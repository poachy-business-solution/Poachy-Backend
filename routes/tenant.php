<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Audit\AuditLogController;
use App\Http\Controllers\Api\Tenant\Auth\TenantAuthController;
use App\Http\Controllers\Api\Tenant\Budget\BudgetController;
use App\Http\Controllers\Api\Tenant\Business\BusinessDetailsController;
use App\Http\Controllers\Api\Tenant\Business\BusinessHelperController;
use App\Http\Controllers\Api\Tenant\Customer\CustomerController;
use App\Http\Controllers\Api\Tenant\Customer\CustomerCreditTransactionController;
use App\Http\Controllers\Api\Tenant\Customer\CustomerGroupController;
use App\Http\Controllers\Api\Tenant\Customer\LoyaltyTransactionController;
use App\Http\Controllers\Api\Tenant\Expenses\ExpenseCategoryController;
use App\Http\Controllers\Api\Tenant\Expenses\ExpenseController;
use App\Http\Controllers\Api\Tenant\Inventory\ExpiryAlertController;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryMovementController;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryWasteController;
use App\Http\Controllers\Api\Tenant\Inventory\ProductBatchController;
use App\Http\Controllers\Api\Tenant\Inventory\PurchaseOrderController;
use App\Http\Controllers\Api\Tenant\Inventory\StockAlertController;
use App\Http\Controllers\Api\Tenant\Inventory\StockTransferController;
use App\Http\Controllers\Api\Tenant\Offers\CouponController;
use App\Http\Controllers\Api\Tenant\Offers\PromotionController;
use App\Http\Controllers\Api\Tenant\Product\ProductBrandController;
use App\Http\Controllers\Api\Tenant\Product\ProductBundleController;
use App\Http\Controllers\Api\Tenant\Product\ProductCategoryController;
use App\Http\Controllers\Api\Tenant\Product\ProductController;
use App\Http\Controllers\Api\Tenant\Product\ProductPriceHistoryController;
use App\Http\Controllers\Api\Tenant\Product\ProductUomController;
use App\Http\Controllers\Api\Tenant\Product\ProductVariantController;
use App\Http\Controllers\Api\Tenant\Sales\DailySalesReportController;
use App\Http\Controllers\Api\Tenant\Sales\SaleController;
use App\Http\Controllers\Api\Tenant\Sales\ShiftSalesSummaryController;
use App\Http\Controllers\Api\Tenant\Shift\ShiftAnalyticsController;
use App\Http\Controllers\Api\Tenant\Shift\ShiftAssignmentController;
use App\Http\Controllers\Api\Tenant\Shift\ShiftController;
use App\Http\Controllers\Api\Tenant\Shift\ShiftSwapController;
use App\Http\Controllers\Api\Tenant\Store\StoreController;
use App\Http\Controllers\Api\Tenant\Store\StoreProductController;
use App\Http\Controllers\Api\Tenant\Supplier\SupplierController;
use App\Http\Controllers\Api\Tenant\Supplier\SupplierPaymentController;
use App\Http\Controllers\Api\Tenant\Tax\TaxRateController;
use App\Http\Controllers\Api\Tenant\TenantAccessController;
use App\Http\Controllers\Api\Tenant\Uom\UnitOfMeasureController;
use App\Http\Controllers\Api\Tenant\Uom\UomConversionController;
use App\Http\Controllers\Api\Tenant\User\TenantUserController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes (v1)
|--------------------------------------------------------------------------
|
| These routes handle tenant-specific operations.
| They REQUIRE tenant middleware (InitializeTenancyByDomain).
|
*/

// Public tenant routes (no authentication required)
Route::prefix('v1/tenant')->group(function () {

    // Tenant Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/login', [TenantAuthController::class, 'login']);
        Route::post('/verify-otp', [TenantAuthController::class, 'verifyOtp']); // Step 2: Verify OTP & get token
        Route::post('/resend-otp', [TenantAuthController::class, 'resendOtp']); // Resend OTP
        Route::post('/change-password', [TenantAuthController::class, 'changePassword']); // First-time password change
    });
});

