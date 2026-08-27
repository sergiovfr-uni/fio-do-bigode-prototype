<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('community_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('platform', 30);
            $table->string('profile_url', 1000);
            $table->longText('avatar_url')->nullable();
            $table->string('audience_label', 160)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_partners');
    }
};
