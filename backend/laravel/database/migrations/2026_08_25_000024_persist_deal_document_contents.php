<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('deal_documents', 'content_blob')) {
            DB::statement('ALTER TABLE deal_documents ADD content_blob LONGBLOB NULL AFTER storage_path');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deal_documents', 'content_blob')) {
            DB::statement('ALTER TABLE deal_documents DROP COLUMN content_blob');
        }
    }
};
