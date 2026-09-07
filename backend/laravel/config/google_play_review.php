<?php

return [
    'enabled' => filter_var(env('GOOGLE_PLAY_REVIEW_ACCESS', false), FILTER_VALIDATE_BOOL),
    'pin' => env('GOOGLE_PLAY_REVIEW_PIN'),
    'seller' => [
        'email' => mb_strtolower((string) env('GOOGLE_PLAY_REVIEW_SELLER_EMAIL', 'review.seller@nofiodobigode.app.br')),
        'password' => env('GOOGLE_PLAY_REVIEW_SELLER_PASSWORD'),
    ],
    'buyer' => [
        'email' => mb_strtolower((string) env('GOOGLE_PLAY_REVIEW_BUYER_EMAIL', 'review.buyer@nofiodobigode.app.br')),
        'password' => env('GOOGLE_PLAY_REVIEW_BUYER_PASSWORD'),
    ],
];
