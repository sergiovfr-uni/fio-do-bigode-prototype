<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealWitness extends Model
{
    protected $fillable = ['deal_id', 'registered_by', 'name', 'cpf', 'email'];
    protected $hidden = ['cpf'];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
