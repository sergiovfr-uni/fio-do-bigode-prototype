<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('advertisers',function(Blueprint $t){$t->id();$t->string('name');$t->string('document')->nullable();$t->string('contact_email')->nullable();$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('campaigns',function(Blueprint $t){$t->id();$t->foreignId('advertiser_id')->constrained();$t->string('name');$t->string('headline');$t->string('cta')->default('Conhecer oferta');$t->string('target_url')->nullable();$t->string('placement')->default('home');$t->string('media_path')->nullable();$t->unsignedInteger('priority')->default(100);$t->timestamp('starts_at');$t->timestamp('ends_at');$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('ad_events',function(Blueprint $t){$t->id();$t->foreignId('campaign_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('type',20);$t->uuid('session_id')->nullable();$t->string('fingerprint_hash',64)->nullable();$t->timestamp('occurred_at');$t->timestamps();$t->index(['campaign_id','type','occurred_at']);});
 }
 public function down(): void {Schema::dropIfExists('ad_events');Schema::dropIfExists('campaigns');Schema::dropIfExists('advertisers');}
};