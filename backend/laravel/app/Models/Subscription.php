<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['user_id','plan_id','status','trial_ends_at','current_period_ends_at','gateway','external_id'];
    protected $casts = ['trial_ends_at'=>'datetime','current_period_ends_at'=>'datetime'];
    protected $appends = ['effective_status', 'access_ends_at'];
    public function getEffectiveStatusAttribute(): string
    {
        return in_array($this->status, ['trial', 'active'], true) && !$this->isUsable() ? 'expired' : $this->status;
    }
    public function getAccessEndsAtAttribute(): ?string
    {
        $end = $this->status === 'trial' ? $this->trial_ends_at : $this->current_period_ends_at;
        if ($this->status === 'trial' && $this->current_period_ends_at && (!$end || $this->current_period_ends_at->lt($end))) $end = $this->current_period_ends_at;
        return $end?->toIso8601String();
    }
    public function isUsable(): bool
    {
        if (!in_array($this->status, ['trial', 'active'], true) || !$this->plan?->active) return false;
        if ($this->status === 'trial') {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture()
                && (!$this->current_period_ends_at || $this->current_period_ends_at->isFuture());
        }
        return $this->current_period_ends_at ? $this->current_period_ends_at->isFuture()
            : ($this->plan->complimentary && $this->gateway === 'admin');
    }

    public function user(){ return $this->belongsTo(User::class); }
    public function plan(){ return $this->belongsTo(Plan::class); }
}
