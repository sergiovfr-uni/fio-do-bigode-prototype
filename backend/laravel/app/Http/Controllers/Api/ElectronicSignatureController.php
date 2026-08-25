<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElectronicSignatureController extends Controller
{
    private const CONSENT_VERSION = '1.0';
    private const CONSENT_TEXT = 'Declaro que li o documento, concordo com seu conteúdo e aceito utilizar a assinatura eletrônica do Fio do Bigode como manifestação de vontade, vinculada à minha identidade, ao código de confirmação e ao registro de integridade do documento.';

    public function requestCode(Request $request, Deal $deal, DealEventService $events)
    {
        $role = $this->authorizedRole($request, $deal);
        abort_unless($deal->witnesses()->count() === 0, 422, 'A assinatura eletrônica integrada está disponível, neste MVP, para a formalização sem testemunhas.');
        $source = $this->sourceDocument($deal, $role);
        $code = (string) random_int(100000, 999999);
        $challengeId = (string) Str::uuid();

        DB::table('deal_electronic_signatures')->updateOrInsert(
            ['deal_id'=>$deal->id, 'role'=>$role],
            [
                'challenge_id'=>$challengeId,
                'user_id'=>$request->user()->id,
                'otp_hash'=>Hash::make($code),
                'attempts'=>0,
                'expires_at'=>now()->addMinutes(5),
                'verified_at'=>null,
                'signed_at'=>null,
                'source_document_sha256'=>$source->sha256,
                'signature_image_path'=>null,
                'signature_image_sha256'=>null,
                'signed_document_sha256'=>null,
                'consent_version'=>null,
                'consent_text_sha256'=>null,
                'ip_address'=>null,
                'user_agent'=>null,
                'evidence'=>null,
                'evidence_seal'=>null,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]
        );

        $this->sendCode($request->user()->email, $request->user()->name, $code, $deal, $role);
        $events->record($deal, $request->user()->id, 'electronic_signature_code_sent', ['role'=>$role]);

        $response = [
            'challenge_id'=>$challengeId,
            'masked_email'=>$this->maskEmail($request->user()->email),
            'expires_in'=>300,
            'consent_version'=>self::CONSENT_VERSION,
            'consent_text'=>self::CONSENT_TEXT,
            'message'=>'Código enviado. Leia o documento, confirme a ciência e assine na tela.',
        ];
        if (app()->environment('testing')) $response['test_code'] = $code;

        return response()->json($response);
    }

    public function sign(Request $request, Deal $deal, ContractService $contracts, DealEventService $events)
    {
        $role = $this->authorizedRole($request, $deal);
        $data = $request->validate([
            'challenge_id'=>['required','uuid'],
            'code'=>['required','digits:6'],
            'consent'=>['accepted'],
            'consent_version'=>['required','in:'.self::CONSENT_VERSION],
            'signature_data_url'=>['required','string','max:1500000'],
        ]);

        $signature = DB::table('deal_electronic_signatures')
            ->where('deal_id', $deal->id)
            ->where('user_id', $request->user()->id)
            ->where('role', $role)
            ->where('challenge_id', $data['challenge_id'])
            ->first();
        abort_unless($signature && !$signature->signed_at, 422, 'Solicitação de assinatura inválida ou já utilizada.');
        abort_if(now()->greaterThan(\Illuminate\Support\Carbon::parse($signature->expires_at)), 422, 'O código expirou. Solicite um novo.');

        $attempts = (int) $signature->attempts + 1;
        if ($attempts > 5) {
            DB::table('deal_electronic_signatures')->where('id', $signature->id)->delete();
            abort(429, 'Muitas tentativas. Solicite um novo código.');
        }
        DB::table('deal_electronic_signatures')->where('id', $signature->id)->update(['attempts'=>$attempts, 'updated_at'=>now()]);
        abort_unless(Hash::check($data['code'], $signature->otp_hash), 422, 'Código inválido ou expirado.');

        $source = $this->sourceDocument($deal, $role);
        abort_unless(hash_equals($signature->source_document_sha256, $source->sha256), 422, 'O documento mudou após o envio do código. Solicite um novo código.');

        $encoded = preg_replace('/^data:image\/png;base64,/', '', $data['signature_data_url']);
        $image = base64_decode($encoded, true);
        abort_unless($image !== false && str_starts_with($image, "\x89PNG\r\n\x1a\n"), 422, 'Assinatura desenhada inválida.');
        abort_if(strlen($image) > 1024 * 1024, 422, 'A assinatura deve ter no máximo 1 MB.');

        $imageHash = hash('sha256', $image);
        $imagePath = 'deals/'.$deal->public_id.'/signatures/'.$role.'-'.$imageHash.'.png';
        Storage::disk('local')->put($imagePath, $image);
        $signedAt = now();
        $consentHash = hash('sha256', self::CONSENT_TEXT);
        $evidence = [
            'method'=>'email_otp_plus_drawn_signature',
            'challenge_id'=>$data['challenge_id'],
            'user_id'=>$request->user()->id,
            'role'=>$role,
            'source_document_sha256'=>$source->sha256,
            'signature_image_sha256'=>$imageHash,
            'consent_version'=>self::CONSENT_VERSION,
            'consent_text_sha256'=>$consentHash,
            'signed_at'=>$signedAt->toIso8601String(),
            'ip_address'=>$request->ip(),
            'user_agent'=>Str::limit((string) $request->userAgent(), 500, ''),
        ];
        $evidenceJson = json_encode($evidence, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $evidenceSeal = hash_hmac('sha256', $evidenceJson, (string) config('app.key'));

        DB::table('deal_electronic_signatures')->where('id', $signature->id)->update([
            'verified_at'=>$signedAt,
            'signed_at'=>$signedAt,
            'signature_image_path'=>$imagePath,
            'signature_image_sha256'=>$imageHash,
            'consent_version'=>self::CONSENT_VERSION,
            'consent_text_sha256'=>$consentHash,
            'ip_address'=>$request->ip(),
            'user_agent'=>Str::limit((string) $request->userAgent(), 500, ''),
            'evidence'=>$evidenceJson,
            'evidence_seal'=>$evidenceSeal,
            'updated_at'=>$signedAt,
        ]);

        $generated = $contracts->generateElectronicVersion($deal->fresh(), $request->user()->id, $role);
        DB::table('deal_electronic_signatures')->where('id', $signature->id)->update([
            'signed_document_sha256'=>$generated['sha256'],
            'updated_at'=>now(),
        ]);

        if ($role === 'seller') {
            $deal->update(['seller_signed_document_id'=>$generated['document_id'], 'status'=>'counterparty_signature_pending']);
            $events->record($deal, $request->user()->id, 'seller_electronic_signature_completed', ['document_id'=>$generated['document_id'], 'sha256'=>$generated['sha256']]);
            $events->notify($deal, $deal->buyer_id, 'buyer_signature_required', 'Contrato pronto para sua assinatura', 'O vendedor assinou eletronicamente. Leia o documento e conclua sua assinatura no Fio do Bigode.', ['deal_id'=>$deal->id]);
            $message = 'Assinatura do vendedor concluída. O comprador foi notificado.';
        } else {
            $nextStatus = (float) $deal->down_payment > 0 ? 'entry_receipt_pending' : 'active';
            $deal->update(['fully_signed_document_id'=>$generated['document_id'], 'formalized_at'=>now(), 'status'=>$nextStatus]);
            $events->record($deal, $request->user()->id, 'all_electronic_signatures_completed', ['document_id'=>$generated['document_id'], 'sha256'=>$generated['sha256']]);
            $events->notify($deal, $deal->seller_id, 'documental_closing_complete', 'Contrato assinado pelas duas partes', (float) $deal->down_payment > 0 ? 'A formalização terminou. Agora aguarde o comprovante da entrada.' : 'A formalização terminou e a negociação entrou no acompanhamento das parcelas.', ['deal_id'=>$deal->id]);
            $message = 'Assinaturas concluídas. O documento final e o certificado de evidências estão disponíveis.';
        }

        return response()->json([
            'message'=>$message,
            'status'=>$deal->fresh()->status,
            'document_sha256'=>$generated['sha256'],
        ]);
    }

    private function authorizedRole(Request $request, Deal $deal): string
    {
        if ($deal->status === 'signature_pending' && (int) $request->user()->id === (int) $deal->seller_id) return 'seller';
        if ($deal->status === 'counterparty_signature_pending' && (int) $request->user()->id === (int) $deal->buyer_id) return 'buyer';
        abort(403, 'A assinatura não está disponível para este usuário nesta etapa.');
    }

    private function sourceDocument(Deal $deal, string $role): object
    {
        $document = $role === 'buyer' && $deal->seller_signed_document_id
            ? DB::table('deal_documents')->find($deal->seller_signed_document_id)
            : DB::table('deal_documents')->where('deal_id', $deal->id)->where('type', 'unsigned_contract')->latest()->first();
        abort_unless($document && Storage::disk('local')->exists($document->storage_path), 404, 'Documento não disponível para assinatura.');
        return $document;
    }

    private function sendCode(string $email, string $name, string $code, Deal $deal, string $role): void
    {
        abort_unless(env('RESEND_API_KEY') || app()->environment('testing'), 503, 'Envio do código de assinatura temporariamente indisponível.');
        if (app()->environment('testing')) return;
        $safeName = e($name);
        $safeTitle = e($deal->title ?: 'Negociação '.$deal->public_id);
        $party = $role === 'seller' ? 'vendedor' : 'comprador';
        Http::withToken(env('RESEND_API_KEY'))->acceptJson()->post('https://api.resend.com/emails', [
            'from'=>'Fio do Bigode <naoresponda@nofiodobigode.app.br>',
            'to'=>[$email],
            'subject'=>'Código para assinar o contrato — Fio do Bigode',
            'html'=>"<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'><h2>Assinatura eletrônica</h2><p>Olá, {$safeName}. Confirme sua assinatura como {$party} na negociação <b>{$safeTitle}</b>.</p><div style='font-size:32px;font-weight:bold;letter-spacing:8px;padding:20px;background:#f5f2ea;text-align:center'>{$code}</div><p>O código expira em 5 minutos e só pode ser usado uma vez.</p><p style='font-size:12px;color:#777'>Se você não iniciou esta assinatura, não compartilhe o código e entre em contato com a outra parte.</p></div>",
        ])->throw();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return mb_substr($local, 0, min(2, mb_strlen($local))).str_repeat('*', max(2, mb_strlen($local)-2)).'@'.$domain;
    }
}
