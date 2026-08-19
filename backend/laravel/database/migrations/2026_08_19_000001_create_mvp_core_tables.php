<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('users', function(Blueprint $t){$t->id();$t->string('name',160);$t->string('cpf',11)->unique();$t->string('email')->unique();$t->string('phone',20);$t->string('password');$t->string('kyc_status',20)->default('pending');$t->unsignedTinyInteger('risk_score')->default(0);$t->unsignedTinyInteger('reputation_score')->default(0);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
  Schema::create('plans', function(Blueprint $t){$t->id();$t->string('slug')->unique();$t->string('name');$t->decimal('monthly_price',10,2)->default(0);$t->unsignedInteger('active_listing_limit')->default(1);$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('subscriptions', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('plan_id')->constrained();$t->string('status',20)->default('trial');$t->timestamp('trial_ends_at')->nullable();$t->timestamp('current_period_ends_at')->nullable();$t->string('gateway')->nullable();$t->string('external_id')->nullable();$t->timestamps();});
  Schema::create('listings', function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->foreignId('seller_id')->constrained('users');$t->string('category',60);$t->string('title',180);$t->text('description');$t->decimal('price',14,2);$t->string('status',20)->default('draft');$t->timestamp('published_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
  Schema::create('deals', function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->foreignId('seller_id')->constrained('users');$t->foreignId('buyer_id')->constrained('users');$t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();$t->string('origin',20)->default('direct');$t->string('status',30)->default('proposal_sent');$t->decimal('total_amount',14,2);$t->decimal('down_payment',14,2)->default(0);$t->unsignedSmallInteger('installments')->default(1);$t->decimal('monthly_interest',7,4)->default(0);$t->timestamp('terms_locked_at')->nullable();$t->timestamps();});
  Schema::create('deal_offers', function(Blueprint $t){$t->id();$t->foreignId('deal_id')->constrained()->cascadeOnDelete();$t->foreignId('created_by')->constrained('users');$t->string('type',20);$t->decimal('total_amount',14,2);$t->decimal('down_payment',14,2)->default(0);$t->unsignedSmallInteger('installments');$t->decimal('monthly_interest',7,4)->default(0);$t->string('status',20)->default('pending');$t->timestamp('accepted_at')->nullable();$t->timestamps();});
  Schema::create('kyc_checks', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('provider',40);$t->string('check_type',40);$t->string('status',20);$t->string('external_id')->nullable();$t->json('result')->nullable();$t->timestamp('verified_at')->nullable();$t->timestamps();});
  Schema::create('consents', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('type',50);$t->string('version',30);$t->timestamp('accepted_at');$t->string('ip_hash',64)->nullable();$t->string('user_agent_hash',64)->nullable();$t->timestamps();$t->unique(['user_id','type','version']);});
 }
 public function down(): void {foreach(['consents','kyc_checks','deal_offers','deals','listings','subscriptions','plans','users'] as $table) Schema::dropIfExists($table);}
};
