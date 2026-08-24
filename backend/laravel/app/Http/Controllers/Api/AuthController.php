<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:160'],
            'cpf' => ['required','string','size:11','unique:users,cpf'],
            'email' => ['required','email','unique:users,email'],
            'phone' => ['required','string','max:20'],
            'password' => ['required','string','min:10'],
        ]);

        $data['cpf'] = preg_replace('/\\D+/', '', $data['cpf']);
        $data['phone'] = preg_replace('/\\D+/', '', $data['phone']);
        $data['email'] = mb_strtolower(trim($data['email']));

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'kyc_status' => 'pending',
            'risk_score' => 50,
            'reputation_score' => 0,
            'account_status' => 'active',
        ]);
        $this->ensureFreeTrial($user);
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token'=>$token,
            'token_type'=>'Bearer',
            'user'=>$user,
            'next'=>'kyc',
            'message'=>'Cadastro criado com Free Trial de 30 dias. Conclua a validação de identidade antes de operar.',
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'=>['required','email'],
            'password'=>['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Credenciais inválidas.');
        abort_unless(($user->account_status ?? 'active') === 'active', 403, 'Esta conta está bloqueada ou em processo de exclusão.');
        $this->ensureFreeTrial($user);

        $challengeId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        Cache::put('2fa:'.$challengeId, [
            'user_id'=>$user->id,
            'code'=>$code,
            'attempts'=>0,
        ], now()->addMinutes(5));

        $this->sendTwoFactorCode($user, $code);

        return response()->json([
            'two_factor_required'=>true,
            'challenge_id'=>$challengeId,
            'delivery'=>'email',
            'masked_email'=>$this->maskEmail($user->email),
            'expires_in'=>300,
            'message'=>'Enviamos um código de 6 dígitos para seu e-mail.',
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $data = $request->validate([
            'challenge_id'=>['required','uuid'],
            'code'=>['required','digits:6'],
        ]);

        $cacheKey = '2fa:'.$data['challenge_id'];
        $challenge = Cache::get($cacheKey);
        abort_unless($challenge, 422, 'Token inválido ou expirado.');

        $attempts = (int)($challenge['attempts'] ?? 0) + 1;
        if ($attempts > 5) {
            Cache::forget($cacheKey);
            abort(429, 'Muitas tentativas. Faça login novamente.');
        }

        if (!hash_equals((string)$challenge['code'], (string)$data['code'])) {
            $challenge['attempts'] = $attempts;
            Cache::put($cacheKey, $challenge, now()->addMinutes(5));
            abort(422, 'Token inválido ou expirado.');
        }

        Cache::forget($cacheKey);
        $user = User::findOrFail($challenge['user_id']);
        $user->tokens()->where('name', 'mobile')->delete();

        return response()->json([
            'token'=>$user->createToken('mobile')->plainTextToken,
            'token_type'=>'Bearer',
            'user'=>$user,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email'=>['required','email']]);
        $user = User::where('email',$data['email'])->first();
        if ($user) {
            $plain = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email'=>$user->email],
                ['token'=>hash('sha256',$plain),'created_at'=>now()]
            );
        }
        return response()->json(['message'=>'Se o e-mail existir, enviaremos as instruções de recuperação.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'=>['required','email'],
            'token'=>['required','string','size:64'],
            'password'=>['required','string','min:10','confirmed'],
        ]);
        $row = DB::table('password_reset_tokens')->where('email',$data['email'])->first();
        abort_unless($row && hash_equals($row->token, hash('sha256',$data['token'])) && now()->diffInMinutes($row->created_at) <= 30, 422, 'Token inválido ou expirado.');
        $user = User::where('email',$data['email'])->firstOrFail();
        $user->update(['password'=>Hash::make($data['password'])]);
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email',$data['email'])->delete();
        return response()->json(['message'=>'Senha redefinida. Faça login novamente.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateQualification(Request $request)
    {
        $data = $request->validate([
            'identity_document'=>['required','string','max:40'], 'birth_date'=>['required','date','before:today'],
            'marital_status'=>['required','string','max:40'], 'occupation'=>['required','string','max:120'],
            'nationality'=>['required','string','max:60'], 'address_line'=>['required','string','max:220'],
            'address_number'=>['required','string','max:30'], 'address_complement'=>['nullable','string','max:100'],
            'district'=>['required','string','max:100'], 'city'=>['required','string','max:100'],
            'state'=>['required','string','size:2'], 'postal_code'=>['required','string'],
        ]);
        $data['postal_code'] = preg_replace('/\D+/', '', $data['postal_code']);
        abort_unless(strlen($data['postal_code']) === 8, 422, 'Informe um CEP válido.');
        $data['state'] = strtoupper($data['state']);
        $request->user()->update($data);
        return response()->json($request->user()->fresh());
    }

    public function lookupUser(Request $request)
    {
        $data = $request->validate(['query'=>['required','string','min:5','max:255']]);
        $query = trim($data['query']);
        $digits = preg_replace('/\\D+/', '', $query);
        $normalizedPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', '')";

        $user = User::query()->where(function ($builder) use ($query, $digits, $normalizedPhoneSql) {
            if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
                $builder->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($query)]);
                return;
            }

            if (strlen($digits) === 11) {
                $builder->where('cpf', $digits)
                    ->orWhereRaw($normalizedPhoneSql.' = ?', [$digits])
                    ->orWhereRaw($normalizedPhoneSql.' = ?', ['55'.$digits]);
                return;
            }

            if (strlen($digits) >= 10) {
                $builder->whereRaw($normalizedPhoneSql.' = ?', [$digits])
                    ->orWhereRaw($normalizedPhoneSql.' = ?', ['55'.$digits]);
            }
        })->first();

        return response()->json(['exists'=>(bool)$user,'user'=>$user ? [
            'id'=>$user->id,
            'name'=>$user->name,
            'email'=>$user->email,
            'phone'=>$user->phone,
            'cpf_masked'=>preg_replace('/(\\d{3})(\\d{3})(\\d{3})(\\d{2})/', '$1.$2.$3-$4', $user->getRawOriginal('cpf')),
            'kyc_status'=>$user->kyc_status,
        ] : null]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }

    private function sendTwoFactorCode(User $user, string $code): void
    {
        abort_unless(env('RESEND_API_KEY'), 503, 'Envio do código de acesso temporariamente indisponível.');

        Http::withToken(env('RESEND_API_KEY'))
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from'=>'Fio do Bigode <naoresponda@nofiodobigode.app.br>',
                'to'=>[$user->email],
                'subject'=>'Seu código de acesso ao Fio do Bigode',
                'html'=>"<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'><h2>Confirme seu acesso</h2><p>Use o código abaixo para entrar:</p><div style='font-size:32px;font-weight:bold;letter-spacing:8px;padding:20px;background:#f5f2ea;text-align:center'>{$code}</div><p>O código expira em 5 minutos e só pode ser usado uma vez.</p><p style='font-size:12px;color:#777'>Se você não tentou entrar, ignore esta mensagem.</p></div>",
            ])
            ->throw();
    }

    private function ensureFreeTrial(User $user): void
    {
        $hasCurrent = DB::table('subscriptions')->where('user_id',$user->id)
            ->whereIn('status',['trial','active'])
            ->where(function($q){$q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at','>',now());})
            ->exists();
        if ($hasCurrent) return;

        $planId = DB::table('plans')->where('slug','trial')->value('id');
        if (!$planId) return;
        DB::table('subscriptions')->insert([
            'user_id'=>$user->id,'plan_id'=>$planId,'status'=>'trial',
            'trial_ends_at'=>now()->addDays(30),'current_period_ends_at'=>now()->addDays(30),
            'gateway'=>null,'external_id'=>null,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible.str_repeat('*', max(2, mb_strlen($local)-2)).'@'.$domain;
    }
}
