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
        ];

        DB::transaction(function (): void {
            DB::table('wallet_transactions')
                ->whereNotNull('deal_id')
                ->orWhereNotNull('installment_id')
                ->delete();

            DB::table('deals')->update([
                'seller_signed_document_id' => null,
                'fully_signed_document_id' => null,
                'entry_receipt_document_id' => null,
            ]);

            // Os registros dependentes da negociação usam exclusão em cascata.
            DB::table('deals')->delete();
            DB::table('deal_invitations')->delete();
            DB::table('notifications')
                ->whereNull('deal_id')
                ->whereIn('type', ['deal_invitation_received', 'witness_invitation_received'])
                ->delete();
        });

        Log::warning('Homologação: negociações de teste removidas para nova validação.', $counts);
    }

    public function down(): void
    {
        // Limpeza de homologação autorizada e intencionalmente irreversível.
    }
};
