<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('admin_only')->default(false);
            $table->boolean('complimentary')->default(false);
        });
        DB::table('plans')->where('slug', 'trial')->update(['name'=>'Free Trial 60 dias', 'updated_at'=>now()]);
        DB::table('plans')->insertOrIgnore([
            'slug'=>'influenciador', 'name'=>'Influenciador — cortesia', 'monthly_price'=>0,
            'active_listing_limit'=>30, 'direct_deal_limit'=>30, 'active_deal_limit'=>30,
            'active'=>true, 'admin_only'=>true, 'complimentary'=>true,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }
    public function down(): void
    {
        // Mantém as assinaturas existentes e seus registros históricos.
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['admin_only', 'complimentary']));
    }
};
