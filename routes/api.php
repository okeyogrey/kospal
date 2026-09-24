<?php

use App\Http\Controllers\Api\DesktopUpdateController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('referrals')->middleware('throttle:60,1')->group(function () {
    Route::post('accounts', [ReferralController::class, 'register']);
    Route::get('codes/{code}', [ReferralController::class, 'preview']);
    Route::post('redeem', [ReferralController::class, 'redeem']);

    Route::middleware('referral.account')->group(function () {
        Route::post('codes', [ReferralController::class, 'issue']);
        Route::get('dashboard', [ReferralController::class, 'dashboard']);
        Route::post('apply-payment', [ReferralController::class, 'applyPayment']);
        Route::post('heartbeat', [ReferralController::class, 'heartbeat']);
        Route::post('email', [ReferralController::class, 'email']);
    });
});

Route::prefix('updates')->middleware('throttle:60,1')->group(function () {
    Route::get('desktop', [DesktopUpdateController::class, 'show']);
    Route::get('desktop/package', [DesktopUpdateController::class, 'package']);
});

Route::prefix('sync')->middleware('throttle:sync')->group(function () {
    Route::post('accounts', [SyncController::class, 'register']);
    Route::post('join', [SyncController::class, 'join']);

    Route::middleware('sync.device')->group(function () {
        Route::post('operations', [SyncController::class, 'push']);
        Route::get('operations', [SyncController::class, 'pull']);
        Route::post('join-code', [SyncController::class, 'regenerate']);
        Route::get('office', [SyncController::class, 'office']);
    });
});
