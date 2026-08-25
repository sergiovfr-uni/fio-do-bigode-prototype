<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->date('first_due_date')->nullable()->after('monthly_interest');
            $table->json('terms_snapshot')->nullable()->after('terms_locked_at');
            $table->timestamp('formalized_at')->nullable()->after('terms_snapshot');
            $table->timestamp('paid_off_at')->nullable()->after('formalized_at');
        });

        Schema::table('deal_offers', function (Blueprint $table) {
            $table->date('first_due_date')->nullable()->after('monthly_interest');
        });

        Schema::table('deal_invitations', function (Blueprint $table) {
            $table->date('first_due_date')->nullable()->after('monthly_interest');
        });
    }

    public function down(): void
    {
        Schema::table('deal_invitations', fn (Blueprint $table) => $table->dropColumn('first_due_date'));
        Schema::table('deal_offers', fn (Blueprint $table) => $table->dropColumn('first_due_date'));
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['first_due_date', 'terms_snapshot', 'formalized_at', 'paid_off_at']);
        });
    }
};
