<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->string('entity_id', 80);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['admin_user_id', 'created_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->longText('media_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('media_path')->nullable()->change();
        });
    }
};
