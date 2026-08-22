<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_documents', function (Blueprint $table) {
            $table->string('validation_status', 30)->nullable()->after('signed');
            $table->string('signature_provider', 60)->nullable()->after('validation_status');
            $table->json('signer_identifiers')->nullable()->after('signature_provider');
            $table->json('validation_report')->nullable()->after('signer_identifiers');
            $table->timestamp('validated_at')->nullable()->after('validation_report');
        });
    }

    public function down(): void
    {
        Schema::table('deal_documents', function (Blueprint $table) {
            $table->dropColumn(['validation_status','signature_provider','signer_identifiers','validation_report','validated_at']);
        });
    }
};
