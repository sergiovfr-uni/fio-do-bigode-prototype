<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Installment extends Model {
 protected $fillable=['deal_id','number','due_date','amount','status','paid_at','external_payment_id'];
 protected $casts=['due_date'=>'date','paid_at'=>'datetime','amount'=>'decimal:2'];
 public function deal(){return $this->belongsTo(Deal::class);} }
