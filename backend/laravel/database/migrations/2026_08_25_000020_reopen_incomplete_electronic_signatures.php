<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // A versão anterior marcava a evidência como utilizada antes de terminar
        // a geração do PDF. Reabre somente tentativas que não produziram documento.
        DB::table('deal_electronic_signatures')
            ->whereNotNull('signed_at')
            ->whereNull('signed_document_sha256')
            ->update([
                'verified_at' => null,
                'signed_at' => null,
                'signature_image_path' => null,
                'signature_image_sha256' => null,
                'consent_version' => null,
                'consent_text_sha256' => null,
                'ip_address' => null,
                'user_agent' => null,
                'evidence' => null,
                'evidence_seal' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // A reabertura de uma tentativa incompleta não deve ser revertida.
    }
};
