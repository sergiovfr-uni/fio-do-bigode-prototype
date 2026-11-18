<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
class LegalPartner extends Authenticatable {
 use HasApiTokens;
 protected $guarded=['id'];
 protected $hidden=['payment_reference','notes'];
 protected $casts=['plan_snapshot'=>'array','access_until'=>'datetime','activated_at'=>'datetime','payment_confirmed_at'=>'datetime','consented_at'=>'datetime'];
 public function plan(){return $this->belongsTo(Plan::class);}
 public function hasAccess(): bool {return $this->status==='active' && $this->access_until?->isFuture() && $this->plan?->active && $this->plan->audience==='legal_partner';}
}
