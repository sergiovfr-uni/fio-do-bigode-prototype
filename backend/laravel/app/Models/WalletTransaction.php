<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WalletTransaction extends Model {
 protected $fillable=['wallet_account_id','deal_id','installment_id','type','direction','amount','status','external_id','description','occurred_at'];
 protected $casts=['amount'=>'decimal:2','occurred_at'=>'datetime'];
 public function wallet(){return $this->belongsTo(WalletAccount::class,'wallet_account_id');}
}
