<?php

return [
    'api_key' => env('DIDIT_API_KEY'),
    'workflow_id' => env('DIDIT_WORKFLOW_ID'),
    'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
    'api_url' => env('DIDIT_API_URL', 'https://verification.didit.me'),
    'callback_url' => env('DIDIT_CALLBACK_URL', 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?kyc_return=1'),
    'environment' => env('DIDIT_ENVIRONMENT', 'sandbox'),
];
