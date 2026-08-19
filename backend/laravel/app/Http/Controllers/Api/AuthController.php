<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
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

        $user = User::create([...$data, 'password' => Hash::make($data['password']), 'kyc_status' => 'pending']);
        return response()->json(['user' => $user, 'next' => 'kyc'], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Credenciais inválidas.');

        $challenge = (string) random_int(100000, 999999);
        $challengeId = (string) Str::uuid();
        Cache::put('2fa:'.$challengeId, ['user_id'=>$user->id,'code'=>$challenge], now()->addMinutes(5));

        // MVP: enviar o código por provedor de e-mail. Nunca retornar o código em produção.
        return response()->json(['challenge_id'=>$challengeId,'expires_in'=>300,'next'=>'2fa']);
    }

    public function verifyTwoFactor(Request $request)
    {
        $data = $request->validate(['challenge_id'=>['required','uuid'],'code'=>['required','digits:6']]);
        $challenge = Cache::pull('2fa:'.$data['challenge_id']);
        abort_unless($challenge && hash_equals($challenge['code'], $data['code']), 422, 'Token inválido ou expirado.');
        $user = User::findOrFail($challenge['user_id']);
        return response()->json(['token'=>$user->createToken('mobile')->plainTextToken,'user'=>$user]);
    }

    public function me(Request $request) { return $request->user(); }
    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return response()->noContent(); }
}
