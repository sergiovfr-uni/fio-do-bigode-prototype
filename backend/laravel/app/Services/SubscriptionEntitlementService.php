<?php
namespace App\Services;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SubscriptionEntitlementService
{
    public function current(User $user): ?Subscription
    {
        return $user->subscriptions()->with('plan')->whereIn('status', ['trial', 'active'])
            ->latest('id')->get()->first(fn (Subscription $subscription) => $subscription->isUsable());
    }
    public function requireCurrent(User $user): Subscription
    {
        $subscription = $this->current($user);
        if (!$subscription) throw ValidationException::withMessages([
            'plan'=>'Seu período de acesso terminou. Entre em contato com a administração para continuar criando anúncios e negociações.',
        ]);
        return $subscription;
    }
    public function assertCanCreateDirectDeal(User $user): void
    {
        $subscription = $this->requireCurrent($user);
        $limit = $subscription->plan->direct_deal_limit;
        $deals = $user->dealsAsSeller()->where('origin', 'direct')
            ->whereNotIn('status', ['rejected', 'cancelled', 'paid', 'settled', 'completed', 'quitada', 'paid_off', 'closed'])->count();
        $invitations = DB::table('deal_invitations')->where('created_by', $user->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        if ($deals + $invitations >= $limit) throw ValidationException::withMessages([
            'plan'=>"Limite de {$limit} negociação(ões) direta(s) em andamento atingido.",
        ]);
    }
}
