<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('installments', function(Blueprint $t){
   $t->id();$t->foreignId('deal_id')->constrained()->cascadeOnDelete();$t->unsignedSmallInteger('number');$t->date('due_date');$t->decimal('amount',14,2);$t->string('status',20)->default('pending');$t->timestamp('paid_at')->nullable();$t->string('external_payment_id')->nullable();$t->timestamps();$t->unique(['deal_id','number']);
  });
  Schema::create('wallet_accounts', function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('provider',30)->default('mock');$t->string('external_id')->nullable();$t->string('status',20)->default('active');$t->decimal('available_balance',14,2)->default(0);$t->timestamps();
  });
  Schema::create('wallet_transactions', function(Blueprint $t){
   $t->id();$t->foreignId('wallet_account_id')->constrained()->cascadeOnDelete();$t->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();$t->string('type',20);$t->string('direction',10);$t->decimal('amount',14,2);$t->string('status',20)->default('posted');$t->string('external_id')->nullable();$t->string('description',255)->nullable();$t->timestamp('occurred_at');$t->timestamps();
  });
 }
 public function down(): void {Schema::dropIfExists('wallet_transactions');Schema::dropIfExists('wallet_accounts');Schema::dropIfExists('installments');}
};
