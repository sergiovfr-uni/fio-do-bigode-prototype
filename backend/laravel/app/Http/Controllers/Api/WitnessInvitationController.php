<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealWitness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WitnessInvitationController extends Controller
{
    public function show(string $code)
    {
        $witness = $this->resolve($code);
        $deal = $witness->deal()->with(['seller:id,name', 'buyer:id,name', 'listing:id,title'])->firstOrFail();

        if (!$witness->viewed_at) {
            $witness->update(['viewed_at' => now(), 'invitation_status' => 'viewed']);
        }

        return response()->json([
            'code' => $witness->invitation_code,
            'deal_code' => $this->dealCode($deal->public_id),
            'witness_name' => $witness->name,
            'title' => $deal->title ?? $deal->listing?->title ?? 'Negociação',
            'total_amount' => $deal->total_amount,
            'seller_name' => $deal->seller?->name,
            'buyer_name' => $deal->buyer?->name,
            'status' => $witness->invitation_status,
            'expires_at' => $witness->invitation_expires_at,
            'download_url' => url('/api/v1/witness-invitations/'.$witness->invitation_code.'/document'),
        ]);
    }

    public function download(string $code)
    {
        $witness = $this->resolve($code);
        $document = DB::table('deal_documents')
            ->where('deal_id', $witness->deal_id)
            ->where('type', 'unsigned_contract')
            ->latest()
            ->first();

        abort_unless($document && Storage::disk('local')->exists($document->storage_path), 404, 'Dossiê ainda não disponível.');

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_name,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function resolve(string $code): DealWitness
    {
        $witness = DealWitness::where('invitation_code', strtoupper($code))->first();
        abort_unless(
            $witness && (!$witness->invitation_expires_at || now()->lte($witness->invitation_expires_at)),
            404,
            'Convite de testemunha inválido ou expirado.'
        );
        return $witness;
    }

    private function dealCode(string $publicId): string
    {
        return strtoupper(substr(str_replace('-', '', $publicId), 0, 8));
    }
}
