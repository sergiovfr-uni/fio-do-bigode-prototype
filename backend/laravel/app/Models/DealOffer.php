<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealOffer extends Model
{
    protected $fillable = ['deal_id','created_by','type','total_amount','down_payment','installments','monthly_interest','first_due_date','status','accepted_at'];
    protected $casts = ['total_amount'=>'decimal:2','down_payment'=>'decimal:2','monthly_interest'=>'decimal:4','first_due_date'=>'date:Y-m-d','accepted_at'=>'datetime'];
    public function deal(){ return $this->belongsTo(Deal::class); }
    public function author(){ return $this->belongsTo(User::class,'created_by'); }
}