// Protected tenant routes requires authentication & active subscription
Route::prefix('v1/tenant')
    ->middleware(['auth:tenant', 'tenant.access'])
    ->group(function () {

        // Store Management
        Route::prefix('stores')->group(function () {
            Route::get('/', [StoreController::class, 'index']);
            Route::post('/', [StoreController::class, 'store']);
            Route::get('/{id}', [StoreController::class, 'show']);
            Route::patch('/{id}/details', [StoreController::class, 'updateDetails']);
            Route::patch('/{id}/set-main', [StoreController::class, 'setAsMain']);
            Route::patch('/{id}/activate', [StoreController::class, 'activate']);
            Route::patch('/{id}/deactivate', [StoreController::class, 'deactivate']);
            Route::post('/{id}/assign-manager', [StoreController::class, 'assignManager']);
            Route::delete('/{id}/remove-manager', [StoreController::class, 'removeManager']);
        });

        // Product Categories Management
        Route::prefix('categories')->group(function () {
            Route::get('/', [ProductCategoryController::class, 'index']);
            Route::post('/', [ProductCategoryController::class, 'store']);
            Route::get('/{category}', [ProductCategoryController::class, 'show']);
            Route::patch('/{category}', [ProductCategoryController::class, 'update']);
            Route::patch('/{category}/activate', [ProductCategoryController::class, 'activate']);
            Route::patch('/{category}/deactivate', [ProductCategoryController::class, 'deactivate']);
            Route::delete('/{category}', [ProductCategoryController::class, 'destroy']);
        });

        // Product Brands Management
        Route::prefix('brands')->group(function () {
            Route::get('/', [ProductBrandController::class, 'index']);
            Route::get('/{brand}', [ProductBrandController::class, 'show']);
            Route::post('/', [ProductBrandController::class, 'store']);
            Route::patch('/{brand}/activate', [ProductBrandController::class, 'activate']);
            Route::patch('/{brand}/deactivate', [ProductBrandController::class, 'deactivate']);
            Route::patch('/{brand}/feature', [ProductBrandController::class, 'feature']);
            Route::patch('/{brand}/unfeature', [ProductBrandController::class, 'unfeature']);
            Route::post('/{brand}/logo', [ProductBrandController::class, 'updateLogo']);
            Route::delete('/{brand}', [ProductBrandController::class, 'destroy']);
        });

        // Tax Rates Management
        Route::prefix('tax-rates')->group(function () {
            Route::get('/', [TaxRateController::class, 'index']);
            Route::post('/', [TaxRateController::class, 'store']);
            Route::patch('/{taxRate}/toggle-active', [TaxRateController::class, 'toggleActive']);
            Route::patch('/{taxRate}/toggle-default', [TaxRateController::class, 'toggleDefault']);
            Route::patch('/{taxRate}/effective-until', [TaxRateController::class, 'updateEffectiveUntil']);
        });

        // Suppliers Routes
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index']);
            Route::post('/', [SupplierController::class, 'store']);
            Route::get('/supplier-options', [SupplierController::class, 'supplierOptions']);
            Route::get('/{supplier}', [SupplierController::class, 'show']);
            Route::patch('/{supplier}/personal-details', [SupplierController::class, 'updatePersonalDetails']);
            Route::patch('/{supplier}/financial-details', [SupplierController::class, 'updateFinancialDetails']);
            Route::patch('/{supplier}/toggle-active', [SupplierController::class, 'toggleActive']);
        });

        // Units of Measure Routes
        Route::prefix('units-of-measure')->group(function () {
            Route::get('/', [UnitOfMeasureController::class, 'index']);
            Route::get('/{id}', [UnitOfMeasureController::class, 'show']);
            Route::post('/', [UnitOfMeasureController::class, 'store']);
            Route::patch('/{id}', [UnitOfMeasureController::class, 'update']);
            Route::get('/{id}/conversion-options', [UnitOfMeasureController::class, 'conversionOptions']);
            Route::post('/{id}/set-base-unit', [UnitOfMeasureController::class, 'setBaseUnit']);
            Route::delete('/{id}/remove-base-unit', [UnitOfMeasureController::class, 'removeBaseUnit']);
        });

        // UOM Conversion Routes
        Route::prefix('uom-conversions')->group(function () {
            Route::post('/', [UomConversionController::class, 'store']);
            Route::patch('/{id}', [UomConversionController::class, 'update']);
            Route::delete('/{id}', [UomConversionController::class, 'destroy']);
            Route::post('/convert', [UomConversionController::class, 'convert']);
        });

        // Products Routes
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/{uuid}', [ProductController::class, 'show']);
            Route::patch('/{uuid}', [ProductController::class, 'update']);
            Route::patch('/{uuid}/inventory', [ProductController::class, 'updateInventoryConfig']);
            Route::patch('/{uuid}/online', [ProductController::class, 'updateOnlineConfig']);
            Route::patch('/{uuid}/toggle-active', [ProductController::class, 'toggleActive']);
            Route::patch('/{uuid}/toggle-featured', [ProductController::class, 'toggleFeatured']);
            Route::post('/{uuid}/images', [ProductController::class, 'addImages']);
            Route::post('/{uuid}/primary-image', [ProductController::class, 'updatePrimaryImage']);
            Route::delete('/{uuid}/images', [ProductController::class, 'removeImage']);

            Route::prefix('{uuid}/uoms')->group(function () {
                Route::get('/', [ProductUomController::class, 'index']);
                Route::post('/', [ProductUomController::class, 'store']);
                Route::patch('/{productUom}', [ProductUomController::class, 'update']);
                Route::delete('/{productUomId}', [ProductUomController::class, 'destroy']);
                Route::get('/base', [ProductUomController::class, 'base']);
                Route::get('/purchase', [ProductUomController::class, 'purchase']);
                Route::get('/sales', [ProductUomController::class, 'sales']);
            });

            Route::prefix('{uuid}/variants')->group(function () {
                Route::get('/', [ProductVariantController::class, 'index']);
                Route::post('/', [ProductVariantController::class, 'store']);
            });
        });

        // Product variants Routes
        Route::prefix('variants')->group(function () {
            Route::get('/', [ProductVariantController::class, 'indexAll']);
            Route::get('/{id}', [ProductVariantController::class, 'show']);
            Route::patch('/{id}', [ProductVariantController::class, 'update']);
            Route::delete('/{id}', [ProductVariantController::class, 'destroy']);
            Route::patch('/{id}/toggle-active', [ProductVariantController::class, 'toggleActive']);
            Route::patch('/{id}/inventory', [ProductVariantController::class, 'updateInventory']);
        });

        // Product Bundles
        Route::prefix('bundles')->group(function () {
            Route::get('/', [ProductBundleController::class, 'index']);
            Route::post('/', [ProductBundleController::class, 'store']);
            Route::get('/{id}', [ProductBundleController::class, 'show']);
            Route::patch('/{id}', [ProductBundleController::class, 'update']);
            Route::delete('/{id}', [ProductBundleController::class, 'destroy']);

            // Items
            Route::post('/{id}/items', [ProductBundleController::class, 'addItem']);
            Route::patch('/{id}/items/{itemId}', [ProductBundleController::class, 'updateItem']);
            Route::delete('/{id}/items/{itemId}', [ProductBundleController::class, 'removeItem']);

            // Status toggles
            Route::patch('/{id}/toggle-active', [ProductBundleController::class, 'toggleActive']);
            Route::patch('/{id}/toggle-online', [ProductBundleController::class, 'toggleOnline']);

            // Pricing
            Route::patch('/{id}/pricing', [ProductBundleController::class, 'updatePricing']);

            // Images
            Route::post('/{id}/images', [ProductBundleController::class, 'addImages']);
            Route::delete('/{id}/images', [ProductBundleController::class, 'removeImage']);

            // Utilities
            Route::get('/{id}/savings', [ProductBundleController::class, 'calculateSavings']);
            Route::get('/{id}/breakdown', [ProductBundleController::class, 'getBreakdown']);
        });

        // Store Products Routes
        Route::prefix('stores')->group(function () {
            Route::get('{store?}/products', [StoreProductController::class, 'index']);
            Route::post('{store?}/products', [StoreProductController::class, 'store']);
            Route::get('{store?}/products/stats', [StoreProductController::class, 'stats']);
            Route::get('{store?}/products/{product}', [StoreProductController::class, 'show']);
            Route::patch('{store?}/products/{product}', [StoreProductController::class, 'update']);
            Route::patch('{store?}/products/{product}/availability', [StoreProductController::class, 'toggleAvailability']);
            Route::delete('{store?}/products/{product}', [StoreProductController::class, 'destroy']);
        });

        // Inventory Management Routes
        Route::prefix('inventory')->group(function () {
            Route::get('/', [InventoryController::class, 'index']);
            Route::post('/check-availability', [InventoryController::class, 'checkAvailability']);
            Route::get('/low-stock/list', [InventoryController::class, 'getLowStock']);
            Route::get('/out-of-stock/list', [InventoryController::class, 'getOutOfStock']);
            Route::get('/value/calculate', [InventoryController::class, 'getInventoryValue']);
            Route::get('/summary', [InventoryController::class, 'getSummary']);
            Route::get('/{id}', [InventoryController::class, 'show']);
            Route::get('/product/{productId}', [InventoryController::class, 'getProductInventory']);
        });

        Route::prefix('inventory-movements')->group(function () {
            Route::get('/', [InventoryMovementController::class, 'index']);
            Route::get('/{id}', [InventoryMovementController::class, 'show']);
            Route::post('/adjustment', [InventoryMovementController::class, 'createAdjustment']);
            Route::post('/damage', [InventoryMovementController::class, 'createDamage']);
        });

        // Stock Transfers Routes
        Route::prefix('transfers')->group(function () {
            Route::get('/', [StockTransferController::class, 'index']);
            Route::get('/pending/approvals', [StockTransferController::class, 'pendingApprovals']);
            Route::get('/{id}', [StockTransferController::class, 'show']);
            Route::post('/', [StockTransferController::class, 'store']);
            Route::post('/{id}/approve', [StockTransferController::class, 'approve']);
            Route::post('/{id}/send', [StockTransferController::class, 'send']);
            Route::post('/{id}/receive', [StockTransferController::class, 'receive']);
            Route::post('/{id}/cancel', [StockTransferController::class, 'cancel']);
        });

        Route::prefix('purchase-orders')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index']);
            Route::get('/{id}', [PurchaseOrderController::class, 'show']);
            Route::post('/', [PurchaseOrderController::class, 'store']);
            Route::patch('/{id}', [PurchaseOrderController::class, 'update']);
            Route::post('/{id}/send', [PurchaseOrderController::class, 'send']);
            Route::post('/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
        });

        // Product Batches Routes
        Route::prefix('batches')->group(function () {
            Route::get('/', [ProductBatchController::class, 'index']);
            Route::post('/receive', [ProductBatchController::class, 'store']);
            Route::get('/valuation/calculate', [ProductBatchController::class, 'valuation']);
            Route::get('/cogs/calculate', [ProductBatchController::class, 'calculateCogs']);
            Route::post('/expired/mark', [ProductBatchController::class, 'markExpired']);
            Route::get('/{id}', [ProductBatchController::class, 'show']);
        });

        // Customer Management Routes
        Route::prefix('customers')->group(function () {
            Route::get('/search', [CustomerController::class, 'search']);
            Route::get('marketing-eligible', [CustomerController::class, 'marketingEligible']);
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/{customer}', [CustomerController::class, 'show']);
            Route::patch('/{customer}', [CustomerController::class, 'update']);
            Route::delete('/{customer}', [CustomerController::class, 'destroy']);
            Route::post('/{customer}/restore', [CustomerController::class, 'restore']);
            Route::patch('/{customer}/upgrade-type', [CustomerController::class, 'upgradeType']);
            Route::patch('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
            Route::patch('{customer}/toggle-marketing', [CustomerController::class, 'toggleMarketingConsent']);
        });

        // Customer Group Routes
        Route::prefix('customer-groups')->group(function () {
            Route::get('/', [CustomerGroupController::class, 'index']);
            Route::post('/', [CustomerGroupController::class, 'store']);
            Route::get('/{customer_group}', [CustomerGroupController::class, 'show']);
            Route::patch('/{customer_group}', [CustomerGroupController::class, 'update']);
            Route::delete('/{customer_group}', [CustomerGroupController::class, 'destroy']);
            Route::patch('/{customer_group}/toggle', [CustomerGroupController::class, 'toggleStatus']);
            Route::get('/{customer_group}/members', [CustomerGroupController::class, 'members']);
            Route::post('/{customer_group}/members', [CustomerGroupController::class, 'addMember']);
            Route::delete('/{customer_group}/members/{customer}', [CustomerGroupController::class, 'removeMember']);
            Route::post('/{customer_group}/members/bulk', [CustomerGroupController::class, 'bulkAddMembers']);
        });

        // Coupon Management
        Route::prefix('coupons')->group(function () {
            Route::get('available-coupons', [CouponController::class, 'availableCoupons']);
            Route::get('/', [CouponController::class, 'index']);
            Route::post('/', [CouponController::class, 'store']);
            Route::get('/{id}', [CouponController::class, 'show']);
            Route::patch('/{id}', [CouponController::class, 'update']);
            Route::delete('/{id}', [CouponController::class, 'destroy']);

            // Activation/Deactivation
            Route::patch('/{id}/activate', [CouponController::class, 'activate']);
            Route::patch('/{id}/deactivate', [CouponController::class, 'deactivate']);

            // Applicability Management - Products
            Route::post('/{id}/products', [CouponController::class, 'attachProducts']);
            Route::post('/{id}/products/bulk', [CouponController::class, 'bulkAttachProducts']);
            Route::delete('/{id}/products/bulk', [CouponController::class, 'bulkDetachProducts']);
            Route::delete('/{id}/products/{productId}', [CouponController::class, 'detachProduct']);

            // Applicability Management - Categories
            Route::post('/{id}/categories', [CouponController::class, 'attachCategories']);
            Route::delete('/{id}/categories/{categoryId}', [CouponController::class, 'detachCategory']);

            // Applicability Management - Brands
            Route::post('/{id}/brands', [CouponController::class, 'attachBrands']);
            Route::delete('/{id}/brands/{brandId}', [CouponController::class, 'detachBrand']);
        });

        // Promotions Management
        Route::prefix('promotions')->group(function () {
            Route::get('active',   [PromotionController::class, 'activePromotions']);
            Route::get('featured', [PromotionController::class, 'featuredPromotions']);
            Route::get('pos',      [PromotionController::class, 'posPromotions']);
            Route::get('website',  [PromotionController::class, 'websitePromotions']);
            Route::apiResource('/', PromotionController::class)->parameter('', 'promotion');
            Route::patch('{promotion}/activate',   [PromotionController::class, 'activate']);
            Route::patch('{promotion}/deactivate', [PromotionController::class, 'deactivate']);
            Route::post('{promotion}/banner', [PromotionController::class, 'updateBanner']);
            Route::delete('{promotion}/banner', [PromotionController::class, 'removeBanner']);
            Route::post('{promotion}/products',            [PromotionController::class, 'attachProducts']);
            Route::post('{promotion}/products/bulk',       [PromotionController::class, 'bulkAttachProducts']);
            Route::delete('{promotion}/products/bulk',     [PromotionController::class, 'bulkDetachProducts']);
            Route::delete('{promotion}/products/{product}', [PromotionController::class, 'detachProduct']);
            Route::post('{promotion}/categories',            [PromotionController::class, 'attachCategories']);
            Route::delete('{promotion}/categories/{category}', [PromotionController::class, 'detachCategory']);
            Route::post('{promotion}/brands',         [PromotionController::class, 'attachBrands']);
            Route::delete('{promotion}/brands/{brand}', [PromotionController::class, 'detachBrand']);
            Route::patch('{promotion}/stores',          [PromotionController::class, 'updateStores']);
            Route::patch('{promotion}/customer-groups', [PromotionController::class, 'updateCustomerGroups']);
        });

        // User Management (Owner/Manager only)
        Route::middleware(['role:owner|manager,tenant'])->group(function () {
            Route::get('/users', [TenantUserController::class, 'index']);
            Route::post('/users', [TenantUserController::class, 'store']);
            Route::put('/users/{userId}', [TenantUserController::class, 'update']);
            Route::delete('/users/{userId}', [TenantUserController::class, 'destroy']);
            Route::get('/roles', [TenantUserController::class, 'roles']);
        });

        // Role Assignment (Owner only)
        Route::middleware(['role:owner,tenant'])->group(function () {
            Route::post('/users/{userId}/assign-role', [TenantUserController::class, 'assignRole']);
        });

        // Expense Categories
        Route::prefix('expense-categories')->group(function () {
            Route::get('/', [ExpenseCategoryController::class, 'index']);
            Route::get('/tree', [ExpenseCategoryController::class, 'tree']);
            Route::get('/recurring-eligible', [ExpenseCategoryController::class, 'recurringEligible']);
            Route::post('/', [ExpenseCategoryController::class, 'store']);
            Route::get('/{expense_category}', [ExpenseCategoryController::class, 'show']);
            Route::patch('/{expense_category}', [ExpenseCategoryController::class, 'update']);
            Route::delete('/{expense_category}', [ExpenseCategoryController::class, 'destroy']);
            Route::get('/{expense_category}/children', [ExpenseCategoryController::class, 'children']);
            Route::post('/{expense_category}/toggle-active', [ExpenseCategoryController::class, 'toggleActive']);
        });

        // Expenses
        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::post('/', [ExpenseController::class, 'store']);
            Route::get('/pending-approval', [ExpenseController::class, 'pendingApproval']);
            Route::get('/analytics', [ExpenseController::class, 'analytics']);
            Route::get('/{expense}', [ExpenseController::class, 'show']);
            Route::patch('/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/{expense}', [ExpenseController::class, 'destroy']);

            // Approval actions
            Route::post('/{expense}/approve', [ExpenseController::class, 'approve']);
            Route::post('/{expense}/reject', [ExpenseController::class, 'reject']);

            // Receipt management
            Route::post('/{expense}/upload-receipt', [ExpenseController::class, 'uploadReceipt']);
            Route::delete('/{expense}/delete-receipt', [ExpenseController::class, 'deleteReceipt']);

            // Recurrences
            Route::post('/{expense}/set-recurrence', [ExpenseController::class, 'setRecurrence']);
            Route::patch('/{expense}/update-recurrence', [ExpenseController::class, 'updateRecurrence']);
            Route::post('/{expense}/cancel-recurrence', [ExpenseController::class, 'cancelRecurrence']);
            Route::get('/{expense}/recurrences', [ExpenseController::class, 'getRecurrences']);
            Route::post('/{expense}/generate-recurrence', [ExpenseController::class, 'generateRecurrence']);
        });

        // Budgets
        Route::prefix('budgets')->group(function () {
            Route::get('/', [BudgetController::class, 'index']);
            Route::post('/', [BudgetController::class, 'store']);
            Route::get('/current', [BudgetController::class, 'current']);
            Route::get('/alerts', [BudgetController::class, 'alerts']);
            Route::get('/over-budget', [BudgetController::class, 'overBudget']);
            Route::get('/performance', [BudgetController::class, 'performance']);
            Route::get('/{budget}', [BudgetController::class, 'show']);
            Route::patch('/{budget}', [BudgetController::class, 'update']);
            Route::delete('/{budget}', [BudgetController::class, 'destroy']);
            Route::post('/{budget}/recalculate', [BudgetController::class, 'recalculate']);
            Route::get('/{budget}/expenses', [BudgetController::class, 'expenses']);
        });

        // Shift Management Routes
        Route::prefix('shifts')->group(function () {
            Route::get('/', [ShiftController::class, 'index']);
            Route::post('/', [ShiftController::class, 'store']);
            Route::get('/statistics', [ShiftController::class, 'statistics']);
            Route::get('/for-date', [ShiftController::class, 'forDate']);
            Route::get('/{shift}', [ShiftController::class, 'show']);
            Route::patch('/{shift}', [ShiftController::class, 'update']);
            Route::delete('/{shift}', [ShiftController::class, 'destroy']);
            Route::post('/{shift}/toggle-active', [ShiftController::class, 'toggleActive']);
            Route::post('/{shift}/duplicate', [ShiftController::class, 'duplicate']);
        });

        // Shift Assignments Routes
        Route::prefix('shift-assignments')->group(function () {
            Route::get('/', [ShiftAssignmentController::class, 'index']);
            Route::post('/', [ShiftAssignmentController::class, 'store']);
            Route::post('/bulk', [ShiftAssignmentController::class, 'bulkStore']);
            Route::get('/statistics', [ShiftAssignmentController::class, 'statistics']);
            Route::get('/upcoming', [ShiftAssignmentController::class, 'upcomingAssignments']);
            Route::get('/needing-approval', [ShiftAssignmentController::class, 'needingApproval']);
            Route::get('/{assignment}', [ShiftAssignmentController::class, 'show']);
            Route::post('/{assignment}/cancel', [ShiftAssignmentController::class, 'cancel']);
            Route::post('/{assignment}/clock-in', [ShiftAssignmentController::class, 'clockIn']);
            Route::post('/{assignment}/clock-out', [ShiftAssignmentController::class, 'clockOut']);
            Route::post('/{assignment}/approve', [ShiftAssignmentController::class, 'approve']);
            Route::get('/{assignment}/clock-out-info', [ShiftAssignmentController::class, 'getClockOutInfo']);
        });
        Route::get('/users/{userId}/shift-assignments', [ShiftAssignmentController::class, 'userAssignments']);
        Route::get('/stores/{storeId}/shift-assignments', [ShiftAssignmentController::class, 'storeAssignments']);

        // Shift Sales Summary Routes
        Route::prefix('shifts/{shiftAssignment}')->group(function () {
            Route::get('sales-summary', [ShiftSalesSummaryController::class, 'show']);
            Route::post('recalculate-summary', [ShiftSalesSummaryController::class, 'recalculate']);
            Route::get('cash-reconciliation', [ShiftSalesSummaryController::class, 'cashReconciliation']);
        });

        // Shift Analytics
        Route::prefix('shift-analytics')->group(function () {
            Route::get('/attendance-rate', [ShiftAnalyticsController::class, 'attendanceRate']);
            Route::get('/cash-variances', [ShiftAnalyticsController::class, 'cashVariances']);
            Route::get('/top-performers', [ShiftAnalyticsController::class, 'topPerformers']);
            Route::get('/coverage-report', [ShiftAnalyticsController::class, 'coverageReport']);
            Route::get('/overtime-analysis', [ShiftAnalyticsController::class, 'overtimeAnalysis']);
            Route::get('/punctuality-analysis', [ShiftAnalyticsController::class, 'punctualityAnalysis']);
            Route::get('/dashboard-summary', [ShiftAnalyticsController::class, 'dashboardSummary']);
        });

        // Shift Swaps 
        Route::prefix('shift-swaps')->group(function () {
            Route::get('/', [ShiftSwapController::class, 'index']);
            Route::post('/', [ShiftSwapController::class, 'store']);
            Route::get('/statistics', [ShiftSwapController::class, 'statistics']);
            Route::get('/{swapRequest}', [ShiftSwapController::class, 'show']);
        });

        // Sales Management
        Route::prefix('sales')->group(function () {
            Route::get('customers/search', [SaleController::class, 'searchCustomer']);
            Route::post('calculate', [SaleController::class, 'calculateSale']);
            Route::post('/', [SaleController::class, 'createSale']);
            Route::get('/', [SaleController::class, 'listSales']);
            Route::get('{sale}', [SaleController::class, 'getSale']);
            Route::get('{sale}/receipt', [SaleController::class, 'generateReceipt']);
            Route::post('{sale}/email-receipt', [SaleController::class, 'emailReceipt']);
        });

        // Loyalty Transactions
        Route::prefix('loyalty-transactions')->group(function () {
            Route::get('/', [LoyaltyTransactionController::class, 'index']);
            Route::post('/award-manual', [LoyaltyTransactionController::class, 'awardManual']);
            Route::get('/analytics/overview', [LoyaltyTransactionController::class, 'analytics'])->middleware('permission:loyalty-transactions');
            Route::get('/{id}', [LoyaltyTransactionController::class, 'show'])->middleware('permission:loyalty-transactions');
        });
        Route::get('/customers/{customerId}/loyalty-transactions', [LoyaltyTransactionController::class, 'customerHistory']);

        // Customer credits Routes
        Route::prefix('credit-transactions')->group(function () {
            Route::get('/', [CustomerCreditTransactionController::class, 'index']);
            Route::post('/record-payment', [CustomerCreditTransactionController::class, 'recordPayment']);
            Route::post('/record-adjustment', [CustomerCreditTransactionController::class, 'recordAdjustment']);
            Route::post('/record-write-off', [CustomerCreditTransactionController::class, 'recordWriteOff']);
            Route::get('/analytics/overview', [CustomerCreditTransactionController::class, 'analytics']);
            Route::get('/{id}', [CustomerCreditTransactionController::class, 'show'])->middleware('permission:credit-management');
        });
        Route::get('/customers/{customerId}/credit-transactions', [CustomerCreditTransactionController::class, 'customerHistory']);

        // Supplier Payment routes
        Route::prefix('supplier-payments')->group(function () {
            Route::post('/', [SupplierPaymentController::class, 'store']);
            Route::get('/', [SupplierPaymentController::class, 'index']);
            Route::get('/{id}', [SupplierPaymentController::class, 'show']);
        });
        Route::get('suppliers/{supplierId}/payments', [SupplierPaymentController::class, 'supplierPayments']);
        Route::get('suppliers/{supplierId}/payment-summary', [SupplierPaymentController::class, 'supplierPaymentSummary']);
        Route::get('purchase-orders/{poId}/payments', [SupplierPaymentController::class, 'purchaseOrderPayments']);

        // Stock Alerts
        Route::prefix('stock-alerts')->group(function () {
            Route::get('/', [StockAlertController::class, 'index']);
            Route::get('/{id}', [StockAlertController::class, 'show']);
            Route::post('/{id}/resolve', [StockAlertController::class, 'resolve']);
        });

        // Expiry Alerts
        Route::prefix('expiry-alerts')->group(function () {
            Route::get('/', [ExpiryAlertController::class, 'index']);
            Route::get('/{id}', [ExpiryAlertController::class, 'show']);
            Route::post('/{id}/resolve', [ExpiryAlertController::class, 'resolve']);
        });

        // Inventory Waste
        Route::prefix('inventory-waste')->group(function () {
            Route::get('/', [InventoryWasteController::class, 'index']);
            Route::post('/', [InventoryWasteController::class, 'store']);
            Route::get('/{id}', [InventoryWasteController::class, 'show']);
            Route::patch('/{id}', [InventoryWasteController::class, 'update']);
            Route::delete('/{id}', [InventoryWasteController::class, 'destroy']);
            Route::post('/{id}/approve', [InventoryWasteController::class, 'approve']);
            Route::post('/{id}/reject', [InventoryWasteController::class, 'reject']);
        });

        // Store-specific routes
        Route::prefix('stores/{storeId}')->group(function () {
            // Stock Alerts by Store
            Route::get('/stock-alerts', [StockAlertController::class, 'byStore']);
            Route::get('/stock-alerts/summary', [StockAlertController::class, 'summary']);
            Route::get('/stock-alerts/dashboard', [StockAlertController::class, 'dashboard']);

            // Expiry Alerts by Store
            Route::get('/expiry-alerts', [ExpiryAlertController::class, 'byStore']);
            Route::get('/expiry-alerts/summary', [ExpiryAlertController::class, 'summary']);
            Route::get('/expiry-alerts/dashboard', [ExpiryAlertController::class, 'dashboard']);

            // Waste Summary by Store
            Route::get('/inventory-waste/summary', [InventoryWasteController::class, 'summary']);
        });

        // Daily sales aggregates
        Route::prefix('reports/daily-sales')->group(function () {
            Route::get('/', [DailySalesReportController::class, 'index']);
            Route::get('/range', [DailySalesReportController::class, 'range']);
            Route::get('/summary', [DailySalesReportController::class, 'summary']);
            Route::get('/top-selling', [DailySalesReportController::class, 'topSelling']);
            Route::get('/top-revenue', [DailySalesReportController::class, 'topRevenue']);
            Route::get('/by-category', [DailySalesReportController::class, 'byCategory']);
            Route::post('/recalculate', [DailySalesReportController::class, 'recalculate']);
        });

        // Price History Routes
        Route::prefix('price-history')->group(function () {
            Route::get('/', [ProductPriceHistoryController::class, 'index']);
            Route::get('/products/{product}', [ProductPriceHistoryController::class, 'show']);
        });

        // Audit logs routes
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/statistics', [AuditLogController::class, 'statistics']);
            Route::get('/grouped-summary', [AuditLogController::class, 'groupedSummary']);
            Route::get('/recent-activity', [AuditLogController::class, 'recentActivity']);
            Route::get('/available-filters', [AuditLogController::class, 'availableFilters']);
        });
    });

