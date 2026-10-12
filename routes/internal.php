<?php

use App\Http\Controllers\Api\Internal\Admin\BillingController;
use App\Http\Controllers\Api\Internal\Admin\ShopController;
use App\Http\Controllers\Api\Internal\Admin\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| Private admin API (/api/internal/admin/*) for the poachy-admin app's server only.
| Guarded by the `internal.admin` middleware (allowlisted network + service token + acting admin);
| per-route role lists below. Marketplace actions (adverts, refunds, disputes, payout holds) join
| this file with their features (tracker items 37, 38, 41, 42).
*/

Route::middleware('throttle:internal-admin')->group(function () {
    // Shops
    Route::post('/shops', [ShopController::class, 'store'])->middleware('internal.admin:super,operations');
    Route::get('/shops/onboarding/{id}', [ShopController::class, 'onboarding'])->middleware('internal.admin');
    Route::get('/shops/{shop}/team', [ShopController::class, 'team'])->middleware('internal.admin');
    Route::post('/shops/{shop}/verification', [ShopController::class, 'verification'])->middleware('internal.admin:super,operations');
    Route::get('/shops/{shop}/verification', [VerificationController::class, 'show'])->middleware('internal.admin');
    Route::post('/shops/{shop}/verification/checks', [VerificationController::class, 'store'])->middleware('internal.admin:super,operations');
    Route::get('/shops/{shop}/verification/checks/{id}/file', [VerificationController::class, 'file'])->whereNumber('id')->middleware('internal.admin:super,operations');
    Route::post('/shops/{shop}/suspend', [ShopController::class, 'suspend'])->middleware('internal.admin:super,operations');
    Route::post('/shops/{shop}/restore', [ShopController::class, 'restore'])->middleware('internal.admin:super,operations');
    Route::post('/shops/{shop}/trial', [ShopController::class, 'extendTrial'])->middleware('internal.admin:super,operations,finance');

    // Billing
    Route::post('/shops/{shop}/billing/credit', [BillingController::class, 'credit'])->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/shops/{shop}/billing/stk', [BillingController::class, 'stk'])->middleware(['internal.admin:super,finance,operations', 'throttle:internal-admin-money']);
    Route::get('/shops/{shop}/billing/topups/{id}', [BillingController::class, 'topup'])->whereNumber('id')->middleware('internal.admin');
    Route::post('/shops/{shop}/billing/link', [BillingController::class, 'link'])->middleware('internal.admin');
    Route::post('/billing/unmatched/{id}/assign', [BillingController::class, 'assignPayment'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::get('/billing/prices', [BillingController::class, 'prices'])->middleware('internal.admin');
    Route::post('/billing/prices', [BillingController::class, 'storePrice'])->middleware('internal.admin:super,finance');
});
