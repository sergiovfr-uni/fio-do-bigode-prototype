<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealWitnessController extends Controller
{
    public function index(Request $request, Deal $deal)
    {
        $this->authorizeParty($request, $deal);
        return response()->json($deal->witnesses()->get()->map(fn ($witness) => [
            'id' => $witness->id,
            'name' => $witness->name,
            'email' => $witness->email,
            'cpf_masked' => '***.***.***-'.substr($witness->getRawOriginal('cpf'), -2),
        ]));
    }

    public function store(Request $request, Deal $deal, ContractService $contracts, DealEventService $events)
    {
        $this->authorizeParty($request, $deal);
        abort_unless($deal->status === 'witnesses_pending', 422, 'As testemunhas só podem ser definidas após o aceite e antes da geração do dossiê.');
        abort_if($deal->witnesses()->exists(), 422, 'As testemunhas desta negociação já foram definidas.');

        $data = $request->validate([
            'witnesses' => ['required', 'array', 'size:2'],
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

        abort_unless($witnesses->pluck('cpf')->unique()->count() === 2, 422, 'As duas testemunhas precisam ter CPFs diferentes.');
        abort_unless($witnesses->pluck('email')->unique()->count() === 2, 422, 'As duas testemunhas precisam ter e-mails diferentes.');

        $deal->loadMissing(['seller:id,cpf', 'buyer:id,cpf']);
        $partyCpfs = collect([$deal->seller?->cpf, $deal->buyer?->cpf])->map(fn ($cpf) => preg_replace('/\D+/', '', (string) $cpf));
        abort_if($witnesses->pluck('cpf')->intersect($partyCpfs)->isNotEmpty(), 422, 'Comprador e vendedor não podem atuar como testemunhas da própria negociação.');

        $generated = DB::transaction(function () use ($request, $deal, $witnesses, $contracts) {
            foreach ($witnesses as $witness) {
                $deal->witnesses()->create($witness + ['registered_by' => $request->user()->id]);
            }
            $generated = $contracts->generate($deal->fresh(), $request->user()->id);
            $deal->update(['status' => 'signature_pending']);
            return $generated;
        });

        $events->record($deal, $request->user()->id, 'witnesses_registered', ['count' => 2]);
        $events->record($deal, $request->user()->id, 'documents_generated', ['sha256' => $generated['sha256']]);
        $events->notify($deal, $events->otherParty($deal, $request->user()->id), 'documents_generated', 'Dossiê pronto para assinatura', 'As duas testemunhas foram definidas e o dossiê da negociação está disponível.',['deal_id'=>$deal->id]);

        return response()->json($deal->fresh(['offers', 'witnesses']));
    }

    public function skip(Request $request, Deal $deal, ContractService $contracts, DealEventService $events)
    {
        $this->authorizeParty($request, $deal);
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
}
