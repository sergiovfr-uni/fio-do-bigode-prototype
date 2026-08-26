<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiditKycController extends Controller
{
    public function start(Request $request)
    {
        $user = $request->user();
        if ($user->kyc_status === 'verified') {
            return response()->json(['kyc_status'=>'verified','message'=>'Sua identidade já está verificada.']);
        }

        $existing = DB::table('didit_kyc_sessions')
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Approved','Declined','Expired','Abandoned','Kyc Expired'])
            ->latest()->first();
        if ($existing && $existing->verification_url) {
            return response()->json(['session_id'=>$existing->session_id,'url'=>$existing->verification_url,'status'=>$existing->status]);
        }

        abort_unless(config('didit.api_key') && config('didit.workflow_id'), 503, 'A validação de identidade ainda não foi configurada.');
        $response = Http::timeout(20)
            ->withHeaders(['x-api-key'=>config('didit.api_key')])
            ->acceptJson()
            ->post(rtrim(config('didit.api_url'), '/').'/v3/session/', [
                'workflow_id'=>config('didit.workflow_id'),
                'vendor_data'=>(string) $user->id,
                'callback'=>config('didit.callback_url'),
                'language'=>'pt',
                'metadata'=>['source'=>'fio-do-bigode','user_id'=>(string) $user->id],
            ]);

        if (!$response->successful()) {
            Log::warning('Didit recusou a criação da sessão KYC.', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            abort(502, 'A Didit recusou a criação da sessão (HTTP '.$response->status().').');
        }
        $payload = $response->json();
        $sessionId = $payload['session_id'] ?? $payload['id'] ?? null;
        $url = $payload['url'] ?? $payload['session_url'] ?? null;
        abort_unless($sessionId && $url, 502, 'A Didit não retornou uma sessão válida.');

        DB::table('didit_kyc_sessions')->insert([
            'user_id'=>$user->id,
            'session_id'=>$sessionId,
            'workflow_id'=>config('didit.workflow_id'),
            'environment'=>config('didit.environment'),
            'status'=>'Not Started',
            'verification_url'=>$url,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        $user->update(['kyc_status'=>'pending']);

        return response()->json(['session_id'=>$sessionId,'url'=>$url,'status'=>'Not Started'], 201);
    }

    public function status(Request $request)
    {
        $session = DB::table('didit_kyc_sessions')->where('user_id',$request->user()->id)->latest()->first();
        if ($session && !in_array($session->status, ['Approved','Declined','Expired','Abandoned','Kyc Expired'], true)) {
            $response = Http::timeout(20)
                ->withHeaders(['x-api-key'=>config('didit.api_key')])
                ->acceptJson()
                ->get(rtrim(config('didit.api_url'), '/').'/v3/session/'.$session->session_id.'/decision/');

            if ($response->successful()) {
                $this->applyDecision($session, $response->json(), 'status-check-'.now()->timestamp);
                $session = DB::table('didit_kyc_sessions')->where('id',$session->id)->first();
                $request->user()->refresh();
            } else {
                Log::warning('Didit recusou a consulta da sessão KYC.', [
                    'user_id'=>$request->user()->id,
                    'session_id'=>$session->session_id,
                    'status'=>$response->status(),
                    'response'=>$response->json() ?? $response->body(),
                ]);
            }
        }

        return response()->json([
            'kyc_status'=>$request->user()->kyc_status,
            'session'=>$session ? [
                'session_id'=>$session->session_id,
                'status'=>$session->status,
                'url'=>$session->verification_url,
                'completed_at'=>$session->completed_at,
            ] : null,
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->json()->all();
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature-V2', '');
        $simpleSignature = (string) $request->header('X-Signature-Simple', '');
        abort_unless($timestamp !== '' && ctype_digit($timestamp) && abs(time()-(int)$timestamp) <= 300, 401, 'Webhook expirado.');
        abort_unless(($signature !== '' || $simpleSignature !== '') && config('didit.webhook_secret'), 401, 'Assinatura ausente.');

        $canonical = json_encode($this->canonicalize($payload), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $canonical, config('didit.webhook_secret'));
        $simpleCanonical = implode(':', [
            $payload['timestamp'] ?? '',
            $payload['session_id'] ?? '',
            $payload['status'] ?? '',
            $payload['webhook_type'] ?? '',
        ]);
        $simpleExpected = hash_hmac('sha256', $simpleCanonical, config('didit.webhook_secret'));
        $verified = ($signature !== '' && hash_equals($expected, $signature))
            || ($simpleSignature !== '' && hash_equals($simpleExpected, $simpleSignature));
        abort_unless($verified, 401, 'Assinatura inválida.');

        $eventType = $payload['webhook_type'] ?? '';
        $sessionId = $payload['session_id'] ?? null;
        abort_unless($sessionId && $eventType, 422, 'Evento inválido.');
        $eventId = $payload['event_id'] ?? hash('sha256', implode('|', [$sessionId,$eventType,$payload['timestamp'] ?? $timestamp]));

        $inserted = DB::table('didit_webhook_events')->insertOrIgnore([
            'event_id'=>$eventId,
            'event_type'=>$eventType,
            'session_id'=>$sessionId,
            'payload'=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        if (!$inserted) return response()->json(['received'=>true,'duplicate'=>true]);

        if (in_array($eventType, ['status.updated','data.updated'], true) && $sessionId) {
            $session = DB::table('didit_kyc_sessions')->where('session_id',$sessionId)->first();
            if ($session) {
                $this->applyDecision($session, $payload, $eventId);
            }
        }

        return response()->json(['received'=>true]);
    }

    private function applyDecision(object $session, array $payload, string $eventId): void
    {
        $status = (string) ($payload['status'] ?? 'In Progress');
        $userStatus = match ($status) {
            'Approved' => 'verified',
            'Declined' => 'rejected',
            'In Review' => 'review',
            'Expired', 'Kyc Expired' => 'expired',
            default => 'pending',
        };

        DB::transaction(function () use ($payload,$eventId,$session,$status,$userStatus): void {
            DB::table('didit_kyc_sessions')->where('id',$session->id)->update([
                'status'=>$status,
                'decision'=>json_encode($payload['decision'] ?? $payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'completed_at'=>in_array($status,['Approved','Declined','Expired','Abandoned','Kyc Expired'],true) ? now() : null,
                'updated_at'=>now(),
            ]);
            DB::table('users')->where('id',$session->user_id)->update([
                'kyc_status'=>$userStatus,
                'risk_score'=>$status === 'Approved' ? 0 : ($status === 'Declined' ? 100 : 50),
                'updated_at'=>now(),
            ]);
            DB::table('kyc_checks')->updateOrInsert(
                ['user_id'=>$session->user_id,'provider'=>'didit','check_type'=>'full','external_id'=>$session->session_id],
                [
                    'status'=>$userStatus,
                    'result'=>json_encode(['event_id'=>$eventId,'didit_status'=>$status], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'verified_at'=>$status === 'Approved' ? now() : null,
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]
            );
            DB::table('didit_webhook_events')->where('event_id',$eventId)->update(['processed_at'=>now(),'updated_at'=>now()]);
        });
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn ($item) => $this->canonicalize($item), $value);
        ksort($value);
        foreach ($value as $key=>$item) $value[$key]=$this->canonicalize($item);
        return $value;
    }
}
