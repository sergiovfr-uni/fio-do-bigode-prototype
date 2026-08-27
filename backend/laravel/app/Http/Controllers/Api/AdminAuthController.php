<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Credenciais inválidas.');
        abort_unless($user->is_admin, 403, 'Esta conta não possui acesso administrativo.');
        abort_unless(($user->account_status ?? 'active') === 'active', 403, 'Esta conta administrativa está bloqueada.');

        $challengeId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        Cache::put('admin-2fa:'.$challengeId, [
            'user_id' => $user->id,
            'code' => $code,
            'attempts' => 0,
        ], now()->addMinutes(5));

        $this->sendTwoFactorCode($user, $code);

        return response()->json([
            'two_factor_required' => true,
            'challenge_id' => $challengeId,
            'delivery' => 'email',
            'masked_email' => $this->maskEmail($user->email),
            'expires_in' => 300,
            'message' => 'Enviamos o código administrativo de 6 dígitos para seu e-mail.',
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        $cacheKey = 'admin-2fa:'.$data['challenge_id'];
        $challenge = Cache::get($cacheKey);
        abort_unless($challenge, 422, 'Código inválido ou expirado.');

        $attempts = (int) ($challenge['attempts'] ?? 0) + 1;
        if ($attempts > 5) {
            Cache::forget($cacheKey);
            abort(429, 'Muitas tentativas. Faça login novamente.');
        }

        if (!hash_equals((string) $challenge['code'], (string) $data['code'])) {
            $challenge['attempts'] = $attempts;
            Cache::put($cacheKey, $challenge, now()->addMinutes(5));
            abort(422, 'Código inválido ou expirado.');
        }

        Cache::forget($cacheKey);
        $user = User::findOrFail($challenge['user_id']);
        abort_unless($user->is_admin && ($user->account_status ?? 'active') === 'active', 403, 'Acesso administrativo revogado.');

        $user->tokens()->where('name', 'admin-panel')->delete();

        return response()->json([
            'token' => $user->createToken('admin-panel', ['admin:read'])->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->adminUser($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->adminUser($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function adminUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
        ];
    }

    private function sendTwoFactorCode(User $user, string $code): void
    {
        abort_unless(env('RESEND_API_KEY'), 503, 'Envio do código administrativo temporariamente indisponível.');

        Http::withToken(env('RESEND_API_KEY'))
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from' => 'Fio do Bigode <naoresponda@nofiodobigode.app.br>',
                'to' => [$user->email],
                'subject' => 'Código de acesso ao painel Fio do Bigode',
                'html' => "<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'><h2>Acesso administrativo</h2><p>Use o código abaixo para entrar no painel:</p><div style='font-size:32px;font-weight:bold;letter-spacing:8px;padding:20px;background:#f5f2ea;text-align:center'>{$code}</div><p>O código expira em 5 minutos e só pode ser usado uma vez.</p><p style='font-size:12px;color:#777'>Se você não tentou acessar o painel, altere sua senha e avise o responsável técnico.</p></div>",
            ])
            ->throw();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, mb_strlen($local) - 2)).'@'.$domain;
    }
}
