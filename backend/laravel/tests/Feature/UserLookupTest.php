<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_search_never_returns_an_unrelated_user(): void
    {
        $searcher = $this->user('Sergio Ferreira', '11111111111', 'sergio@test.local');
        $this->user('Bruno Sousa', '22222222222', 'bruno@test.local');
        $kevin = $this->user('Kevin Moraes', '33333333333', 'kevin@test.local');

        $response = $this->withToken($searcher->createToken('test')->plainTextToken)
            ->getJson('/api/v1/users/lookup?query=Kevin');

        $response->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $kevin->id)
            ->assertJsonPath('users.0.name', 'Kevin Moraes');

        $this->assertStringNotContainsString('Bruno', $response->getContent());
    }

    private function user(string $name, string $cpf, string $email): User
    {
        return User::create([
            'name'=>$name,
            'cpf'=>$cpf,
            'email'=>$email,
            'phone'=>'319'.substr($cpf, -8),
            'password'=>Hash::make('Password123!'),
            'kyc_status'=>'verified',
            'account_status'=>'active',
        ]);
    }
}
