<?php

namespace App\Support;

use App\Models\User;

final class GooglePlayReviewAccess
{
    public static function pinFor(User $user): ?string
    {
        if (!config('google_play_review.enabled')) return null;

        $pin = (string) config('google_play_review.pin');
        if (!preg_match('/^\d{6}$/', $pin)) return null;

        $email = mb_strtolower(trim((string) $user->email));
        $reviewEmails = array_filter([
            config('google_play_review.seller.email'),
            config('google_play_review.buyer.email'),
        ]);

        return in_array($email, $reviewEmails, true) ? $pin : null;
    }
}
