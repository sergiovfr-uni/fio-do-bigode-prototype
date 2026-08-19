<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MvpCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_create_direct_deal(): void
    {
        $seller = User::create(['name'=>'Seller','cpf'=>'11111111111','email'=>'seller@test.local','phone'=>'31999990001','password'=>Hash::make('Password123!'),'kyc_status'=>'verified']);
        $buyer = User::create(['name'=>'Buyer','cpf'=>'22222222222','email'=>'buyer@test.local','phone'=>'31999990002','password'=>Hash::make('Password123!'),'kyc_status'=>'verified']);

        $token = $seller->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/deals',[
            'buyer_id'=>$buyer->id,'title'=>'Notebook','description'=>'Notebook usado em bom estado',
            'total_amount'=>5000,'down_payment'=>1000,'installments'=>4,'monthly_interest'=>0,
        ])->assertCreated()->assertJsonPath('origin','direct')->assertJsonPath('status','proposal_sent');
    }

    public function test_unverified_user_cannot_create_direct_deal(): void
    {
        $seller = User::create(['name'=>'Seller','cpf'=>'33333333333','email'=>'pending@test.local','phone'=>'31999990003','password'=>Hash::make('Password123!'),'kyc_status'=>'pending']);
        $buyer = User::create(['name'=>'Buyer','cpf'=>'44444444444','email'=>'verified@test.local','phone'=>'31999990004','password'=>Hash::make('Password123!'),'kyc_status'=>'verified']);

        $this->withToken($seller->createToken('test')->plainTextToken)->postJson('/api/v1/deals',[
            'buyer_id'=>$buyer->id,'title'=>'Moto','description'=>'Moto usada','total_amount'=>12000,'installments'=>12,
        ])->assertForbidden();
    }

    public function test_consent_is_persisted_with_version(): void
    {
        $user = User::create(['name'=>'User','cpf'=>'55555555555','email'=>'consent@test.local','phone'=>'31999990005','password'=>Hash::make('Password123!'),'kyc_status'=>'verified']);
        $this->withToken($user->createToken('test')->plainTextToken)->postJson('/api/v1/compliance/consents',[
            'type'=>'privacy','version'=>'1.0',
        ])->assertOk()->assertJson(['accepted'=>true,'type'=>'privacy','version'=>'1.0']);
        $this->assertDatabaseHas('consents',['user_id'=>$user->id,'type'=>'privacy','version'=>'1.0']);
    }
}
