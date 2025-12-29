<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Auth\TenantAuthController;
use App\Http\Controllers\Api\Tenant\Business\BusinessDetailsController;
use App\Http\Controllers\Api\Tenant\Business\BusinessHelperController;
use App\Http\Controllers\Api\Tenant\Customer\CustomerController;
use App\Http\Controllers\Api\Tenant\Customer\CustomerGroupController;
use App\Http\Controllers\Api\Tenant\Expenses\ExpenseCategoryController;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryMovementController;
use App\Http\Controllers\Api\Tenant\Inventory\ProductBatchController;
use App\Http\Controllers\Api\Tenant\Inventory\PurchaseOrderController;
use App\Http\Controllers\Api\Tenant\Inventory\StockTransferController;
use App\Http\Controllers\Api\Tenant\Offers\CouponController;
use App\Http\Controllers\Api\Tenant\Product\ProductBrandController;
use App\Http\Controllers\Api\Tenant\Product\ProductBundleController;
use App\Http\Controllers\Api\Tenant\Product\ProductCategoryController;
use App\Http\Controllers\Api\Tenant\Product\ProductController;
use App\Http\Controllers\Api\Tenant\Product\ProductUomController;
use App\Http\Controllers\Api\Tenant\Product\ProductVariantController;
use App\Http\Controllers\Api\Tenant\Store\StoreController;
use App\Http\Controllers\Api\Tenant\Store\StoreProductController;
use App\Http\Controllers\Api\Tenant\Supplier\SupplierController;
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
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/{customer}', [CustomerController::class, 'show']);
            Route::patch('/{customer}', [CustomerController::class, 'update']);
            Route::delete('/{customer}', [CustomerController::class, 'destroy']);
            Route::post('/{customer}/restore', [CustomerController::class, 'restore']);
            Route::patch('/{customer}/upgrade-type', [CustomerController::class, 'upgradeType']);
            Route::patch('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
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
