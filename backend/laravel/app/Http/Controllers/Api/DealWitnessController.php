<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DealWitnessController extends Controller
{
    public function index(Request $request, Deal $deal)
    {
        $this->authorizeParty($request, $deal);
        return response()->json($deal->witnesses()->get()->map(fn ($witness) => [
            'id' => $witness->id,
            'name' => $witness->name,
            'email' => $witness->email,
            'cpf_masked' => $witness->cpf_masked,
            'invitation_code' => $witness->invitation_code,
            'invitation_status' => $witness->invitation_status,
            'invite_url' => $witness->invite_url,
        ]));
    }

    public function store(Request $request, Deal $deal, ContractService $contracts, DealEventService $events)
    {
        $this->authorizeParty($request, $deal);
        abort_unless((int)$request->user()->id === (int)$deal->seller_id, 403, 'Somente o vendedor pode definir as testemunhas.');
        abort_unless($deal->status === 'witnesses_pending', 422, 'As testemunhas só podem ser definidas após o aceite e antes da geração do dossiê.');
        $existing = $deal->witnesses()->get();
        abort_if($existing->count() >= 2, 422, 'As duas testemunhas desta negociação já foram definidas.');

        $data = $request->validate([
            'witnesses' => ['required', 'array', 'min:1', 'max:2'],
            'witnesses.*.name' => ['required', 'string', 'min:3', 'max:160'],
            'witnesses.*.cpf' => ['required', 'string'],
            'witnesses.*.email' => ['required', 'email', 'max:255'],
        ]);

        $witnesses = collect($data['witnesses'])->map(function ($witness) {
            $witness['cpf'] = preg_replace('/\D+/', '', $witness['cpf']);
            $witness['email'] = mb_strtolower(trim($witness['email']));
            abort_unless(strlen($witness['cpf']) === 11, 422, 'Informe um CPF válido para cada testemunha.');
            return $witness;
        });

        abort_if($existing->count() + $witnesses->count() > 2, 422, 'A negociação aceita no máximo duas testemunhas.');
        abort_unless($witnesses->pluck('cpf')->unique()->count() === $witnesses->count(), 422, 'As testemunhas precisam ter CPFs diferentes.');
        abort_unless($witnesses->pluck('email')->unique()->count() === $witnesses->count(), 422, 'As testemunhas precisam ter e-mails diferentes.');
        abort_if($witnesses->pluck('cpf')->intersect($existing->map(fn ($w) => $w->getRawOriginal('cpf')))->isNotEmpty(), 422, 'Esta testemunha já foi cadastrada.');
        abort_if($witnesses->pluck('email')->intersect($existing->pluck('email'))->isNotEmpty(), 422, 'Este e-mail já pertence a uma testemunha cadastrada.');

        $deal->loadMissing(['seller:id,cpf', 'buyer:id,cpf']);
        $partyCpfs = collect([$deal->seller?->cpf, $deal->buyer?->cpf])->map(fn ($cpf) => preg_replace('/\D+/', '', (string) $cpf));
        abort_if($witnesses->pluck('cpf')->intersect($partyCpfs)->isNotEmpty(), 422, 'Comprador e vendedor não podem atuar como testemunhas da própria negociação.');

        $result = DB::transaction(function () use ($request, $deal, $witnesses, $contracts) {
            $createdIds = [];
            foreach ($witnesses as $witness) {
                $created = $deal->witnesses()->create($witness + [
                    'registered_by' => $request->user()->id,
                    'invitation_code' => $this->newCode(),
                    'invitation_status' => 'pending',
                    'invitation_expires_at' => now()->addDays(30),
                ]);
                $createdIds[] = $created->id;
            }
            if ($deal->witnesses()->count() === 2) {
                $generated = $contracts->generate($deal->fresh(), $request->user()->id);
                $deal->update(['status' => 'signature_pending']);
                return ['generated' => $generated, 'created_ids' => $createdIds];
            }
            return ['generated' => null, 'created_ids' => $createdIds];
        });
        $generated = $result['generated'];

        $total = $deal->witnesses()->count();
        $events->record($deal, $request->user()->id, 'witnesses_registered', ['added' => $witnesses->count(), 'total' => $total]);
        $events->notify($deal, $events->otherParty($deal, $request->user()->id), 'witness_added', 'Testemunha adicionada', $request->user()->name.' adicionou testemunha(s) à negociação. Total: '.$total.'/2.',['deal_id'=>$deal->id,'witness_count'=>$total]);
        if ($generated) {
            $events->record($deal, $request->user()->id, 'documents_generated', ['sha256' => $generated['sha256']]);
            $events->notify($deal, $events->otherParty($deal, $request->user()->id), 'documents_generated', 'Dossiê pronto para assinatura', 'As duas testemunhas foram definidas e o dossiê da negociação está disponível.',['deal_id'=>$deal->id]);
        }

        $deal->fresh(['seller:id,name', 'buyer:id,name', 'witnesses'])->witnesses
            ->whereIn('id', $result['created_ids'])->each(fn ($witness) => $this->sendInvitationEmail($witness, $deal));

        return response()->json($deal->fresh(['offers', 'witnesses']));
    }

    public function skip(Request $request, Deal $deal, ContractService $contracts, DealEventService $events)
    {
        $this->authorizeParty($request, $deal);
        abort_unless((int)$request->user()->id === (int)$deal->seller_id, 403, 'Somente o vendedor pode dispensar as testemunhas.');
        abort_unless($deal->status === 'witnesses_pending', 422, 'Esta escolha só pode ser feita após o aceite e antes da geração do dossiê.');
        abort_if($deal->witnesses()->exists(), 422, 'Já existem testemunhas cadastradas nesta negociação.');

        $generated = DB::transaction(function () use ($request, $deal, $contracts) {
            $generated = $contracts->generate($deal->fresh(), $request->user()->id);
            $deal->update(['status' => 'signature_pending']);
            return $generated;
        });

        $events->record($deal, $request->user()->id, 'witnesses_waived', [
            'basis' => 'CPC art. 784, § 4º',
        ]);
        $events->record($deal, $request->user()->id, 'documents_generated', ['sha256' => $generated['sha256']]);
        $events->notify($deal, $events->otherParty($deal, $request->user()->id), 'documents_generated', 'Dossiê pronto para assinatura', 'O dossiê sem testemunhas foi gerado e está disponível para assinatura das partes.',['deal_id'=>$deal->id]);

        return response()->json($deal->fresh(['offers', 'witnesses']));
    }

    private function authorizeParty(Request $request, Deal $deal): void
    {
        abort_unless(in_array($request->user()->id, [$deal->seller_id, $deal->buyer_id], true), 403);
    }

    private function newCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (DB::table('deal_witnesses')->where('invitation_code', $code)->exists());
        return $code;
    }

    private function sendInvitationEmail($witness, Deal $deal): void
    {
        if (!$witness->email || !env('RESEND_API_KEY')) return;

        $name = htmlspecialchars($witness->name, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($deal->title ?? $deal->listing?->title ?? 'Negociação', ENT_QUOTES, 'UTF-8');
        $dealCode = strtoupper(substr(str_replace('-', '', $deal->public_id), 0, 8));
        $inviteUrl = $witness->invite_url;

        try {
            Http::withToken(env('RESEND_API_KEY'))->acceptJson()->post('https://api.resend.com/emails', [
                'from' => 'Fio do Bigode <naoresponda@nofiodobigode.app.br>',
                'to' => [$witness->email],
                'subject' => 'Convite para testemunhar uma negociação',
                'html' => "<div style='font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#111'>
                    <div style='background:#111;padding:28px;text-align:center;color:#d3a42f'><h1>FIO DO BIGODE</h1></div>
                    <div style='padding:28px'><h2>{$name}, você foi indicado como testemunha.</h2>
                    <p>Comprador e vendedor convidaram você para consultar o dossiê de <b>{$title}</b>.</p>
                    <div style='border:1px solid #ddd;border-radius:14px;padding:18px;margin:22px 0'>
                    <b>Código da negociação: {$dealCode}</b><br>Código do convite: {$witness->invitation_code}</div>
                    <a href='{$inviteUrl}' style='display:block;background:#111;color:#fff;text-decoration:none;padding:16px;border-radius:12px;text-align:center;font-weight:bold'>Consultar dossiê</a>
                    <p style='font-size:12px;color:#777'>O acesso é restrito a este documento e expira em 30 dias. A assinatura será realizada externamente via Gov.br ou certificado ICP-Brasil.</p></div></div>",
            ])->throw();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
