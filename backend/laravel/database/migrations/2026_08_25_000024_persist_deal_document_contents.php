<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('deal_documents', 'content_blob')) {
            Schema::table('deal_documents', fn (Blueprint $table) => $table->longBlob('content_blob')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deal_documents', 'content_blob')) {
            Schema::table('deal_documents', fn (Blueprint $table) => $table->dropColumn('content_blob'));
        }
    }
};
