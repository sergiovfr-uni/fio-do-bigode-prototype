<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NegotiationJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_leads_negotiation_until_document_generation(): void
    {
        $seller = $this->verifiedParty('seller@journey.test', '11111111111');
        $buyer = $this->verifiedParty('buyer@journey.test', '22222222222');
        $firstDueDate = now()->addMonth()->toDateString();

        $dealId = $this->withToken($seller->createToken('seller')->plainTextToken)
            ->postJson('/api/v1/deals', [
                'buyer_id'=>$buyer->id,
                'title'=>'Moto de repasse',
                'description'=>'Moto usada, conforme vistoria das partes.',
                'total_amount'=>12000,
                'down_payment'=>2000,
                'installments'=>5,
                'monthly_interest'=>1.5,
                'first_due_date'=>$firstDueDate,
            ])
            ->assertCreated()
            ->assertJsonPath('seller_id', $seller->id)
            ->assertJsonPath('status', 'proposal_sent')
            ->json('id');

        $this->withToken($seller->createToken('seller-2')->plainTextToken)
            ->postJson('/api/v1/deals/'.$dealId.'/accept')
            ->assertStatus(422);

        $this->withToken($buyer->createToken('buyer')->plainTextToken)
            ->postJson('/api/v1/deals/'.$dealId.'/accept')
            ->assertOk()
            ->assertJsonPath('status', 'witnesses_pending')
            ->assertJsonCount(5, 'payment_schedule');

        $this->withToken($buyer->createToken('buyer-2')->plainTextToken)
            ->postJson('/api/v1/deals/'.$dealId.'/witnesses/skip')
            ->assertForbidden();

        $this->withToken($seller->createToken('seller-3')->plainTextToken)
            ->postJson('/api/v1/deals/'.$dealId.'/witnesses/skip')
            ->assertOk()
            ->assertJsonPath('status', 'signature_pending');

        $this->assertDatabaseHas('deals', [
            'id'=>$dealId,
            'seller_id'=>$seller->id,
            'buyer_id'=>$buyer->id,
            'status'=>'signature_pending',
            'first_due_date'=>$firstDueDate,
        ]);
    }

    private function verifiedParty(string $email, string $cpf): User
    {
        return User::create([
            'name'=>'Pessoa de Teste',
            'cpf'=>$cpf,
            'email'=>$email,
            'phone'=>'31999990000',
            'password'=>Hash::make('Password123!'),
            'kyc_status'=>'verified',
            'identity_document'=>'MG-1234567',
            'birth_date'=>'1990-01-01',
            'marital_status'=>'solteiro(a)',
            'occupation'=>'comerciante',
            'nationality'=>'brasileiro(a)',
            'address_line'=>'Rua de Teste',
            'address_number'=>'100',
            'district'=>'Centro',
            'city'=>'Belo Horizonte',
            'state'=>'MG',
            'postal_code'=>'30110000',
        ]);
    }
}
