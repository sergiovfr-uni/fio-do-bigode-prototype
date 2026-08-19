<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\CampaignController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor']);

    Route::get('/campaigns/home', [CampaignController::class, 'home']);
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{listing}', [ListingController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/listings', [ListingController::class, 'store']);
        Route::post('/listings/{listing}/proposals', [DealController::class, 'fromListing']);
        Route::get('/deals', [DealController::class, 'index']);
        Route::post('/deals', [DealController::class, 'store']);
        Route::post('/deals/{deal}/counteroffers', [DealController::class, 'counteroffer']);
        Route::post('/deals/{deal}/accept', [DealController::class, 'accept']);
    });
});
