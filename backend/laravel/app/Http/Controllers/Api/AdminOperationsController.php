<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\Listing;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminOperationsController extends Controller
{
    public function updateUser(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        $data = $request->validate([
            'name'=>['required','string','max:160'],
            'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'phone'=>['required','string','max:20'],
        ]);
        $before = $user->only(['name','email','phone','account_status','kyc_status']);
        $user->update($data);
        $this->audit($request, 'user.updated', 'user', $user->id, $before, $user->fresh()->only(array_keys($before)));
        return response()->json($user->fresh());
    }

    public function changeUserStatus(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        $data = $request->validate([
            'status'=>['required',Rule::in(['active','blocked'])],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        $before = ['account_status'=>$user->account_status];
        $user->update(['account_status'=>$data['status']]);
        if ($data['status'] === 'blocked') $user->tokens()->delete();
        $this->audit($request, 'user.status_changed', 'user', $user->id, $before, ['account_status'=>$data['status']], $data['reason']);
        return response()->json($user->fresh());
    }

    public function retryUserKyc(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        abort_if($user->kyc_status === 'verified', 422, 'Uma identidade verificada não pode ser reiniciada pelo painel.');
        $data = $request->validate(['reason'=>['required','string','min:5','max:500']]);
        $before = ['kyc_status'=>$user->kyc_status,'risk_score'=>$user->risk_score];
        DB::transaction(function () use ($user): void {
            DB::table('didit_kyc_sessions')->where('user_id',$user->id)
                ->whereNotIn('status',['Approved','Declined','Expired','Abandoned','Kyc Expired'])
                ->update(['status'=>'Abandoned','completed_at'=>now(),'updated_at'=>now()]);
            $user->update(['kyc_status'=>'pending','risk_score'=>50]);
        });
        $this->audit($request, 'user.kyc_retry_released', 'user', $user->id, $before, ['kyc_status'=>'pending','risk_score'=>50], $data['reason']);
        return response()->json(['message'=>'Nova tentativa de KYC liberada.','user'=>$user->fresh()]);
    }

    public function sendPasswordReset(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        $data = $request->validate(['reason'=>['required','string','min:5','max:500']]);
        abort_unless(env('RESEND_API_KEY'), 503, 'Recuperação de senha temporariamente indisponível.');
        $plain = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(['email'=>$user->email],['token'=>hash('sha256',$plain),'created_at'=>now()]);
        $url = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/app.html?reset='.rawurlencode($plain).'&email='.rawurlencode($user->email);
        Http::withToken(env('RESEND_API_KEY'))->acceptJson()->post('https://api.resend.com/emails',[
            'from'=>'Fio do Bigode <naoresponda@nofiodobigode.app.br>','to'=>[$user->email],
            'subject'=>'Redefina sua senha do Fio do Bigode',
            'html'=>"<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'><h2>Redefinição de senha</h2><p>Olá, ".e($user->name).".</p><p>A administração enviou um link seguro para você criar uma nova senha.</p><p style='margin:28px 0'><a href='".e($url)."' style='background:#111;color:#fff;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:bold'>Criar nova senha</a></p><p>O link expira em 30 minutos e só pode ser usado uma vez.</p></div>",
        ])->throw();
        $this->audit($request, 'user.password_reset_sent', 'user', $user->id, null, ['email'=>$user->email], $data['reason']);
        return response()->json(['message'=>'Link de recuperação enviado.']);
    }

    public function extendTrial(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        $data = $request->validate([
            'days'=>['required','integer','min:1','max:90'],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        $plan = Plan::where('slug','trial')->where('active',true)->firstOrFail();
        $subscription = Subscription::where('user_id',$user->id)->where('status','trial')->latest()->first();
        abort_if(!$subscription && Subscription::where('user_id',$user->id)->where('status','active')->exists(), 422, 'O usuário possui um plano ativo. Use Alterar plano em vez de estender o trial.');
        $before = $subscription?->toArray();
        if (!$subscription) {
            $subscription = new Subscription(['user_id'=>$user->id,'plan_id'=>$plan->id,'status'=>'trial']);
        }
        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture() ? $subscription->trial_ends_at->copy() : now();
        $endsAt = $base->addDays($data['days']);
        $subscription->fill(['plan_id'=>$plan->id,'status'=>'trial','trial_ends_at'=>$endsAt,'current_period_ends_at'=>$endsAt])->save();
        $this->audit($request, 'subscription.trial_extended', 'subscription', $subscription->id, $before, $subscription->fresh()->toArray(), $data['reason']);
        return response()->json($subscription->fresh()->load('plan'));
    }

    public function assignPlan(Request $request, User $user)
    {
        $this->assertNegotiator($user);
        $data = $request->validate([
            'plan_id'=>['required','integer','exists:plans,id'],
            'status'=>['required',Rule::in(['trial','active'])],
            'days'=>['required','integer','min:1','max:365'],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        $plan = Plan::whereKey($data['plan_id'])->where('active',true)->firstOrFail();
        $before = Subscription::where('user_id',$user->id)->whereIn('status',['trial','active'])->get()->toArray();
        $subscription = DB::transaction(function () use ($user,$plan,$data) {
            Subscription::where('user_id',$user->id)->whereIn('status',['trial','active'])->update(['status'=>'cancelled','updated_at'=>now()]);
            $endsAt = now()->addDays($data['days']);
            return Subscription::create([
                'user_id'=>$user->id,'plan_id'=>$plan->id,'status'=>$data['status'],
                'trial_ends_at'=>$data['status']==='trial' ? $endsAt : null,
                'current_period_ends_at'=>$endsAt,'gateway'=>'admin',
            ]);
        });
        $this->audit($request, 'subscription.plan_assigned', 'user', $user->id, $before, $subscription->fresh()->load('plan')->toArray(), $data['reason']);
        return response()->json($subscription->fresh()->load('plan'), 201);
    }

    public function createPlan(Request $request)
    {
        $data = $this->validatePlan($request);
        $plan = Plan::create($data);
        $this->audit($request, 'plan.created', 'plan', $plan->id, null, $plan->toArray());
        return response()->json($plan, 201);
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $before = $plan->toArray();
        $plan->update($this->validatePlan($request, $plan));
        $this->audit($request, 'plan.updated', 'plan', $plan->id, $before, $plan->fresh()->toArray());
        return response()->json($plan->fresh());
    }

    public function changePlanStatus(Request $request, Plan $plan)
    {
        $data = $request->validate(['active'=>['required','boolean'],'reason'=>['required','string','min:5','max:500']]);
        $before = ['active'=>$plan->active];
        $plan->update(['active'=>$data['active']]);
        $this->audit($request, 'plan.status_changed', 'plan', $plan->id, $before, ['active'=>$plan->fresh()->active], $data['reason']);
        return response()->json($plan->fresh());
    }

    public function createListing(Request $request)
    {
        $data = $this->validateListing($request);
        $seller = User::whereKey($data['seller_id'])->where('is_admin',false)->firstOrFail();
        abort_unless($seller->kyc_status === 'verified', 422, 'O vendedor precisa ter KYC verificado.');
        if (($data['status'] ?? 'draft') === 'published') abort_unless(!empty($data['cover_image']), 422, 'Informe a imagem principal para publicar.');
        $listing = Listing::create([
            ...$data,
            'published_at'=>($data['status'] ?? 'draft')==='published' ? now() : null,
            'expires_at'=>($data['status'] ?? 'draft')==='published' ? now()->addDays(30) : null,
        ]);
        $this->audit($request, 'listing.created', 'listing', $listing->id, null, $listing->toArray());
        return response()->json($listing->load('seller:id,name,email,kyc_status'), 201);
    }

    public function updateListing(Request $request, Listing $listing)
    {
        $before = $listing->toArray();
        $data = $this->validateListing($request, $listing);
        unset($data['seller_id']);
        $listing->update($data);
        $this->audit($request, 'listing.updated', 'listing', $listing->id, $before, $listing->fresh()->toArray());
        return response()->json($listing->fresh()->load('seller:id,name,email,kyc_status'));
    }

    public function changeListingStatus(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(['draft','published','archived'])],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        if ($data['status']==='published') {
            abort_unless($listing->cover_image, 422, 'O anúncio precisa de uma imagem para ser publicado.');
            abort_unless($listing->seller?->kyc_status==='verified', 422, 'O vendedor precisa ter KYC verificado.');
        }
        $before = ['status'=>$listing->status,'published_at'=>$listing->published_at,'expires_at'=>$listing->expires_at];
        $listing->update([
            'status'=>$data['status'],
            'published_at'=>$data['status']==='published' ? ($listing->published_at ?: now()) : $listing->published_at,
            'expires_at'=>$data['status']==='published' ? now()->addDays(30) : $listing->expires_at,
        ]);
        $this->audit($request, 'listing.status_changed', 'listing', $listing->id, $before, $listing->fresh()->only(array_keys($before)), $data['reason']);
        return response()->json($listing->fresh());
    }

    public function changeDealStatus(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(['suspended','cancelled','active','overdue'])],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        abort_if(in_array($deal->status,['paid_off','closed'],true), 422, 'Uma negociação quitada ou concluída não pode ser alterada.');
        if (in_array($data['status'],['active','overdue'],true)) abort_unless($deal->status==='suspended', 422, 'Somente uma negociação suspensa pode ser reativada.');
        $before = ['status'=>$deal->status];
        $deal->update(['status'=>$data['status']]);
        $this->audit($request, 'deal.status_changed', 'deal', $deal->id, $before, ['status'=>$data['status']], $data['reason']);
        return response()->json($deal->fresh());
    }

    public function dealDetails(Deal $deal)
    {
        return response()->json([
            'deal'=>$deal->load(['seller:id,name,email,kyc_status','buyer:id,name,email,kyc_status','listing','installments']),
            'documents'=>DB::table('deal_documents')->where('deal_id',$deal->id)->orderByDesc('id')->get(['id','type','original_name','mime_type','signed','created_at']),
            'events'=>DB::table('deal_events')->where('deal_id',$deal->id)->orderByDesc('id')->limit(100)->get(['id','actor_id','type','payload','created_at']),
            'ratings'=>DB::table('deal_ratings')->where('deal_id',$deal->id)->orderBy('id')->get(),
        ]);
    }

    public function downloadDealDocument(Deal $deal, int $document)
    {
        $file = DB::table('deal_documents')->where('deal_id',$deal->id)->where('id',$document)->first();
        abort_unless($file, 404, 'Documento não encontrado.');
        if (Storage::disk('local')->exists($file->storage_path)) return Storage::disk('local')->download($file->storage_path,$file->original_name);
        abort_unless($file->content_blob, 410, 'Este documento antigo não está mais disponível.');
        return response()->streamDownload(fn()=>print($file->content_blob),$file->original_name,['Content-Type'=>$file->mime_type ?: 'application/octet-stream']);
    }

    public function updateInstallment(Request $request, Installment $installment)
    {
        abort_if($installment->status==='paid', 422, 'Uma parcela paga não pode ter o vencimento alterado.');
        $data = $request->validate(['due_date'=>['required','date'],'reason'=>['required','string','min:5','max:500']]);
        $before = ['due_date'=>$installment->due_date?->format('Y-m-d'),'status'=>$installment->status];
        $installment->update(['due_date'=>$data['due_date']]);
        $this->audit($request, 'installment.due_date_changed', 'installment', $installment->id, $before, $installment->fresh()->only(['due_date','status']), $data['reason']);
        return response()->json($installment->fresh());
    }

    public function rejectInstallmentReceipt(Request $request, Installment $installment)
    {
        abort_unless($installment->receipt_document_id && $installment->status!=='paid', 422, 'Não há comprovante pendente para rejeitar.');
        $data = $request->validate(['reason'=>['required','string','min:5','max:500']]);
        $before = $installment->only(['status','receipt_document_id','receipt_uploaded_at']);
        $installment->update(['status'=>'pending','receipt_document_id'=>null,'receipt_uploaded_at'=>null]);
        $this->audit($request, 'installment.receipt_rejected', 'installment', $installment->id, $before, $installment->fresh()->only(array_keys($before)), $data['reason']);
        return response()->json($installment->fresh());
    }

    public function downloadInstallmentReceipt(Installment $installment)
    {
        $file = $installment->receipt_document_id ? DB::table('deal_documents')->find($installment->receipt_document_id) : null;
        abort_unless($file, 404, 'Comprovante não disponível.');
        if (Storage::disk('local')->exists($file->storage_path)) return Storage::disk('local')->download($file->storage_path,$file->original_name);
        abort_unless($file->content_blob, 410, 'Este comprovante antigo não está mais disponível.');
        return response()->streamDownload(fn()=>print($file->content_blob),$file->original_name,['Content-Type'=>$file->mime_type ?: 'application/octet-stream']);
    }

    public function reconcileInstallment(Request $request, Installment $installment, DealEventService $events)
    {
        $data = $request->validate([
            'external_payment_id'=>['nullable','string','max:120'],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        abort_unless($installment->receipt_document_id, 422, 'A parcela precisa possuir comprovante.');
        abort_if($installment->status==='paid', 422, 'Esta parcela já está paga.');
        $before = $installment->toArray();
        DB::transaction(function () use ($installment,$data): void {
            $lockedInstallment = Installment::whereKey($installment->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedInstallment->status==='paid', 422, 'Esta parcela já está paga.');
            $deal = $lockedInstallment->deal()->lockForUpdate()->firstOrFail();
            $lockedInstallment->update(['status'=>'paid','paid_at'=>now(),'external_payment_id'=>$data['external_payment_id'] ?? null]);
            $wallet = WalletAccount::firstOrCreate(['user_id'=>$deal->seller_id],['provider'=>'mock','status'=>'active','available_balance'=>0]);
            $wallet->transactions()->create([
                'deal_id'=>$deal->id,'installment_id'=>$lockedInstallment->id,'type'=>'admin_reconciliation',
                'direction'=>'credit','amount'=>$lockedInstallment->amount,'status'=>'posted',
                'external_id'=>$data['external_payment_id'] ?? ('admin-'.Str::uuid()),
                'description'=>$data['reason'],'occurred_at'=>now(),
            ]);
            $wallet->increment('available_balance',(float)$lockedInstallment->amount);
            if (!$deal->installments()->where('status','!=','paid')->exists()) $deal->update(['status'=>'paid_off','paid_off_at'=>now()]);
        });
        $deal = $installment->deal()->firstOrFail();
        $events->record($deal, $request->user()->id, 'admin_installment_reconciled', ['installment_id'=>$installment->id,'reason'=>$data['reason']]);
        $events->notify($deal, $deal->buyer_id, 'admin_installment_reconciled', 'Pagamento conciliado', 'A administração confirmou o pagamento da parcela '.$installment->number.'.', ['deal_id'=>$deal->id,'installment_id'=>$installment->id]);
        $events->notify($deal, $deal->seller_id, 'admin_installment_reconciled', 'Pagamento conciliado', 'A administração confirmou o recebimento da parcela '.$installment->number.'.', ['deal_id'=>$deal->id,'installment_id'=>$installment->id]);
        if ($deal->status==='paid_off') {
            $events->notify($deal, $deal->buyer_id, 'deal_rating_required', 'Avalie o vendedor', 'A negociação foi quitada. Faça sua avaliação em bigodinhos para gerar o termo de quitação.', ['deal_id'=>$deal->id]);
            $events->notify($deal, $deal->seller_id, 'deal_rating_required', 'Avalie o comprador', 'A negociação foi quitada. Faça sua avaliação em bigodinhos para gerar o termo de quitação.', ['deal_id'=>$deal->id]);
        }
        $this->audit($request, 'installment.reconciled', 'installment', $installment->id, $before, $installment->fresh()->toArray(), $data['reason']);
        return response()->json($installment->fresh());
    }

    public function adjustWallet(Request $request, WalletAccount $wallet)
    {
        $data = $request->validate([
            'direction'=>['required',Rule::in(['credit','debit'])],
            'amount'=>['required','numeric','min:0.01','max:1000000'],
            'reason'=>['required','string','min:5','max:500'],
        ]);
        $before = ['available_balance'=>$wallet->available_balance];
        DB::transaction(function () use ($wallet,$data): void {
            $lockedWallet = WalletAccount::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $amount = (float)$data['amount'];
            abort_if($data['direction']==='debit' && (float)$lockedWallet->available_balance < $amount, 422, 'O débito excede o saldo disponível.');
            $lockedWallet->transactions()->create([
                'type'=>'admin_adjustment','direction'=>$data['direction'],'amount'=>$amount,
                'status'=>'posted','external_id'=>'admin-'.Str::uuid(),
                'description'=>$data['reason'],'occurred_at'=>now(),
            ]);
            $data['direction']==='credit' ? $lockedWallet->increment('available_balance',$amount) : $lockedWallet->decrement('available_balance',$amount);
        });
        $this->audit($request, 'wallet.adjusted', 'wallet_account', $wallet->id, $before, ['available_balance'=>$wallet->fresh()->available_balance,'direction'=>$data['direction'],'amount'=>$data['amount']], $data['reason']);
        return response()->json($wallet->fresh()->load('user:id,name,email'));
    }

    public function changeWalletStatus(Request $request, WalletAccount $wallet)
    {
        $data = $request->validate(['status'=>['required',Rule::in(['active','suspended'])],'reason'=>['required','string','min:5','max:500']]);
        $before = ['status'=>$wallet->status];
        $wallet->update(['status'=>$data['status']]);
        $this->audit($request, 'wallet.status_changed', 'wallet_account', $wallet->id, $before, ['status'=>$data['status']], $data['reason']);
        return response()->json($wallet->fresh());
    }

    public function advertisers()
    {
        return DB::table('advertisers')->orderBy('name')->get();
    }

    public function createAdvertiser(Request $request)
    {
        $data = $request->validate(['name'=>['required','string','max:160'],'document'=>['nullable','string','max:30'],'contact_email'=>['nullable','email','max:255'],'active'=>['sometimes','boolean']]);
        $id = DB::table('advertisers')->insertGetId([...$data,'active'=>$data['active'] ?? true,'created_at'=>now(),'updated_at'=>now()]);
        $partner = (array)DB::table('advertisers')->find($id);
        $this->audit($request, 'advertiser.created', 'advertiser', $id, null, $partner);
        return response()->json($partner, 201);
    }

    public function updateAdvertiser(Request $request, int $advertiser)
    {
        $before = DB::table('advertisers')->find($advertiser);
        abort_unless($before, 404);
        $data = $request->validate(['name'=>['required','string','max:160'],'document'=>['nullable','string','max:30'],'contact_email'=>['nullable','email','max:255']]);
        DB::table('advertisers')->where('id',$advertiser)->update([...$data,'updated_at'=>now()]);
        $after = DB::table('advertisers')->find($advertiser);
        $this->audit($request, 'advertiser.updated', 'advertiser', $advertiser, (array)$before, (array)$after);
        return response()->json($after);
    }

    public function changeAdvertiserStatus(Request $request, int $advertiser)
    {
        $before = DB::table('advertisers')->find($advertiser);
        abort_unless($before, 404);
        $data = $request->validate(['active'=>['required','boolean'],'reason'=>['required','string','min:5','max:500']]);
        DB::table('advertisers')->where('id',$advertiser)->update(['active'=>$data['active'],'updated_at'=>now()]);
        if (!$data['active']) DB::table('campaigns')->where('advertiser_id',$advertiser)->update(['active'=>false,'updated_at'=>now()]);
        $after = DB::table('advertisers')->find($advertiser);
        $this->audit($request, 'advertiser.status_changed', 'advertiser', $advertiser, (array)$before, (array)$after, $data['reason']);
        return response()->json($after);
    }

    public function createCampaign(Request $request)
    {
        $data = $this->validateCampaign($request);
        $id = DB::table('campaigns')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);
        $campaign = (array)DB::table('campaigns')->find($id);
        $this->audit($request, 'campaign.created', 'campaign', $id, null, $campaign);
        return response()->json($campaign, 201);
    }

    public function updateCampaign(Request $request, int $campaign)
    {
        $before = DB::table('campaigns')->find($campaign);
        abort_unless($before, 404);
        $data = $this->validateCampaign($request, true);
        DB::table('campaigns')->where('id',$campaign)->update([...$data,'updated_at'=>now()]);
        $after = DB::table('campaigns')->find($campaign);
        $this->audit($request, 'campaign.updated', 'campaign', $campaign, (array)$before, (array)$after);
        return response()->json($after);
    }

    public function changeCampaignStatus(Request $request, int $campaign)
    {
        $before = DB::table('campaigns')->find($campaign);
        abort_unless($before, 404);
        $data = $request->validate(['active'=>['required','boolean'],'reason'=>['required','string','min:5','max:500']]);
        DB::table('campaigns')->where('id',$campaign)->update(['active'=>$data['active'],'updated_at'=>now()]);
        $after = DB::table('campaigns')->find($campaign);
        $this->audit($request, 'campaign.status_changed', 'campaign', $campaign, (array)$before, (array)$after, $data['reason']);
        return response()->json($after);
    }

    public function auditLogs()
    {
        return AdminAuditLog::with('admin:id,name,email')->latest()->paginate(100);
    }

    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'slug'=>['required','string','max:60','regex:/^[a-z0-9-]+$/',Rule::unique('plans','slug')->ignore($plan?->id)],
            'name'=>['required','string','max:120'],
            'monthly_price'=>['required','numeric','min:0','max:99999.99'],
            'active_listing_limit'=>['required','integer','min:0','max:10000'],
            'direct_deal_limit'=>['required','integer','min:0','max:10000'],
            'active'=>['sometimes','boolean'],
        ]);
        if ($plan) {
            abort_unless($data['slug']===$plan->slug, 422, 'O identificador de um plano existente não pode ser alterado.');
            unset($data['active']);
        }
        return $data;
    }

    private function validateListing(Request $request, ?Listing $listing = null): array
    {
        $data = $request->validate([
            'seller_id'=>[$listing ? 'sometimes' : 'required','integer','exists:users,id'],
            'category'=>['required','string','max:60'],
            'title'=>['required','string','max:180'],
            'description'=>['required','string','max:5000'],
            'cover_image'=>['nullable','string','max:900000'],
            'price'=>['required','numeric','min:0.01'],
            'accepts_trade'=>['sometimes','boolean'],
            'status'=>['sometimes',Rule::in(['draft','published','archived'])],
        ]);
        if (!empty($data['cover_image'])) abort_unless((bool)preg_match('#^data:image/(jpeg|png|webp);base64,#',$data['cover_image']), 422, 'A imagem deve ser JPG, PNG ou WebP.');
        if ($listing) unset($data['seller_id'],$data['status']);
        return $data;
    }

    private function validateCampaign(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'advertiser_id'=>['required','integer','exists:advertisers,id'],
            'name'=>['required','string','max:160'],
            'headline'=>['required','string','max:255'],
            'cta'=>['required','string','max:80'],
            'target_url'=>['nullable','url','max:1000'],
            'placement'=>['required',Rule::in(['home'])],
            'media_path'=>[$updating ? 'nullable' : 'required','nullable','string','max:900000'],
            'priority'=>['required','integer','min:0','max:10000'],
            'starts_at'=>['required','date'],
            'ends_at'=>['required','date','after:starts_at'],
            'active'=>['sometimes','boolean'],
        ]);
        if (!empty($data['media_path'])) abort_unless((bool)preg_match('#^data:image/(jpeg|png|webp);base64,#',$data['media_path']) || str_starts_with($data['media_path'],'https://'), 422, 'A mídia deve ser uma imagem JPG, PNG ou WebP, ou uma URL HTTPS.');
        if (!empty($data['target_url'])) abort_unless(str_starts_with($data['target_url'],'https://'), 422, 'O link da campanha deve usar HTTPS.');
        if ($updating) unset($data['active']);
        return $data;
    }

    private function assertNegotiator(User $user): void
    {
        abort_if($user->is_admin, 403, 'Contas administrativas não podem ser alteradas por este módulo.');
    }

    private function audit(Request $request, string $action, string $entityType, int|string $entityId, mixed $before = null, mixed $after = null, ?string $reason = null): void
    {
        AdminAuditLog::create([
            'admin_user_id'=>$request->user()?->id,
            'action'=>$action,'entity_type'=>$entityType,'entity_id'=>(string)$entityId,
            'before_data'=>$this->sanitizeAuditData($before),'after_data'=>$this->sanitizeAuditData($after),'reason'=>$reason,
            'ip_hash'=>$request->ip() ? hash('sha256',$request->ip()) : null,
            'user_agent_hash'=>$request->userAgent() ? hash('sha256',$request->userAgent()) : null,
        ]);
    }

    private function sanitizeAuditData(mixed $value): mixed
    {
        if (is_object($value)) $value = (array)$value;
        if (!is_array($value)) return $value;
        foreach ($value as $key=>$item) {
            if (in_array((string)$key,['password','remember_token','cover_image','media_path','content_blob'],true) && filled($item)) {
                $value[$key] = '[conteúdo protegido]';
            } else {
                $value[$key] = $this->sanitizeAuditData($item);
            }
        }
        return $value;
    }
}
