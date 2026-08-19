<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name','cpf','email','phone','password','kyc_status','risk_score','reputation_score'];
    protected $hidden = ['password','remember_token','cpf'];
    protected $casts = ['email_verified_at'=>'datetime','risk_score'=>'integer','reputation_score'=>'integer'];
}
