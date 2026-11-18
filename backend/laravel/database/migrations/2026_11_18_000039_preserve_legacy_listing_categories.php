<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        // Preserve options offered by older app versions without reactivating retired categories.
        foreach (['Imóvel','Serviço'] as $name) {
            DB::table('listing_categories')->insertOrIgnore(['name'=>$name,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void {
        // Categories may have acquired listings; preserve them on rollback.
    }
};
