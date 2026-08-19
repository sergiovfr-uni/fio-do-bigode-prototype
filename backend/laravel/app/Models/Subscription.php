<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['user_id','plan_id','status','trial_ends_at','current_period_ends_at','gateway','external_id'];
    protected $casts = ['trial_ends_at'=>'datetime','current_period_ends_at'=>'datetime'];
    public function user(){ return $this->belongsTo(User::class); }
    public function plan(){ return $this->belongsTo(Plan::class); }
}
