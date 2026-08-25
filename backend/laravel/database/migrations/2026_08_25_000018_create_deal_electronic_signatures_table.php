<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_electronic_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('challenge_id')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('source_document_sha256', 64);
            $table->string('signature_image_path')->nullable();
            $table->string('signature_image_sha256', 64)->nullable();
            $table->string('signed_document_sha256', 64)->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->string('consent_text_sha256', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('evidence')->nullable();
            $table->string('evidence_seal', 64)->nullable();
            $table->timestamps();
            $table->unique(['deal_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_electronic_signatures');
    }
};
