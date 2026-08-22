<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Support\Facades\Http;

class SignatureValidationService
{
    public function validate(string $pdf, Deal $deal): array
    {
        $url = env('SIGNATURE_VALIDATOR_URL');
        if (!$url) {
            return [
                'status'=>'pending',
                'reason'=>'Validador criptográfico ainda não configurado.',
                'signers'=>[],
                'report'=>null,
            ];
        }

        $request = Http::timeout(45)->acceptJson();
        if ($token = env('SIGNATURE_VALIDATOR_TOKEN')) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request
                ->attach('file', $pdf, 'documento-assinado.pdf', ['Content-Type'=>'application/pdf'])
                ->post($url)
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            report($e);
            return ['status'=>'pending','reason'=>'Validação temporariamente indisponível.','signers'=>[],'report'=>null];
        }

        $signatures = collect($response['signatures'] ?? [])
            ->filter(fn($signature) => ($signature['status'] ?? null) === 'valid')
            ->map(fn($signature) => preg_replace('/\D+/', '', (string)($signature['identifier'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $deal->loadMissing(['seller:id,cpf','buyer:id,cpf','witnesses:id,deal_id,cpf']);
        $required = collect([$deal->seller?->cpf, $deal->buyer?->cpf])
            ->concat($deal->witnesses->map(fn($witness) => $witness->getRawOriginal('cpf')))
            ->map(fn($identifier) => preg_replace('/\D+/', '', (string)$identifier))
            ->filter()
            ->unique()
            ->values();

        $requiredCount = $deal->witnesses->count() === 2 ? 4 : 2;
        $valid = ($response['valid'] ?? false) === true
            && ($response['document_intact'] ?? false) === true
            && $required->count() === $requiredCount
            && $required->every(fn($identifier) => $signatures->contains($identifier));

        return [
            'status'=>$valid ? 'valid' : 'rejected',
            'reason'=>$valid ? null : ($requiredCount === 4
                ? 'O PDF precisa estar íntegro e conter quatro assinaturas válidas: comprador, vendedor e duas testemunhas.'
                : 'O PDF precisa estar íntegro e conter as assinaturas válidas do comprador e do vendedor.'),
            'signers'=>$signatures->all(),
            'report'=>$response,
        ];
    }
}
