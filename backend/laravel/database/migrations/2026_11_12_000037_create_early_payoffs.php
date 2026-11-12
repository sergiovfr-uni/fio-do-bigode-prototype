<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('early_payoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->unsignedBigInteger('balance_cents');
            $table->unsignedBigInteger('proposed_cents');
            $table->unsignedBigInteger('agreed_cents')->nullable();
            $table->json('schedule');
            $table->foreignId('receipt_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->timestamps();
            $table->index(['deal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('early_payoffs');
    }
};
