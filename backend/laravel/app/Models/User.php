<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name','cpf','identity_document','birth_date','marital_status','occupation','nationality','email','phone','address_line','address_number','address_complement','district','city','state','postal_code','password','kyc_status','risk_score','reputation_score','account_status','deletion_requested_at'];
    protected $hidden = ['password','remember_token','cpf'];
    protected $casts = ['email_verified_at'=>'datetime','birth_date'=>'date:Y-m-d','deletion_requested_at'=>'datetime','risk_score'=>'integer','reputation_score'=>'integer'];

    protected $appends = ['contract_qualification_complete', 'reputation_reviews_count'];

    public function hasContractQualification(): bool
    {
        foreach (['name','cpf','identity_document','birth_date','marital_status','occupation','nationality','address_line','address_number','district','city','state','postal_code'] as $field) {
            if (blank($this->{$field})) return false;
        }
        return true;
    }

    public function getContractQualificationCompleteAttribute(): bool
    {
        return $this->hasContractQualification();
    }

    public function getReputationReviewsCountAttribute(): int
    {
        return (int) DB::table('deal_ratings')->where('rated_user_id', $this->id)->count();
    }

    public function subscriptions(){ return $this->hasMany(Subscription::class); }
    public function listings(){ return $this->hasMany(Listing::class,'seller_id'); }
    public function dealsAsSeller(){ return $this->hasMany(Deal::class,'seller_id'); }
    public function dealsAsBuyer(){ return $this->hasMany(Deal::class,'buyer_id'); }
    public function offers(){ return $this->hasMany(DealOffer::class,'created_by'); }
}
