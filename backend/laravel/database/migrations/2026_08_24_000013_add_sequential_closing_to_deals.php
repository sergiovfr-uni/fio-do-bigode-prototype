<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('initiator_id')->nullable()->after('buyer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('seller_signed_document_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->foreignId('fully_signed_document_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->foreignId('entry_receipt_document_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->timestamp('entry_confirmed_at')->nullable();
        });

        DB::table('deals')->orderBy('id')->get()->each(function ($deal) {
            $initiator = DB::table('deal_offers')->where('deal_id', $deal->id)->oldest('id')->value('created_by');
            DB::table('deals')->where('id', $deal->id)->update(['initiator_id'=>$initiator ?: $deal->seller_id]);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initiator_id');
            $table->dropConstrainedForeignId('seller_signed_document_id');
            $table->dropConstrainedForeignId('fully_signed_document_id');
            $table->dropConstrainedForeignId('entry_receipt_document_id');
            $table->dropColumn('entry_confirmed_at');
        });
    }
};
