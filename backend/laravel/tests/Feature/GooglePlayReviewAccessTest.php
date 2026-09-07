<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GooglePlayReviewAccess;
use Database\Seeders\GooglePlayReviewSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GooglePlayReviewAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_user_can_complete_two_factor_login_with_reusable_pin(): void
    {
        config()->set('google_play_review.enabled', true);
        config()->set('google_play_review.pin', '246810');
        config()->set('google_play_review.seller.email', 'review.seller@nofiodobigode.app.br');

        User::create([
            'name' => 'Review Seller',
            'cpf' => '90000000001',
            'email' => 'review.seller@nofiodobigode.app.br',
            'phone' => '31900000001',
            'password' => Hash::make('ReviewPassword123!'),
            'kyc_status' => 'verified',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'review.seller@nofiodobigode.app.br',
            'password' => 'ReviewPassword123!',
        ])->assertOk()->assertJsonPath('two_factor_required', true);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_id' => $login->json('challenge_id'),
            'code' => '246810',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_review_pin_is_never_enabled_for_a_regular_user(): void
    {
        config()->set('google_play_review.enabled', true);
        config()->set('google_play_review.pin', '246810');
        config()->set('google_play_review.seller.email', 'review.seller@nofiodobigode.app.br');
        config()->set('google_play_review.buyer.email', 'review.buyer@nofiodobigode.app.br');

        $user = User::create([
            'name' => 'Regular User',
            'cpf' => '90000000003',
            'email' => 'regular@example.com',
            'phone' => '31900000003',
            'password' => Hash::make('RegularPassword123!'),
            'kyc_status' => 'verified',
        ]);

        $this->assertNull(GooglePlayReviewAccess::pinFor($user));
    }

    public function test_review_seeder_creates_verified_users_with_active_ouro_plan(): void
    {
        config()->set('google_play_review.enabled', true);
        config()->set('google_play_review.pin', '246810');
        config()->set('google_play_review.seller.email', 'review.seller@nofiodobigode.app.br');
        config()->set('google_play_review.seller.password', 'SellerReviewPassword123!');
        config()->set('google_play_review.buyer.email', 'review.buyer@nofiodobigode.app.br');
        config()->set('google_play_review.buyer.password', 'BuyerReviewPassword123!');

        $this->seed(PlanSeeder::class);
        $this->seed(GooglePlayReviewSeeder::class);

        $seller = User::where('email', 'review.seller@nofiodobigode.app.br')->firstOrFail();
        $buyer = User::where('email', 'review.buyer@nofiodobigode.app.br')->firstOrFail();

        $this->assertSame('verified', $seller->kyc_status);
        $this->assertSame('verified', $buyer->kyc_status);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $seller->id, 'status' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $buyer->id, 'status' => 'active']);
    }
}
