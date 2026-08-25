<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        $counts = [
            'deals' => DB::table('deals')->count(),
            'invitations' => DB::table('deal_invitations')->count(),
            'documents' => DB::table('deal_documents')->count(),
            'installments' => DB::table('installments')->count(),
            'notifications' => DB::table('notifications')->count(),
        ];

        DB::transaction(function (): void {
            // Remove lançamentos financeiros de homologação antes que as FKs
            // transformem suas referências em NULL.
            DB::table('wallet_transactions')
                ->whereNotNull('deal_id')
                ->orWhereNotNull('installment_id')
                ->delete();

            // Rompe as referências circulares deals <-> deal_documents.
            DB::table('deals')->update([
                'seller_signed_document_id' => null,
                'fully_signed_document_id' => null,
                'entry_receipt_document_id' => null,
            ]);

            // As relações dependentes possuem cascadeOnDelete: propostas,
            // parcelas, documentos, testemunhas, eventos e notificações.
            DB::table('deals')->delete();
            DB::table('deal_invitations')->delete();

            // Remove avisos de convites antigos que não possuem deal_id.
            DB::table('notifications')->delete();
        });

        Log::warning('Base de homologação: negociações removidas para novo ciclo de testes.', $counts);
    }

    public function down(): void
    {
        // Limpeza autorizada e irreversível: não recriar dados de homologação antigos.
    }
};
