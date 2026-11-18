<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('listings', function (Blueprint $t) { $t->unsignedBigInteger('seller_id')->nullable()->change(); });
        Schema::create('listing_categories', function (Blueprint $t) { $t->id(); $t->string('name',60)->unique(); $t->boolean('active')->default(true); $t->timestamps(); });
        $names = DB::table('listings')->distinct()->pluck('category')->merge(['Veículo','Eletrônico','Outros'])->unique();
        foreach ($names as $name) DB::table('listing_categories')->insertOrIgnore(['name'=>$name,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        // Preserve ownerless announcements: restoring NOT NULL requires assigning their sellers first.
        Schema::dropIfExists('listing_categories');
    }
};
