<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sales\CalculateSaleRequest;
use App\Http\Requests\Tenant\Sales\CreateSaleRequest;
use App\Http\Requests\Tenant\Sales\ListSalesRequest;
use App\Http\Requests\Tenant\Sales\SearchCustomerRequest;
use App\Http\Resources\Tenant\Sales\CustomerSearchResource;
use App\Http\Resources\Tenant\Sales\SaleCalculationResource;
use App\Http\Resources\Tenant\Sales\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Sale;
use App\Services\Tenant\Sales\SaleCalculationService;
use App\Services\Tenant\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
        protected SaleCalculationService $calculationService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales/customers/search",
     *     summary="Search customer by phone number",
     *     description="Lookup customer information by phone number for POS transactions. Returns complete customer profile including loyalty points balance (with expiring points), credit information (limit, debt, available credit, overdue status), and purchase history.",
     *     operationId="searchCustomer",
     *     tags={"Sales - Customers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="Customer phone number (10-15 characters). Can include country code with + prefix.",
     *         required=true,
     *         example="0712345678",
     *         @OA\Schema(
     *             type="string",
     *             minLength=10,
     *             maxLength=15
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer found successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "data", "meta"},
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true,
     *                 description="Indicates if the request was successful"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Customer found successfully",
     *                 description="Human-readable success message"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"customer"},
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     required={"id", "customer_number", "name", "phone", "email", "customer_type", "customer_type_label", "loyalty", "credit", "total_lifetime_purchases", "total_visits", "is_active"},
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         example=4,
     *                         description="Unique customer identifier"
     *                     ),
     *                     @OA\Property(
     *                         property="customer_number",
     *                         type="string",
     *                         example="CUST-2025-000003",
     *                         description="Unique customer reference number"
     *                     ),
     *                     @OA\Property(
     *                         property="name",
     *                         type="string",
     *                         example="Jane Smith",
     *                         description="Customer full name"
     *                     ),
     *                     @OA\Property(
     *                         property="phone",
     *                         type="string",
     *                         example="+254712345602",
     *                         description="Customer phone number with country code"
     *                     ),
     *                     @OA\Property(
     *                         property="email",
     *                         type="string",
     *                         format="email",
     *                         nullable=true,
     *                         example="jane.smith@example.com",
     *                         description="Customer email address"
     *                     ),
     *                     @OA\Property(
     *                         property="customer_type",
     *                         type="string",
     *                         enum={"walk_in", "regular", "vip", "wholesale"},
     *                         example="vip",
     *                         description="Customer type classification"
     *                     ),
     *                     @OA\Property(
     *                         property="customer_type_label",
     *                         type="string",
     *                         example="VIP Customer",
     *                         description="Human-readable customer type label"
     *                     ),
     *                     @OA\Property(
     *                         property="loyalty",
     *                         type="object",
     *                         required={"enabled", "balance", "expiring_soon", "expiring_in_days"},
     *                         description="Loyalty program information",
     *                         @OA\Property(
     *                             property="enabled",
     *                             type="boolean",
     *                             example=true,
     *                             description="Whether loyalty program is enabled in tenant configuration"
     *                         ),
     *                         @OA\Property(
     *                             property="balance",
     *                             type="number",
     *                             format="float",
     *                             example=500.00,
     *                             description="Current loyalty points balance"
     *                         ),
     *                         @OA\Property(
     *                             property="expiring_soon",
     *                             type="number",
     *                             format="float",
     *                             example=0.00,
     *                             description="Points expiring within the expiring_in_days window"
     *                         ),
     *                         @OA\Property(
     *                             property="expiring_in_days",
     *                             type="integer",
     *                             example=30,
     *                             description="Number of days window for expiring_soon calculation"
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="credit",
     *                         type="object",
     *                         required={"enabled", "credit_limit", "current_debt", "available_credit", "is_overdue"},
     *                         description="Credit account information",
     *                         @OA\Property(
     *                             property="enabled",
     *                             type="boolean",
     *                             example=true,
     *                             description="Whether credit sales are enabled in tenant configuration"
     *                         ),
     *                         @OA\Property(
     *                             property="credit_limit",
     *                             type="number",
     *                             format="float",
     *                             example=50000.00,
     *                             description="Maximum credit limit assigned to customer"
     *                         ),
     *                         @OA\Property(
     *                             property="current_debt",
     *                             type="number",
     *                             format="float",
     *                             example=5000.00,
     *                             description="Current outstanding debt amount"
     *                         ),
     *                         @OA\Property(
     *                             property="available_credit",
     *                             type="number",
     *                             format="float",
     *                             example=45000.00,
     *                             description="Remaining credit available (credit_limit - current_debt)"
     *                         ),
     *                         @OA\Property(
     *                             property="is_overdue",
     *                             type="boolean",
     *                             example=false,
     *                             description="Whether customer has overdue debt payments"
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="total_lifetime_purchases",
     *                         type="number",
     *                         format="float",
     *                         example=125000.00,
     *                         description="Aggregate total of all purchases by customer"
     *                     ),
     *                     @OA\Property(
     *                         property="total_visits",
     *                         type="integer",
     *                         example=45,
     *                         description="Total number of transactions/visits by customer"
     *                     ),
     *                     @OA\Property(
     *                         property="is_active",
     *                         type="boolean",
     *                         example=true,
     *                         description="Whether customer account is active"
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 required={"timestamp", "request_id", "tenant_id", "tenant_name"},
     *                 description="Response metadata",
     *                 @OA\Property(
     *                     property="timestamp",
     *                     type="string",
     *                     format="date-time",
     *                     example="2026-01-08T07:52:10.087102Z",
     *                     description="ISO 8601 timestamp of response generation"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="d1e89d36-656f-46b8-8c74-d1b76bcbf7be",
     *                     description="Unique request identifier for tracking"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_id",
     *                     type="string",
     *                     format="uuid",
     *                     nullable=true,
     *                     example="bbab2597-e1ae-466b-a071-83033841d2ed",
     *                     description="Tenant UUID in multi-tenant context"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_name",
     *                     type="string",
     *                     nullable=true,
     *                     example=null,
     *                     description="Tenant business name (if configured)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found with provided phone number",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "meta"},
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Customer not found with phone number: 0712345678"
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - invalid phone format",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "errors", "meta"},
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="phone",
     *                     type="array",
     *                     @OA\Items(type="string", example="Phone number is required to search for customer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - invalid or missing bearer token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "meta"},
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to search for customer: Database connection error"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function searchCustomer(SearchCustomerRequest $request): JsonResponse
    {
        try {
            $phone = $request->input('phone');

            $customer = $this->saleService->resolveCustomerByPhone($phone);

            if (!$customer) {
                return ApiResponse::notFound(
                    'Customer not found with phone number: ' . $phone
                );
            }

            return ApiResponse::success(
                'Customer found successfully',
                [
                    'customer' => new CustomerSearchResource($customer),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Customer search failed', [
                'phone' => $request->input('phone'),
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::serverError(
                'Failed to search for customer: ' . $e->getMessage()
            );
        }
    }

    public function calculateSale(CalculateSaleRequest $request): JsonResponse
    {
        try {
            $calculations = $this->calculationService->calculateSaleTotals(
                $request->validated()
            );

            return ApiResponse::success(
                'Sale calculations completed successfully',
                new SaleCalculationResource($calculations)
            );
        } catch (\Exception $e) {
            Log::error('Sale calculation failed', [
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::error(
                'Failed to calculate sale totals: ' . $e->getMessage(),
                null,
                422
            );
        }
    }

    public function createSale(CreateSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->saleService->createSale($request->validated());

            // Load relationships for response
            $sale->load([
                'items.product',
                'items.productVariant',
                'items.bundle',
                'items.uom',
                'payments.receivedBy',
                'customer',
                'store',
                'coupon',
                'servedBy',
            ]);

            // Get updated customer data if customer exists
            $customerUpdated = null;
            if ($sale->customer_id) {
                $customer = $sale->customer->fresh();
                $customerUpdated = [
                    'loyalty_balance' => (float) $customer->loyalty_points,
                    'total_lifetime_purchases' => (float) $customer->total_lifetime_purchases,
                    'total_visits' => $customer->total_visits,
                ];
            }

            return ApiResponse::created(
                'Sale completed successfully',
                [
                    'sale' => new SaleResource($sale),
                    'customer_updated' => $customerUpdated,
                ]
            );
        } catch (\RuntimeException $e) {
            // Business logic errors (validation, insufficient stock, etc.)
            Log::warning('Sale creation failed - business logic', [
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::error(
                $e->getMessage(),
                null,
                422
            );
        } catch (\Exception $e) {
            // Unexpected errors
            Log::error('Sale creation failed - system error', [
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::serverError(
                'Failed to create sale. Please try again or contact support.'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales/{id}",
     *     summary="Get sale details by ID",
     *     description="Retrieve complete sale transaction details including all items, payments, customer information, loyalty transactions, and coupon/promotion usage. Returns fully hydrated sale object with all relationships eager-loaded for display on receipt screens, sale history, or reporting.",
     *     operationId="getSale",
     *     tags={"Sales - Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Sale ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sale retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "data", "meta"},
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sale retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"sale"},
     *                 @OA\Property(
     *                     property="sale",
     *                     type="object",
     *                     required={"id", "sale_number", "sale_date", "store", "customer", "items", "payments", "summary", "payment_status", "payment_status_label", "payment_method", "payment_method_label", "payment_reference", "loyalty", "served_by", "notes", "can_refund", "is_walk_in", "created_at", "updated_at"},
     *                     @OA\Property(property="id", type="integer", example=6, description="Unique sale identifier"),
     *                     @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000003", description="Unique sale reference number"),
     *                     @OA\Property(property="sale_date", type="string", format="date-time", example="2026-01-08T11:51:46+03:00", description="Sale transaction timestamp"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         required={"id", "name", "code"},
     *                         description="Store where sale was made",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="customer",
     *                         type="object",
     *                         nullable=true,
     *                         description="Customer details - null for walk-in sales",
     *                         required={"id", "customer_number", "name", "phone"},
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="customer_number", type="string", example="CUST-2025-000003"),
     *                         @OA\Property(property="name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="phone", type="string", example="+254712345602")
     *                     ),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         description="Sale line items with full product details",
     *                         @OA\Items(
     *                             type="object",
     *                             required={"id", "product", "variant", "display_name", "uom", "quantity", "quantity_in_base_uom", "unit_price", "line_total_before_tax", "discount_amount", "tax_amount", "subtotal", "effective_unit_price"},
     *                             @OA\Property(property="id", type="integer", example=7, description="Sale item record ID"),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 nullable=true,
     *                                 description="Product details - null for bundle-only items",
     *                                 required={"id", "name", "sku"},
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                             ),
     *                             @OA\Property(
     *                                 property="variant",
     *                                 type="object",
     *                                 nullable=true,
     *                                 description="Product variant details if applicable",
     *                                 required={"id", "name", "sku"},
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="name", type="string", example="55C725-GAL"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                             ),
     *                             @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL", description="Product name with variant (if any)"),
     *                             @OA\Property(
     *                                 property="uom",
     *                                 type="object",
     *                                 required={"id", "code", "name"},
     *                                 description="Unit of measure used in sale",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="code", type="string", example="pair"),
     *                                 @OA\Property(property="name", type="string", example="Pair")
     *                             ),
     *                             @OA\Property(property="quantity", type="number", format="float", example=1.0, description="Quantity sold in UOM"),
     *                             @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=1.0, description="Quantity in product's base UOM (for inventory tracking)"),
     *                             @OA\Property(property="unit_price", type="number", format="float", example=151999.00, description="Price per UOM at time of sale (before discount)"),
     *                             @OA\Property(property="line_total_before_tax", type="number", format="float", example=151999.00, description="quantity × unit_price"),
     *                             @OA\Property(property="discount_amount", type="number", format="float", example=3000.00, description="Total discount applied to this line (promotions + coupons)"),
     *                             @OA\Property(property="tax_amount", type="number", format="float", example=23839.84, description="Tax on line after discounts"),
     *                             @OA\Property(property="subtotal", type="number", format="float", example=172838.84, description="Final line total: (line_total_before_tax - discount_amount) + tax_amount"),
     *                             @OA\Property(property="effective_unit_price", type="number", format="float", example=148999.00, description="unit_price after discount per unit")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="payments",
     *                         type="array",
     *                         description="Payment records for this sale. Empty for credit sales where payment is deferred.",
     *                         @OA\Items(
     *                             type="object",
     *                             required={"id", "amount", "payment_method", "payment_method_label", "reference_number", "payment_date", "received_by", "notes", "is_electronic"},
     *                             @OA\Property(property="id", type="integer", example=5, description="Payment record ID"),
     *                             @OA\Property(property="amount", type="number", format="float", example=172839.00, description="Payment amount"),
     *                             @OA\Property(property="payment_method", type="string", example="cash", enum={"cash", "mpesa", "card", "bank_transfer", "credit", "mixed", "other"}),
     *                             @OA\Property(property="payment_method_label", type="string", example="Cash", description="Human-readable payment method"),
     *                             @OA\Property(property="reference_number", type="string", nullable=true, example=null, description="Transaction reference (e.g., M-Pesa code, card approval)"),
     *                             @OA\Property(property="payment_date", type="string", format="date-time", example="2026-01-08T11:51:46+03:00"),
     *                             @OA\Property(
     *                                 property="received_by",
     *                                 type="object",
     *                                 required={"id", "name"},
     *                                 description="User who received payment",
     *                                 @OA\Property(property="id", type="integer", example=3),
     *                                 @OA\Property(property="name", type="string", example="Jane Cashier")
     *                             ),
     *                             @OA\Property(property="notes", type="string", nullable=true, example=null, description="Payment notes"),
     *                             @OA\Property(property="is_electronic", type="boolean", example=false, description="Whether payment method is electronic (mpesa, card, bank_transfer)")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="summary",
     *                         type="object",
     *                         required={"subtotal", "tax_amount", "discount_amount", "total_amount", "amount_paid", "amount_due"},
     *                         description="Sale financial summary",
     *                         @OA\Property(property="subtotal", type="number", format="float", example=148999.00, description="Sum of line items before tax (after discounts)"),
     *                         @OA\Property(property="tax_amount", type="number", format="float", example=23839.84, description="Total tax on sale"),
     *                         @OA\Property(property="discount_amount", type="number", format="float", example=3000.00, description="Total discounts (promotions + coupons)"),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=172838.84, description="Grand total: subtotal + tax_amount"),
     *                         @OA\Property(property="amount_paid", type="number", format="float", example=172839.00, description="Total amount paid (sum of payments + loyalty redemption)"),
     *                         @OA\Property(property="amount_due", type="number", format="float", example=0.00, description="Outstanding balance: total_amount - amount_paid")
     *                     ),
     *                     @OA\Property(property="payment_status", type="string", example="paid", enum={"paid", "unpaid", "partially_paid"}, description="Payment completion status"),
     *                     @OA\Property(property="payment_status_label", type="string", example="Fully Paid", description="Human-readable payment status"),
     *                     @OA\Property(property="payment_method", type="string", example="cash", description="Primary payment method (mixed if multiple methods)"),
     *                     @OA\Property(property="payment_method_label", type="string", example="Cash", description="Human-readable payment method"),
     *                     @OA\Property(property="payment_reference", type="string", nullable=true, example=null, description="Primary payment reference number"),
     *                     @OA\Property(
     *                         property="loyalty",
     *                         type="object",
     *                         required={"points_earned", "points_redeemed"},
     *                         description="Loyalty transaction details",
     *                         @OA\Property(property="points_earned", type="number", format="float", example=1728.39, description="Loyalty points earned from this sale"),
     *                         @OA\Property(property="points_redeemed", type="number", format="float", example=0.00, description="Loyalty points redeemed in this sale")
     *                     ),
     *                     @OA\Property(
     *                         property="served_by",
     *                         type="object",
     *                         required={"id", "name"},
     *                         description="Cashier/user who processed the sale",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier")
     *                     ),
     *                     @OA\Property(property="notes", type="string", nullable=true, example=null, description="Additional sale notes"),
     *                     @OA\Property(property="can_refund", type="boolean", example=true, description="Whether sale is eligible for refund (business rules check)"),
     *                     @OA\Property(property="is_walk_in", type="boolean", example=false, description="Whether sale has no associated customer (walk-in purchase)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-08T11:51:46+03:00", description="Sale creation timestamp"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-08T11:51:46+03:00", description="Last update timestamp")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 required={"timestamp", "request_id", "tenant_id", "tenant_name"},
     *                 description="Response metadata",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-08T09:09:33.420205Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c747ee18-e68f-4320-aba0-1bba90125e4c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true, example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sale not found",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "meta"},
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sale not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - user does not have permission to view this sale",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve sale details"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getSale(Sale $sale): JsonResponse
    {
        try {
            // Load all relationships
            $sale->load([
                'items.product',
                'items.productVariant',
                'items.bundle',
                'items.uom',
                'items.taxRate',
                'payments.receivedBy',
                'customer',
                'store',
                'coupon',
                'servedBy',
            ]);

            return ApiResponse::success(
                'Sale retrieved successfully',
                [
                    'sale' => new SaleResource($sale),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve sale', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::serverError(
                'Failed to retrieve sale details'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales",
     *     summary="List sales with filters and pagination",
     *     description="Retrieve paginated list of sales transactions with support for multiple filters: store, customer, payment status, date range, and text search. Returns sales with basic information - use GET /sales/{id} for complete details. Supports sorting and configurable page size (1-100 records per page).",
     *     operationId="listSales",
     *     tags={"Sales - Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"paid", "unpaid", "partially_paid"},
     *             example="paid"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"cash", "mpesa", "card", "bank_transfer", "credit", "mixed", "other"},
     *             example="cash"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for date range filter (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for date range filter (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by sale number, customer name, or customer phone. Minimum 3 characters.",
     *         required=false,
     *         @OA\Schema(type="string", minLength=3, example="Jane")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"sale_date", "total_amount", "sale_number"},
     *             default="sale_date",
     *             example="sale_date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc",
     *             example="desc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of records per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sales retrieved successfully with pagination",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "data", "meta"},
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"sales", "pagination"},
     *                 @OA\Property(
     *                     property="sales",
     *                     type="array",
     *                     description="Array of sales matching filters",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"id", "sale_number", "sale_date", "store", "customer", "summary", "payment_status", "payment_status_label", "payment_method", "payment_method_label", "loyalty", "served_by", "can_refund", "is_walk_in", "created_at"},
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000003"),
     *                         @OA\Property(property="sale_date", type="string", format="date-time", example="2026-01-08T11:51:46+03:00"),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             required={"id", "name", "code"},
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             nullable=true,
     *                             description="Null for walk-in customers",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="customer_number", type="string", example="CUST-2025-000003"),
     *                             @OA\Property(property="name", type="string", example="Jane Smith"),
     *                             @OA\Property(property="phone", type="string", example="+254712345602")
     *                         ),
     *                         @OA\Property(
     *                             property="summary",
     *                             type="object",
     *                             required={"subtotal", "tax_amount", "discount_amount", "total_amount", "amount_paid", "amount_due"},
     *                             @OA\Property(property="subtotal", type="number", format="float", example=148999.00),
     *                             @OA\Property(property="tax_amount", type="number", format="float", example=23839.84),
     *                             @OA\Property(property="discount_amount", type="number", format="float", example=3000.00),
     *                             @OA\Property(property="total_amount", type="number", format="float", example=172838.84),
     *                             @OA\Property(property="amount_paid", type="number", format="float", example=172839.00),
     *                             @OA\Property(property="amount_due", type="number", format="float", example=0.00)
     *                         ),
     *                         @OA\Property(property="payment_status", type="string", example="paid", enum={"paid", "unpaid", "partially_paid"}),
     *                         @OA\Property(property="payment_status_label", type="string", example="Fully Paid"),
     *                         @OA\Property(property="payment_method", type="string", example="cash"),
     *                         @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                         @OA\Property(
     *                             property="loyalty",
     *                             type="object",
     *                             required={"points_earned", "points_redeemed"},
     *                             @OA\Property(property="points_earned", type="number", format="float", example=1728.39),
     *                             @OA\Property(property="points_redeemed", type="number", format="float", example=0.00)
     *                         ),
     *                         @OA\Property(
     *                             property="served_by",
     *                             type="object",
     *                             required={"id", "name"},
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier")
     *                         ),
     *                         @OA\Property(property="can_refund", type="boolean", example=true),
     *                         @OA\Property(property="is_walk_in", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-08T11:51:46+03:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     required={"current_page", "last_page", "per_page", "total", "from", "to"},
     *                     description="Pagination metadata",
     *                     @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
     *                     @OA\Property(property="last_page", type="integer", example=5, description="Total number of pages"),
     *                     @OA\Property(property="per_page", type="integer", example=15, description="Records per page"),
     *                     @OA\Property(property="total", type="integer", example=73, description="Total number of records"),
     *                     @OA\Property(property="from", type="integer", example=1, description="First record number on this page"),
     *                     @OA\Property(property="to", type="integer", example=15, description="Last record number on this page")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 required={"timestamp", "request_id", "tenant_id", "tenant_name"},
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-08T09:15:20.123456Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="abc12345-1234-5678-9abc-def012345678"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true, example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - invalid filter parameters",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "errors", "meta"},
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="The per page must be between 1 and 100.")
     *                 ),
     *                 @OA\Property(
     *                     property="from_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The from date must be a valid date.")
     *                 ),
     *                 @OA\Property(
     *                     property="search",
     *                     type="array",
     *                     @OA\Items(type="string", example="Search query must be at least 3 characters")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve sales list"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function listSales(ListSalesRequest $request): JsonResponse
    {
        try {
            $query = Sale::query()->withDetails();

            // Apply filters
            if ($request->filled('store_id')) {
                $query->byStore($request->input('store_id'));
            }

            if ($request->filled('customer_id')) {
                $query->byCustomer($request->input('customer_id'));
            }

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->input('payment_status'));
            }

            if ($request->filled('from_date') || $request->filled('to_date')) {
                $query->byDateRange(
                    $request->input('from_date'),
                    $request->input('to_date')
                );
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('sale_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            }

            // Order by most recent
            $query->recent();

            // Paginate
            $perPage = $request->input('per_page', 15);
            $sales = $query->paginate($perPage);

            return ApiResponse::paginated(
                SaleResource::collection($sales),
                'Sales retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to list sales', [
                'filters' => $request->validated(),
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::serverError(
                'Failed to retrieve sales list'
            );
        }
    }

    public function generateReceipt(Sale $sale): JsonResponse
    {
        // TODO: Implement receipt generation
        // This would typically use a PDF library like DomPDF or generate HTML

        return ApiResponse::success(
            'Receipt generation endpoint',
            [
                'message' => 'Receipt generation not yet implemented',
                'sale_number' => $sale->sale_number,
            ]
        );
    }
}
