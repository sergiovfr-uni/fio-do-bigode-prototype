<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\User;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFlowTest extends TestCase
{
 use RefreshDatabase;

 public function test_accepted_deal_generates_installments(): void
 {
  $seller=User::factory()->create(['kyc_status'=>'verified']);
  $buyer=User::factory()->create(['kyc_status'=>'verified']);
  $deal=Deal::create(['seller_id'=>$seller->id,'buyer_id'=>$buyer->id,'origin'=>'direct','status'=>'accepted','total_amount'=>1200,'down_payment'=>200,'installments'=>5,'monthly_interest'=>0,'terms_locked_at'=>now()]);
  app(InstallmentService::class)->generate($deal);
  $this->assertDatabaseCount('installments',5);
  $this->assertEquals('200.00',$deal->installments()->first()->amount);
 }

 public function test_wallet_is_created_for_authenticated_user(): void
 {
  $user=User::factory()->create(['kyc_status'=>'verified']);
  $this->actingAs($user)->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('provider','mock');
 }
}
