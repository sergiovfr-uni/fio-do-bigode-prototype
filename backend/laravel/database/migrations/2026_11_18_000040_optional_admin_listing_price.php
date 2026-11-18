<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('listings',function(Blueprint $table){$table->decimal('price',14,2)->nullable()->change();});
    }
    public function down(): void {
        // Preserve genuine absent prices rather than replacing them with a fictional zero.
    }
};
