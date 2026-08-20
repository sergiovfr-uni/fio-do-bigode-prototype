<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
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

        $user->tokens()->where('name', 'mobile')->delete();

        return response()->json([
            'token'=>$user->createToken('mobile')->plainTextToken,
            'token_type'=>'Bearer',
            'user'=>$user,
            'next'=>$user->kyc_status === 'verified' ? 'authenticated' : 'kyc',
            'two_factor_required'=>false,
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
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
