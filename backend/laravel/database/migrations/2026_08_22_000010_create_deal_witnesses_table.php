<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');
            $table->string('name', 160);
            $table->string('cpf', 11);
            $table->string('email');
            $table->timestamps();
            $table->unique(['deal_id', 'cpf']);
            $table->unique(['deal_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_witnesses');
    }
};
