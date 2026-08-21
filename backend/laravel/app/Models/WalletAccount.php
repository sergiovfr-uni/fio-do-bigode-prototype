<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WalletAccount extends Model {
 protected $fillable=['user_id','provider','external_id','status','available_balance'];
 protected $casts=['available_balance'=>'decimal:2'];
 public function user(){return $this->belongsTo(User::class);} public function transactions(){return $this->hasMany(WalletTransaction::class);} }
