<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['slug','name','monthly_price','active_listing_limit','active'];
    protected $casts = ['monthly_price'=>'decimal:2','active_listing_limit'=>'integer','active'=>'boolean'];
    public function subscriptions(){ return $this->hasMany(Subscription::class); }
}
