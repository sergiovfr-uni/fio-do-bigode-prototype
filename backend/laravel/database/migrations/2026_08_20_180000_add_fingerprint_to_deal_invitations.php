<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_invitations', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('deal_invitations', function (Blueprint $table) {
            $table->dropUnique(['fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
