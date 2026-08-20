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
        ]);

        return response()->json([
            'user' => $user,
            'next' => 'kyc',
            'message' => 'Cadastro criado. Conclua a validação de identidade antes de operar.',
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

        // 2FA temporariamente removido para a fase atual de homologação.
        $user->tokens()->where('name', 'mobile')->delete();

        return response()->json([
            'token'=>$user->createToken('mobile')->plainTextToken,
            'token_type'=>'Bearer',
            'user'=>$user,
            'next'=>'authenticated',
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

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible.str_repeat('*', max(2, mb_strlen($local)-2)).'@'.$domain;
    }
}
