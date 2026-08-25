<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->foreignId('receipt_document_id')->nullable()->after('external_payment_id')
                ->constrained('deal_documents')->nullOnDelete();
            $table->timestamp('receipt_uploaded_at')->nullable()->after('receipt_document_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('reminder_key', 120)->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropUnique(['reminder_key']));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('reminder_key'));
        Schema::table('installments', function (Blueprint $table) {
            $table->dropForeign(['receipt_document_id']);
            $table->dropColumn(['receipt_document_id', 'receipt_uploaded_at']);
        });
    }
};
