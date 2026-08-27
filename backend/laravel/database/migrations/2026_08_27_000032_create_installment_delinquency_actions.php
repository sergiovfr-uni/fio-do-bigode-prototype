<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_delinquency_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('type', 40);
            $table->string('status', 24)->default('completed');
            $table->json('payload')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('deal_documents')->nullOnDelete();
            $table->timestamps();
            $table->index(['installment_id', 'type', 'status'], 'installment_delinquency_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_delinquency_actions');
    }
};
