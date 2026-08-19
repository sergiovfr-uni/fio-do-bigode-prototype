<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('password_reset_tokens', function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
  Schema::create('deal_documents', function(Blueprint $t){$t->id();$t->foreignId('deal_id')->constrained()->cascadeOnDelete();$t->foreignId('uploaded_by')->constrained('users');$t->string('type',40);$t->string('storage_path');$t->string('original_name');$t->string('mime_type',100)->nullable();$t->string('sha256',64);$t->boolean('signed')->default(false);$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('deal_documents');Schema::dropIfExists('password_reset_tokens');}
};
