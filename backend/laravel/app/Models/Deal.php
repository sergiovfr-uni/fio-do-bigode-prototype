<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Deal extends Model
{
    use HasUuids;
    protected $fillable = ['public_id','seller_id','buyer_id','listing_id','origin','title','description','status','total_amount','down_payment','installments','monthly_interest','terms_locked_at'];
    protected $casts = ['total_amount'=>'decimal:2','down_payment'=>'decimal:2','monthly_interest'=>'decimal:4','terms_locked_at'=>'datetime'];
    public function uniqueIds(): array { return ['public_id']; }
    public function seller(){ return $this->belongsTo(User::class,'seller_id'); }
    public function buyer(){ return $this->belongsTo(User::class,'buyer_id'); }
    public function listing(){ return $this->belongsTo(Listing::class); }
    public function offers(){ return $this->hasMany(DealOffer::class); }
    public function installments(){ return $this->hasMany(Installment::class)->orderBy('number'); }
    public function witnesses(){ return $this->hasMany(DealWitness::class)->orderBy('id'); }
}
