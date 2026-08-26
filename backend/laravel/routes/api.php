<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\ElectronicSignatureController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\DealDocumentController;
use App\Http\Controllers\Api\DealInvitationController;
use App\Http\Controllers\Api\DealRatingController;
use App\Http\Controllers\Api\DiditKycController;
use App\Http\Controllers\Api\DealWitnessController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WitnessInvitationController;

Route::get('/health', function () {
    try {
        DB::select('select 1');
        return response()->json(['status'=>'ok','service'=>'fio-do-bigode-api','database'=>'ok','timestamp'=>now()->toIso8601String()]);
    } catch (\Throwable $e) {
        return response()->json(['status'=>'degraded','service'=>'fio-do-bigode-api','database'=>'error','timestamp'=>now()->toIso8601String()], 503);
    }
});

Route::prefix('v1')->group(function () {
    Route::post('/kyc/didit/webhook', [DiditKycController::class, 'webhook'])->middleware('throttle:120,1');
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/deal-invitations/{code}', [DealInvitationController::class, 'show']);
    Route::get('/campaigns/home', [CampaignController::class, 'home']);
    Route::post('/campaigns/{campaign}/impression', [CampaignController::class, 'impression']);
    Route::post('/campaigns/{campaign}/click', [CampaignController::class, 'click']);
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{listing}', [ListingController::class, 'show']);
    Route::get('/witness-invitations/{code}', [WitnessInvitationController::class, 'show']);
    Route::get('/witness-invitations/{code}/document', [WitnessInvitationController::class, 'download']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me/contract-qualification', [AuthController::class, 'updateQualification']);
        Route::get('/users/lookup', [AuthController::class, 'lookupUser'])->middleware('throttle:20,1');
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::get('/compliance/consents', [ComplianceController::class, 'consents']);
        Route::post('/compliance/consents', [ComplianceController::class, 'acceptConsent']);
        Route::post('/compliance/kyc', [ComplianceController::class, 'submitKyc']);
        Route::post('/kyc/didit/start', [DiditKycController::class, 'start'])->middleware('throttle:10,1');
        Route::get('/kyc/didit/status', [DiditKycController::class, 'status']);
        Route::post('/compliance/account-deletion', [ComplianceController::class, 'requestAccountDeletion']);
        Route::get('/deal-invitations', [DealInvitationController::class, 'index']);
        Route::post('/deal-invitations', [DealInvitationController::class, 'store']);
        Route::post('/deal-invitations/{code}/accept', [DealInvitationController::class, 'accept']);
        Route::post('/listings', [ListingController::class, 'store']);
        Route::post('/listings/{listing}/proposals', [DealController::class, 'fromListing']);
        Route::get('/deals', [DealController::class, 'index']);
        Route::post('/deals', [DealController::class, 'store']);
        Route::get('/deals/{deal}/timeline', [NotificationController::class, 'timeline']);
        Route::post('/deals/{deal}/counteroffers', [DealController::class, 'counteroffer']);
        Route::post('/deals/{deal}/accept', [DealController::class, 'accept']);
        Route::post('/deals/{deal}/reject', [DealController::class, 'reject']);
        Route::get('/deals/{deal}/witnesses', [DealWitnessController::class, 'index']);
        Route::post('/deals/{deal}/witnesses', [DealWitnessController::class, 'store']);
        Route::post('/deals/{deal}/witnesses/skip', [DealWitnessController::class, 'skip']);
        Route::post('/deals/{deal}/contract', [ContractController::class, 'generate']);
        Route::post('/deals/{deal}/generate-documents', [ContractController::class, 'generate']);
        Route::get('/deals/{deal}/contract/download', [ContractController::class, 'download']);
        Route::post('/deals/{deal}/electronic-signature/code', [ElectronicSignatureController::class, 'requestCode'])->middleware('throttle:5,1');
        Route::post('/deals/{deal}/electronic-signature/sign', [ElectronicSignatureController::class, 'sign'])->middleware('throttle:10,1');
        Route::get('/deals/{deal}/documents', [DealDocumentController::class, 'index']);
        Route::get('/deals/{deal}/ratings', [DealRatingController::class, 'index']);
        Route::post('/deals/{deal}/ratings', [DealRatingController::class, 'store']);
        Route::get('/deals/{deal}/documents/{document}/download', [DealDocumentController::class, 'download']);
        Route::post('/deals/{deal}/documents', [DealDocumentController::class, 'store']);
        Route::post('/deals/{deal}/signed-document', [DealDocumentController::class, 'storeSignedBase64']);
        Route::post('/deals/{deal}/entry-receipt', [DealDocumentController::class, 'storeEntryReceiptBase64']);
        Route::get('/deals/{deal}/entry-receipt/download', [DealDocumentController::class, 'downloadEntryReceipt']);
        Route::post('/deals/{deal}/entry-receipt/confirm', [DealDocumentController::class, 'confirmEntryReceipt']);
        Route::get('/deals/{deal}/installments', [InstallmentController::class, 'index']);
        Route::post('/deals/{deal}/installments/{installment}/receipt', [InstallmentController::class, 'storeReceipt']);
        Route::get('/deals/{deal}/installments/{installment}/receipt', [InstallmentController::class, 'downloadReceipt']);
        Route::post('/deals/{deal}/installments/{installment}/paid', [InstallmentController::class, 'markPaid']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/deals', [AdminController::class, 'deals']);
            Route::get('/listings', [AdminController::class, 'listings']);
            Route::get('/wallets', [AdminController::class, 'wallets']);
            Route::get('/installments', [AdminController::class, 'installments']);
            Route::get('/campaigns', [AdminController::class, 'campaigns']);
        });
    });
});
