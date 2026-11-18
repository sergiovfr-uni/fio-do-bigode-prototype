<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ListingCategoryService {
    public static function assertAvailable(string $name, ?string $current = null): void {
        if ($current === $name) return;
        abort_unless(DB::table('listing_categories')->where('name',$name)->where('active',true)->exists(),422,'Escolha uma categoria ativa dos classificados.');
    }
}
