<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentDelinquencyAction extends Model
{
    protected $fillable = [
        'deal_id', 'installment_id', 'actor_id', 'type', 'status', 'payload', 'document_id',
    ];

    protected $casts = ['payload' => 'array'];

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function installment() { return $this->belongsTo(Installment::class); }
    public function deal() { return $this->belongsTo(Deal::class); }
}
