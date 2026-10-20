<?php

namespace App\Http\Controllers\OpenApi;

/**
 * OpenAPI entries for routes documented here rather than on their controllers: server-to-server
 * sync calls (tenant ↔ central, shared bearer token), M-Pesa callbacks (Safaricom → Poachy, secret
 * last path segment) and a few older tenant routes. RouteContractTest keeps every API route documented.
 *
 * @OA\SecurityScheme(
 *     securityScheme="syncToken",
 *     type="http",
 *     scheme="bearer",
 *     description="Server to server only: CENTRAL_API_TOKEN (tenant → central) or TENANT_API_TOKEN (central → tenant)"
 * )
 *
 * @OA\Tag(name="Internal - Sync", description="Tenant ↔ central synchronisation (never called by the website or the app)")
 * @OA\Tag(name="M-Pesa Callbacks", description="Safaricom posts results here; the last path segment is a secret derived from APP_KEY (App\\Support\\MpesaCallbacks), optionally limited to MPESA_CALLBACK_IPS")
 */
class AdditionalPaths
{
    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace-orders/{orderId}/delivery/location",
     *     summary="Rider location ping for a marketplace delivery (tenant → central)",
     *     operationId="extraPostCentralMarketplaceOrdersOrderidDeliveryLocation",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Saved"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op0(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/c2b/validate/{token}",
     *     summary="C2B validation (Safaricom → Poachy): accept or reject a Paybill payment before it completes",
     *     operationId="extraPostCentralMpesaC2bValidateToken",
     *     tags={"M-Pesa Callbacks"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="ResultCode 0 to accept, C2B00011/C2B00013 to reject"),
     *     @OA\Response(response=403, description="Wrong secret, or caller not in MPESA_CALLBACK_IPS")
     * )
     */
    public function op1(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/c2b/confirm/{token}",
     *     summary="C2B confirmation (Safaricom → Poachy): a completed Paybill payment credits the POA account's balance (idempotent on TransID)",
     *     operationId="extraPostCentralMpesaC2bConfirmToken",
     *     tags={"M-Pesa Callbacks"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="ResultCode 0"),
     *     @OA\Response(response=403, description="Wrong secret, or caller not in MPESA_CALLBACK_IPS")
     * )
     */
    public function op2(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/stk/callback/{token}",
     *     summary="STK result for a marketplace order payment",
     *     operationId="extraPostCentralMpesaStkCallbackToken",
     *     tags={"M-Pesa Callbacks"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="ResultCode 0"),
     *     @OA\Response(response=403, description="Wrong secret, or caller not in MPESA_CALLBACK_IPS")
     * )
     */
    public function op3(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/stk/subscription/callback/{token}",
     *     summary="STK result for a subscription payment",
     *     operationId="extraPostCentralMpesaStkSubscriptionCallbackToken",
     *     tags={"M-Pesa Callbacks"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="ResultCode 0"),
     *     @OA\Response(response=403, description="Wrong secret, or caller not in MPESA_CALLBACK_IPS")
     * )
     */
    public function op4(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/stk/billing/callback/{token}",
     *     summary="STK result for a Poachy balance top-up (and the advert it pays for)",
     *     operationId="extraPostCentralMpesaStkBillingCallbackToken",
     *     tags={"M-Pesa Callbacks"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="ResultCode 0"),
     *     @OA\Response(response=403, description="Wrong secret, or caller not in MPESA_CALLBACK_IPS")
     * )
     */
    public function op5(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/products/{productId}/referrers",
     *     summary="Where a product's views came from (referrers)",
     *     operationId="extraGetCentralReportsProductsProductidReferrers",
     *     tags={"Central - Analytics"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Referrers with counts"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op6(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/search/refinements",
     *     summary="Search refinements: what buyers searched next",
     *     operationId="extraGetCentralReportsSearchRefinements",
     *     tags={"Central - Analytics"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Refinement pairs with counts"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op7(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/product",
     *     summary="Product listing sync (tenant → central)",
     *     operationId="extraPostCentralSyncInboundProduct",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back to the tenant"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op8(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/variant",
     *     summary="Variant listing sync (tenant → central)",
     *     operationId="extraPostCentralSyncInboundVariant",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op9(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/bundle",
     *     summary="Bundle listing sync (tenant → central)",
     *     operationId="extraPostCentralSyncInboundBundle",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op10(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/inventory-count",
     *     summary="Stock count sync (tenant → central)",
     *     operationId="extraPostCentralSyncInboundInventoryCount",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op11(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/delivery-zone",
     *     summary="Delivery zone sync (tenant → central)",
     *     operationId="extraPostCentralSyncInboundDeliveryZone",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op12(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/marketplace-fulfillment",
     *     summary="Online order fulfilment step (tenant → central): accepted / preparing / ready / handed over",
     *     operationId="extraPostCentralSyncInboundMarketplaceFulfillment",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op13(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/order-confirmation",
     *     summary="The shop confirmed (held stock for) an online order",
     *     operationId="extraPostCentralSyncInboundOrderConfirmation",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op14(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/reservation-confirmation",
     *     summary="The shop held (or could not hold) stock for a reserve & collect code",
     *     operationId="extraPostCentralSyncInboundReservationConfirmation",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op15(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/outbound-sync-ack",
     *     summary="Tenant acknowledges a central → tenant sync (payment, cancellation, reservation)",
     *     operationId="extraPostCentralSyncInboundOutboundSyncAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op16(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/product-review-response",
     *     summary="A shop's reply to a product review (tenant → central)",
     *     operationId="extraPostCentralSyncInboundProductReviewResponse",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Queued; ACK sent back"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op17(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/sync/inbound/{syncId}/status",
     *     summary="Status of one inbound sync",
     *     operationId="extraGetCentralSyncInboundSyncidStatus",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Parameter(name="syncId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Sync status"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op18(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/tenants/{tenant_id}/unmapped-categories",
     *     summary="A shop's categories not yet mapped to marketplace categories",
     *     operationId="extraGetCentralTenantsTenantIdUnmappedCategories",
     *     tags={"Central - Admin"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="tenant_id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Unmapped categories"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op19(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/batches/cogs/calculate",
     *     summary="Cost of goods sold from batches (FIFO) for a product and quantity",
     *     operationId="extraGetTenantBatchesCogsCalculate",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="COGS breakdown"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op20(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/batches/expired/mark",
     *     summary="Mark batches past their expiry date as expired",
     *     operationId="extraPostTenantBatchesExpiredMark",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Batches marked"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op21(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/batches/valuation/calculate",
     *     summary="Stock valuation from batches",
     *     operationId="extraGetTenantBatchesValuationCalculate",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Valuation"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op22(): void {}

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/brands/{brand}/unfeature",
     *     summary="Stop featuring a brand",
     *     operationId="extraPatchTenantBrandsBrandUnfeature",
     *     tags={"Tenant - Brands"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="brand", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Brand"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op23(): void {}

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/customer-groups/{customer_group}",
     *     summary="Delete a customer group",
     *     operationId="extraDeleteTenantCustomerGroupsCustomerGroup",
     *     tags={"Tenant - Customer Groups"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="customer_group", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op24(): void {}

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/expenses/{expense}/update-recurrence",
     *     summary="Change how a recurring expense repeats",
     *     operationId="extraPatchTenantExpensesExpenseUpdateRecurrence",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="expense", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Expense"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op25(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace-sales/{id}",
     *     summary="One online order as the shop sees it (buyer, delivery, items, next steps)",
     *     operationId="extraGetTenantMarketplaceSalesId",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Marketplace sale"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op26(): void {}

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/marketplace-sales/{id}/location",
     *     summary="Rider's current location for a delivery order",
     *     operationId="extraPatchTenantMarketplaceSalesIdLocation",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Saved"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op27(): void {}

    /**
     * @OA\Put(
     *     path="/api/v1/tenant/promotions/{promotion}",
     *     summary="Replace a promotion (same as PATCH)",
     *     operationId="extraPutTenantPromotionsPromotion",
     *     tags={"Tenant - Promotions"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="promotion", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Promotion"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op28(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales/{sale}/receipt",
     *     summary="Printable receipt for a sale",
     *     operationId="extraGetTenantSalesSaleReceipt",
     *     tags={"Tenant - Sales"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="sale", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Receipt"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op29(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts/{shiftAssignment}/cash-reconciliation",
     *     summary="Cash expected vs counted for a shift",
     *     operationId="extraGetTenantShiftsShiftassignmentCashReconciliation",
     *     tags={"Tenant - Shifts"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="shiftAssignment", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Reconciliation"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op30(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shifts/{shiftAssignment}/recalculate-summary",
     *     summary="Rebuild a shift's sales summary",
     *     operationId="extraPostTenantShiftsShiftassignmentRecalculateSummary",
     *     tags={"Tenant - Shifts"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="shiftAssignment", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Summary"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op31(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/approved-review",
     *     summary="An approved buyer review for one of the shop's products (central → tenant)",
     *     operationId="extraPostTenantSyncInboundApprovedReview",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Stored"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op32(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/product-ack",
     *     summary="Central stored a product listing (ACK with central id)",
     *     operationId="extraPostTenantSyncInboundProductAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op33(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/variant-ack",
     *     summary="Central stored a variant listing (ACK)",
     *     operationId="extraPostTenantSyncInboundVariantAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op34(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/bundle-ack",
     *     summary="Central stored a bundle listing (ACK)",
     *     operationId="extraPostTenantSyncInboundBundleAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op35(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/inventory-count-ack",
     *     summary="Central stored a stock count (ACK)",
     *     operationId="extraPostTenantSyncInboundInventoryCountAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op36(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/delivery-zone-ack",
     *     summary="Central stored a delivery zone (ACK)",
     *     operationId="extraPostTenantSyncInboundDeliveryZoneAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op37(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/marketplace-fulfillment-ack",
     *     summary="Central applied a fulfilment step (ACK)",
     *     operationId="extraPostTenantSyncInboundMarketplaceFulfillmentAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op38(): void {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sync/inbound/review-response-ack",
     *     summary="Central stored the shop's review reply (ACK)",
     *     operationId="extraPostTenantSyncInboundReviewResponseAck",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Recorded"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op39(): void {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sync/inbound/categories",
     *     summary="The shop's categories, for central's category mapping (central → tenant)",
     *     operationId="extraGetTenantSyncInboundCategories",
     *     tags={"Internal - Sync"},
     *     security={{"syncToken": {}}},
     *
     *     @OA\Response(response=200, description="Categories"),
     *     @OA\Response(response=403, description="Missing or wrong sync token")
     * )
     */
    public function op40(): void {}

    /**
     * @OA\Put(
     *     path="/api/v1/tenant/users/{userId}",
     *     summary="Replace a staff member's details (same as PATCH)",
     *     operationId="extraPutTenantUsersUserid",
     *     tags={"Tenant - Users"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="User"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function op41(): void {}
}
