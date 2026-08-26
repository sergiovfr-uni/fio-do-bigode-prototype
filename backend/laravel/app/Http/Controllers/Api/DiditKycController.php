<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
                'language'=>'pt-BR',
                'metadata'=>['source'=>'fio-do-bigode','user_id'=>(string) $user->id],
            ]);

        abort_unless($response->successful(), 502, 'Não foi possível iniciar a validação de identidade.');
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
        abort_unless($timestamp !== '' && ctype_digit($timestamp) && abs(time()-(int)$timestamp) <= 300, 401, 'Webhook expirado.');
        abort_unless($signature !== '' && config('didit.webhook_secret'), 401, 'Assinatura ausente.');

        $canonical = json_encode($this->canonicalize($payload), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $canonical, config('didit.webhook_secret'));
        abort_unless(hash_equals($expected, $signature), 401, 'Assinatura inválida.');

        $eventId = $payload['event_id'] ?? null;
        $eventType = $payload['webhook_type'] ?? '';
        $sessionId = $payload['session_id'] ?? null;
        abort_unless($eventId && $eventType, 422, 'Evento inválido.');

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
                        'decision'=>isset($payload['decision']) ? json_encode($payload['decision'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $session->decision,
                        'completed_at'=>in_array($status,['Approved','Declined','Expired','Abandoned','Kyc Expired'],true) ? now() : null,
                        'updated_at'=>now(),
                    ]);
                    DB::table('users')->where('id',$session->user_id)->update([
                        'kyc_status'=>$userStatus,
                        'risk_score'=>$status === 'Approved' ? 0 : ($status === 'Declined' ? 100 : 50),
                        'updated_at'=>now(),
                    ]);
                    DB::table('kyc_checks')->insert([
                        'user_id'=>$session->user_id,
                        'provider'=>'didit',
                        'check_type'=>'full',
                        'status'=>$userStatus,
                        'external_id'=>$session->session_id,
                        'result'=>json_encode(['event_id'=>$eventId,'didit_status'=>$status], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        'verified_at'=>$status === 'Approved' ? now() : null,
                        'created_at'=>now(),
                        'updated_at'=>now(),
                    ]);
                    DB::table('didit_webhook_events')->where('event_id',$eventId)->update(['processed_at'=>now(),'updated_at'=>now()]);
                });
            }
        }

        return response()->json(['received'=>true]);
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
