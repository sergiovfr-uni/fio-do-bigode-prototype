<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('installments', 'receipt_document_id')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->foreignId('receipt_document_id')->nullable()
                    ->constrained('deal_documents')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('installments', 'receipt_uploaded_at')) {
            Schema::table('installments', fn (Blueprint $table) => $table->timestamp('receipt_uploaded_at')->nullable());
        }
        if (!Schema::hasColumn('notifications', 'reminder_key')) {
            Schema::table('notifications', fn (Blueprint $table) => $table->string('reminder_key', 120)->nullable()->unique());
        }
    }

    public function down(): void
    {
        // Migração de reparo; não remove dados ou colunas existentes.
    }
};
