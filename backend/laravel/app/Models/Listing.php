<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Listing extends Model
{
    use HasUuids;
    protected $fillable = ['public_id','seller_id','category','title','description','price','status','published_at','expires_at'];
    protected $casts = ['price'=>'decimal:2','published_at'=>'datetime','expires_at'=>'datetime'];
    public function uniqueIds(): array { return ['public_id']; }
    public function seller(){ return $this->belongsTo(User::class,'seller_id'); }
    public function deals(){ return $this->hasMany(Deal::class); }
}
