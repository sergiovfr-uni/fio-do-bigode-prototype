<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealWitness extends Model
{
    protected $fillable = ['deal_id', 'registered_by', 'name', 'cpf', 'email', 'invitation_code', 'invitation_status', 'invitation_expires_at', 'viewed_at'];
    protected $hidden = ['cpf'];
    protected $appends = ['invite_url', 'cpf_masked'];
    protected $casts = ['invitation_expires_at' => 'datetime', 'viewed_at' => 'datetime'];

    public function getInviteUrlAttribute(): ?string
    {
        return $this->invitation_code
            ? 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?witness='.$this->invitation_code
            : null;
    }

    public function getCpfMaskedAttribute(): string
    {
        return '***.***.***-'.substr($this->getRawOriginal('cpf'), -2);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
