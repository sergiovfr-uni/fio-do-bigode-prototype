<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_user_id', 'action', 'entity_type', 'entity_id', 'before_data',
        'after_data', 'reason', 'ip_hash', 'user_agent_hash',
    ];

    protected $casts = ['before_data'=>'array', 'after_data'=>'array'];

    public function admin(){ return $this->belongsTo(User::class, 'admin_user_id'); }
}