// Protected tenant routes requires authentication
Route::prefix('v1/tenant')
    ->middleware(['auth:tenant'])
    ->group(function () {

        // Tenant Authentication - Protected
        Route::prefix('auth')->group(function () {
            Route::get('/me', [TenantAuthController::class, 'me']);
            Route::post('/logout', [TenantAuthController::class, 'logout']);
            Route::post('/update-password', [TenantAuthController::class, 'updatePassword']);
        });

        // Tenant Access Status (Check eligibility)
        Route::prefix('access')->group(function () {
            Route::get('/status', [TenantAccessController::class, 'checkStatus']);
        });

        // Subscription Information
        Route::prefix('subscription')->group(function () {
            Route::get('/info', [TenantAccessController::class, 'subscriptionInfo']);
        });

        // Business Types & Categories (Helper endpoints)
        Route::get('/business-types', [BusinessHelperController::class, 'index']);
        Route::get('/business-types/{typeId}/categories', [BusinessHelperController::class, 'categories']);

        // Business Details Submission
        Route::prefix('business-details')->group(function () {
            Route::post('/', [BusinessDetailsController::class, 'submit']);
            Route::get('/', [BusinessDetailsController::class, 'show']);

            // Granular Updates
            Route::middleware(['role:owner,tenant'])->group(function () {
                Route::patch('/profile', [BusinessDetailsController::class, 'updateProfile']);
                Route::post('/media', [BusinessDetailsController::class, 'updateMedia']);
                Route::patch('/location', [BusinessDetailsController::class, 'updateLocation']);
                Route::patch('/operating-hours', [BusinessDetailsController::class, 'updateOperatingHours']);
                Route::patch('/delivery-info', [BusinessDetailsController::class, 'updateDeliveryInfo']);
                Route::patch('/settings', [BusinessDetailsController::class, 'updateSettings']);
                Route::patch('/social-media', [BusinessDetailsController::class, 'updateSocialMedia']);
            });
        });
    });
