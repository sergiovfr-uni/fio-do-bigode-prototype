<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Installment extends Model {
 protected $fillable=['deal_id','number','due_date','amount','status','paid_at','external_payment_id','receipt_document_id','receipt_uploaded_at'];
 protected $casts=['due_date'=>'date:Y-m-d','paid_at'=>'datetime','receipt_uploaded_at'=>'datetime','amount'=>'decimal:2'];
 public function deal(){return $this->belongsTo(Deal::class);}
 public function delinquencyActions(){return $this->hasMany(InstallmentDelinquencyAction::class)->orderBy('id');}
}
