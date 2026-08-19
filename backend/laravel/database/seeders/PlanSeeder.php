<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
 public function run(): void
 {
  DB::table('plans')->upsert([
   ['slug'=>'trial','name'=>'Free Trial','monthly_price'=>0,'active_listing_limit'=>1,'active'=>1],
   ['slug'=>'bronze','name'=>'Bronze','monthly_price'=>9.90,'active_listing_limit'=>3,'active'=>1],
   ['slug'=>'prata','name'=>'Prata','monthly_price'=>19.90,'active_listing_limit'=>10,'active'=>1],
   ['slug'=>'ouro','name'=>'Ouro','monthly_price'=>39.90,'active_listing_limit'=>30,'active'=>1],
  ], ['slug'], ['name','monthly_price','active_listing_limit','active']);
 }
}
