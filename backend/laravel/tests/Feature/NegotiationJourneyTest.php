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

    public function test_seller_and_buyer_complete_electronic_signature_in_sequence(): void
    {
        $seller = $this->verifiedParty('electronic-seller@journey.test', '33333333333');
        $buyer = $this->verifiedParty('electronic-buyer@journey.test', '44444444444');
        $sellerToken = $seller->createToken('seller-signature')->plainTextToken;
        $buyerToken = $buyer->createToken('buyer-signature')->plainTextToken;

        $dealId = $this->withToken($sellerToken)->postJson('/api/v1/deals', [
            'buyer_id'=>$buyer->id,
            'title'=>'Eletrônico de teste',
            'description'=>'Negociação com assinatura eletrônica integrada.',
            'total_amount'=>1000,
            'down_payment'=>100,
            'installments'=>3,
            'monthly_interest'=>0,
            'first_due_date'=>now()->addMonth()->toDateString(),
        ])->assertCreated()->json('id');

        $this->withToken($buyerToken)->postJson('/api/v1/deals/'.$dealId.'/accept')->assertOk();
        $this->withToken($sellerToken)->postJson('/api/v1/deals/'.$dealId.'/witnesses/skip')
            ->assertOk()->assertJsonPath('status', 'signature_pending');

        $sellerChallenge = $this->withToken($sellerToken)
            ->postJson('/api/v1/deals/'.$dealId.'/electronic-signature/code')
            ->assertOk()->json();
        $this->withToken($sellerToken)->postJson('/api/v1/deals/'.$dealId.'/electronic-signature/sign', [
            'challenge_id'=>$sellerChallenge['challenge_id'],
            'code'=>$sellerChallenge['test_code'],
            'consent'=>true,
            'consent_version'=>'1.0',
            'signature_data_url'=>$this->signaturePng(),
        ])->assertOk()->assertJsonPath('status', 'counterparty_signature_pending');

        $buyerChallenge = $this->withToken($buyerToken)
            ->postJson('/api/v1/deals/'.$dealId.'/electronic-signature/code')
            ->assertOk()->json();
        $this->withToken($buyerToken)->postJson('/api/v1/deals/'.$dealId.'/electronic-signature/sign', [
            'challenge_id'=>$buyerChallenge['challenge_id'],
            'code'=>$buyerChallenge['test_code'],
            'consent'=>true,
            'consent_version'=>'1.0',
            'signature_data_url'=>$this->signaturePng(),
        ])->assertOk()->assertJsonPath('status', 'entry_receipt_pending');

        $this->assertDatabaseHas('deal_electronic_signatures', ['deal_id'=>$dealId, 'role'=>'seller']);
        $this->assertDatabaseHas('deal_electronic_signatures', ['deal_id'=>$dealId, 'role'=>'buyer']);
        $this->assertDatabaseHas('deals', ['id'=>$dealId, 'status'=>'entry_receipt_pending']);
    }

    private function signaturePng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
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
