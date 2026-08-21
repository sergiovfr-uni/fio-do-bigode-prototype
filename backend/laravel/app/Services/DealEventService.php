<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Support\Facades\DB;

class DealEventService
{
    public function record(Deal $deal, ?int $actorId, string $type, array $payload = []): void
    {
        DB::table('deal_events')->insert([
            'deal_id'=>$deal->id,
            'actor_id'=>$actorId,
            'type'=>$type,
            'payload'=>$payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }

    public function notify(Deal $deal, int $userId, string $type, string $title, string $message, array $data = []): void
    {
        DB::table('notifications')->insert([
            'user_id'=>$userId,
            'deal_id'=>$deal->id,
            'type'=>$type,
            'title'=>$title,
            'message'=>$message,
            'data'=>$data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }

    public function otherParty(Deal $deal, int $actorId): int
    {
        return (int)($deal->seller_id === $actorId ? $deal->buyer_id : $deal->seller_id);
    }
}
