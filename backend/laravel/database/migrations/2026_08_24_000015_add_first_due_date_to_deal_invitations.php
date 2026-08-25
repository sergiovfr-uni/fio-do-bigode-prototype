<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('deal_invitations', 'first_due_date')) {
            Schema::table('deal_invitations', function (Blueprint $table) {
                $table->date('first_due_date')->nullable()->after('monthly_interest');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deal_invitations', 'first_due_date')) {
            Schema::table('deal_invitations', fn (Blueprint $table) => $table->dropColumn('first_due_date'));
        }
    }
};
