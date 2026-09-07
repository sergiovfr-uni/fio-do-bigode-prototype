<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class GooglePlayReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (!config('google_play_review.enabled')) return;

        $pin = (string) config('google_play_review.pin');
        $sellerPassword = (string) config('google_play_review.seller.password');
        $buyerPassword = (string) config('google_play_review.buyer.password');

        if (!preg_match('/^\d{6}$/', $pin) || strlen($sellerPassword) < 10 || strlen($buyerPassword) < 10) {
            throw new RuntimeException('Google Play review access requires a six-digit PIN and two passwords with at least 10 characters.');
        }

        $now = now();
        $common = [
            'kyc_status' => 'verified',
            'risk_score' => 5,
            'reputation_score' => 100,
            'account_status' => 'active',
            'email_verified_at' => $now,
            'identity_document' => 'GOOGLE-PLAY-REVIEW',
            'birth_date' => '1990-01-01',
            'marital_status' => 'Not applicable',
            'occupation' => 'App reviewer',
            'nationality' => 'Brazilian',
            'address_line' => 'Review environment',
            'address_number' => '1',
            'district' => 'Review',
            'city' => 'Belo Horizonte',
            'state' => 'MG',
            'postal_code' => '30110000',
            'updated_at' => $now,
        ];

        $seller = User::updateOrCreate(
            ['email' => config('google_play_review.seller.email')],
            array_merge($common, [
                'name' => 'Google Play Review Seller',
                'cpf' => '90000000001',
                'phone' => '31900000001',
                'password' => Hash::make($sellerPassword),
            ])
        );

        $buyer = User::updateOrCreate(
            ['email' => config('google_play_review.buyer.email')],
            array_merge($common, [
                'name' => 'Google Play Review Buyer',
                'cpf' => '90000000002',
                'phone' => '31900000002',
                'password' => Hash::make($buyerPassword),
            ])
        );

        $planId = DB::table('plans')->where('slug', 'ouro')->value('id');
        if (!$planId) throw new RuntimeException('The Ouro plan must exist before seeding Google Play review users.');

        foreach ([$seller, $buyer] as $user) {
            DB::table('subscriptions')->updateOrInsert(
                ['user_id' => $user->id, 'gateway' => 'google_play_review'],
                [
                    'plan_id' => $planId,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_ends_at' => $now->copy()->addYears(10),
                    'external_id' => 'google-play-review-'.$user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
