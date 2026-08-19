<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ListingEntitlementService
{
 public function assertCanPublish(User $user): void
 {
  $subscription = $user->subscriptions()->with('plan')->whereIn('status',['trial','active'])->latest()->first();
  $limit = $subscription?->plan?->active_listing_limit ?? 1;
  $active = $user->listings()->where('status','published')->count();
  if ($active >= $limit) {
   throw ValidationException::withMessages(['plan'=>"Limite de {$limit} anúncio(s) ativo(s) atingido. Faça upgrade do plano para publicar outro anúncio."]);
  }
 }
}
