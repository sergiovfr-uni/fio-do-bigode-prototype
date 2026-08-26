<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('didit_kyc_sessions')) {
            Schema::create('didit_kyc_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->uuid('session_id')->unique();
                $table->uuid('workflow_id')->nullable();
                $table->string('environment', 20)->default('sandbox');
                $table->string('status', 40)->default('Not Started');
                $table->text('verification_url')->nullable();
                $table->json('decision')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('didit_webhook_events')) {
            Schema::create('didit_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('event_type', 60);
                $table->uuid('session_id')->nullable();
                $table->json('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('didit_webhook_events');
        Schema::dropIfExists('didit_kyc_sessions');
    }
};
