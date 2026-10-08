<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('partner_applications', function (Blueprint $t) {
            $t->id(); $t->string('name',160); $t->string('email',255)->unique();
            $t->string('phone',20); $t->string('platform',30); $t->string('profile_url',1000);
            $t->string('audience_label',160)->nullable(); $t->text('message')->nullable();
            $t->timestamp('consented_at'); $t->string('privacy_version',30)->default('2026-10-08');
            $t->string('status',20)->default('new')->index(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partner_applications'); }
};
