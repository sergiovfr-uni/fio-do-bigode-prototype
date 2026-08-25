<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        $counts = [
            'deals'=>DB::table('deals')->count(),
            'invitations'=>DB::table('deal_invitations')->count(),
            'documents'=>DB::table('deal_documents')->count(),
            'installments'=>DB::table('installments')->count(),
        ];

        DB::transaction(function (): void {
            DB::table('wallet_transactions')
                ->whereNotNull('deal_id')
                ->orWhereNotNull('installment_id')
                ->delete();

            DB::table('deals')->update([
                'seller_signed_document_id'=>null,
                'fully_signed_document_id'=>null,
                'entry_receipt_document_id'=>null,
            ]);

            // As tabelas de propostas, assinaturas, documentos, parcelas,
            // testemunhas, eventos e notificações vinculadas usam cascade.
            DB::table('deals')->delete();
            DB::table('deal_invitations')->delete();
            DB::table('notifications')
                ->whereNull('deal_id')
                ->whereIn('type', ['deal_invitation_received', 'witness_invitation_received'])
                ->delete();
        });

        Log::warning('Homologação: fluxo legado removido antes da assinatura eletrônica integrada.', $counts);
    }

    public function down(): void
    {
        // Limpeza de homologação autorizada e intencionalmente irreversível.
    }
};
