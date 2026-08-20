<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedInteger('direct_deal_limit')->default(1)->after('active_listing_limit');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('account_status', 20)->default('active')->after('reputation_score');
            $t->timestamp('deletion_requested_at')->nullable()->after('account_status');
        });

        Schema::table('deals', function (Blueprint $t) {
            $t->string('title', 180)->nullable()->after('origin');
            $t->text('description')->nullable()->after('title');
        });

        Schema::create('deal_invitations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();
            $t->foreignId('created_by')->constrained('users');
            $t->string('initiator_role', 10)->default('seller');
            $t->string('invitee_name', 160)->nullable();
            $t->string('invitee_email')->nullable();
            $t->string('invitee_phone', 20)->nullable();
            $t->string('title', 180);
            $t->text('description');
            $t->decimal('total_amount', 14, 2);
            $t->decimal('down_payment', 14, 2)->default(0);
            $t->unsignedSmallInteger('installments')->default(1);
            $t->decimal('monthly_interest', 7, 4)->default(0);
            $t->string('status', 20)->default('pending');
            $t->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('account_deletion_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('status', 20)->default('pending');
            $t->string('reason', 500)->nullable();
            $t->timestamp('requested_at');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('deal_invitations');
        Schema::table('deals', function (Blueprint $t) { $t->dropColumn(['title','description']); });
        Schema::table('users', function (Blueprint $t) { $t->dropColumn(['account_status','deletion_requested_at']); });
        Schema::table('plans', function (Blueprint $t) { $t->dropColumn('direct_deal_limit'); });
    }
};