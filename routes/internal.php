<?php

use App\Http\Controllers\Api\Internal\Admin\BillingController;
use App\Http\Controllers\Api\Internal\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Internal\Admin\MarketplaceAdminController;
use App\Http\Controllers\Api\Internal\Admin\MarketplaceFeesAdminController;
use App\Http\Controllers\Api\Internal\Admin\MarketplaceMappingController;
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

    // Marketplace: group listings of the same item across shops (barcode does it; this is the manual override).
    Route::post('/marketplace/products/{id}/item', [MarketplaceMappingController::class, 'map'])->whereNumber('id')->middleware('internal.admin:super,operations');
    Route::delete('/marketplace/products/{id}/item', [MarketplaceMappingController::class, 'unmap'])->whereNumber('id')->middleware('internal.admin:super,operations');

    // Marketplace money and trust (item 41): disputes, refunds, payouts, reviews.
    Route::post('/marketplace/returns/{reference}/decide', [MarketplaceAdminController::class, 'decideReturn'])->where('reference', 'RET-[A-Z0-9]{6}')->middleware(['internal.admin:super,operations', 'throttle:internal-admin-money']);
    Route::get('/marketplace/returns/{reference}/photos/{who}/{index}', [MarketplaceAdminController::class, 'returnPhoto'])->where(['reference' => 'RET-[A-Z0-9]{6}', 'who' => 'buyer|shop|dispute', 'index' => '[0-3]'])->middleware('internal.admin:super,operations,support');
    Route::post('/marketplace/refunds/{id}/retry', [MarketplaceAdminController::class, 'retryRefund'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/marketplace/payouts/{id}/retry', [MarketplaceAdminController::class, 'retryPayout'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/marketplace/payouts/{id}/mark-paid', [MarketplaceAdminController::class, 'markPayoutPaid'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/shops/{shop}/payouts/hold', [MarketplaceAdminController::class, 'holdPayouts'])->middleware('internal.admin:super,finance,operations');
    Route::post('/shops/{shop}/payouts/release', [MarketplaceAdminController::class, 'releasePayouts'])->middleware('internal.admin:super,finance');
    Route::post('/marketplace/adverts/{id}/stop', [MarketplaceAdminController::class, 'stopAdvert'])->whereNumber('id')->middleware(['internal.admin:super,operations', 'throttle:internal-admin-money']);
    Route::patch('/marketplace/advert-types/{key}', [MarketplaceAdminController::class, 'updateAdvertType'])->where('key', '[a-z_]{3,30}')->middleware('internal.admin:super,finance');
    // Payout fees and commission (item 49): everyone with marketplace access reads; finance changes.
    Route::get('/marketplace/fees', [MarketplaceFeesAdminController::class, 'index'])->middleware('internal.admin:super,operations,finance,support');
    Route::post('/marketplace/fees', [MarketplaceFeesAdminController::class, 'scheduleFees'])->middleware('internal.admin:super,finance');
    Route::post('/marketplace/commission-rates', [MarketplaceFeesAdminController::class, 'scheduleCommission'])->middleware('internal.admin:super,finance');
    Route::post('/marketplace/reviews/{type}/{id}/moderate', [MarketplaceAdminController::class, 'moderateReview'])->where(['type' => 'product|merchant'])->whereNumber('id')->middleware('internal.admin:super,operations,support');

    // Billing
    Route::post('/shops/{shop}/billing/credit', [BillingController::class, 'credit'])->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/shops/{shop}/billing/stk', [BillingController::class, 'stk'])->middleware(['internal.admin:super,finance,operations', 'throttle:internal-admin-money']);
    Route::get('/shops/{shop}/billing/topups/{id}', [BillingController::class, 'topup'])->whereNumber('id')->middleware('internal.admin');
    Route::post('/shops/{shop}/billing/link', [BillingController::class, 'link'])->middleware('internal.admin');
    Route::post('/billing/unmatched/{id}/assign', [BillingController::class, 'assignPayment'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::post('/billing/unmatched/{id}/returned', [BillingController::class, 'returnPayment'])->whereNumber('id')->middleware(['internal.admin:super,finance', 'throttle:internal-admin-money']);
    Route::get('/billing/prices', [BillingController::class, 'prices'])->middleware('internal.admin');

    // Housekeeping: the emails Poachy sends, rendered with sample data (read-only).
    Route::get('/emails/templates', [EmailTemplateController::class, 'index'])->middleware('internal.admin:super,operations,support');
    Route::get('/emails/templates/{key}/preview', [EmailTemplateController::class, 'preview'])->middleware('internal.admin:super,operations,support');
    Route::post('/billing/prices', [BillingController::class, 'storePrice'])->middleware('internal.admin:super,finance');
});
