<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Sales\ShiftSalesSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShiftAssignment;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSalesSummaryController extends Controller
{
    public function __construct(
        protected ShiftSalesSummaryService $summaryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts/{shiftId}/sales-summary",
     *     summary="Get sales summary for a shift",
     *     description="Retrieves comprehensive sales summary for a specific shift, including transaction counts, sales amounts by payment method, cash tracking, customer metrics, and payment method breakdown percentages. The closing_cash, cash_variance, and sales_per_hour fields will be null if the shift is still active and only populated after clock-out.",
     *     operationId="getShiftSalesSummary",
     *     tags={"Shift Sales Summary"},
     *     @OA\Parameter(
     *         name="shiftId",
     *         in="path",
     *         description="The unique identifier of the shift",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=4
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift sales summary retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "message", "data", "meta"},
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Indicates whether the request was successful",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Human-readable message describing the result",
     *                 example="Shift sales summary retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"total_transactions", "total_sales_amount", "total_cash_sales", "total_card_sales", "total_mpesa_sales", "total_credit_sales", "total_discounts_given", "unique_customers", "average_transaction_value", "opening_cash", "expected_cash", "closing_cash", "cash_variance", "has_significant_variance", "sales_per_hour", "payment_method_breakdown"},
     *                 description="Sales summary data for the shift",
     *                 @OA\Property(
     *                     property="total_transactions",
     *                     type="integer",
     *                     description="Total number of transactions processed during the shift",
     *                     example=9
     *                 ),
     *                 @OA\Property(
     *                     property="total_sales_amount",
     *                     type="number",
     *                     format="double",
     *                     description="Total sales amount across all payment methods",
     *                     example=1494350
     *                 ),
     *                 @OA\Property(
     *                     property="total_cash_sales",
     *                     type="number",
     *                     format="double",
     *                     description="Total sales amount paid in cash",
     *                     example=577875
     *                 ),
     *                 @OA\Property(
     *                     property="total_card_sales",
     *                     type="number",
     *                     format="double",
     *                     description="Total sales amount paid by card",
     *                     example=0
     *                 ),
     *                 @OA\Property(
     *                     property="total_mpesa_sales",
     *                     type="number",
     *                     format="double",
     *                     description="Total sales amount paid via M-Pesa",
     *                     example=249975
     *                 ),
     *                 @OA\Property(
     *                     property="total_credit_sales",
     *                     type="number",
     *                     format="double",
     *                     description="Total sales amount on credit",
     *                     example=370000
     *                 ),
     *                 @OA\Property(
     *                     property="total_discounts_given",
     *                     type="number",
     *                     format="double",
     *                     description="Total amount of discounts given during the shift",
     *                     example=9500
     *                 ),
     *                 @OA\Property(
     *                     property="unique_customers",
     *                     type="integer",
     *                     description="Number of unique customers served during the shift",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="average_transaction_value",
     *                     type="number",
     *                     format="double",
     *                     description="Average value per transaction (total_sales_amount / total_transactions)",
     *                     example=166038.89
     *                 ),
     *                 @OA\Property(
     *                     property="opening_cash",
     *                     type="number",
     *                     format="double",
     *                     description="The amount of cash at the beginning of the shift",
     *                     example=5000
     *                 ),
     *                 @OA\Property(
     *                     property="expected_cash",
     *                     type="number",
     *                     format="double",
     *                     description="The expected cash amount (opening_cash + total_cash_sales)",
     *                     example=582875
     *                 ),
     *                 @OA\Property(
     *                     property="closing_cash",
     *                     type="number",
     *                     format="double",
     *                     nullable=true,
     *                     description="The actual cash amount counted at shift close (null if shift is still active)",
     *                     example=582875
     *                 ),
     *                 @OA\Property(
     *                     property="cash_variance",
     *                     type="number",
     *                     format="double",
     *                     nullable=true,
     *                     description="The difference between expected and actual closing cash (null if shift is still active)",
     *                     example=0
     *                 ),
     *                 @OA\Property(
     *                     property="has_significant_variance",
     *                     type="boolean",
     *                     description="Indicates whether the cash variance exceeds the configured threshold",
     *                     example=false
     *                 ),
     *                 @OA\Property(
     *                     property="sales_per_hour",
     *                     type="number",
     *                     format="double",
     *                     nullable=true,
     *                     description="Average sales per hour (null if shift is still active)",
     *                     example=172757.23
     *                 ),
     *                 @OA\Property(
     *                     property="payment_method_breakdown",
     *                     type="object",
     *                     required={"cash", "card", "mpesa", "credit"},
     *                     description="Percentage breakdown of sales by payment method",
     *                     @OA\Property(
     *                         property="cash",
     *                         type="number",
     *                         format="double",
     *                         description="Percentage of sales paid in cash",
     *                         example=38.67
     *                     ),
     *                     @OA\Property(
     *                         property="card",
     *                         type="number",
     *                         format="double",
     *                         description="Percentage of sales paid by card",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="mpesa",
     *                         type="number",
     *                         format="double",
     *                         description="Percentage of sales paid via M-Pesa",
     *                         example=16.73
     *                     ),
     *                     @OA\Property(
     *                         property="credit",
     *                         type="number",
     *                         format="double",
     *                         description="Percentage of sales on credit",
     *                         example=24.76
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 required={"timestamp", "request_id", "tenant_id", "tenant_name"},
     *                 description="Metadata about the API request and response",
     *                 @OA\Property(
     *                     property="timestamp",
     *                     type="string",
     *                     format="date-time",
     *                     description="The timestamp when the response was generated in ISO 8601 format",
     *                     example="2026-01-09T21:12:08.547405Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     description="Unique identifier for tracking this specific request",
     *                     example="9a23a51c-4a1e-4f26-883c-32cddbef2bf8"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_id",
     *                     type="string",
     *                     format="uuid",
     *                     description="Unique identifier of the tenant",
     *                     example="bbab2597-e1ae-466b-a071-83033841d2ed"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_name",
     *                     type="string",
     *                     nullable=true,
     *                     description="Name of the tenant (null if not available)",
     *                     example=null
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shift not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Shift not found"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthorized"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission to access this shift",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="You do not have permission to access this shift"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="An error occurred while retrieving shift sales summary"
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(ShiftAssignment $shiftAssignment): JsonResponse
    {
        $metrics = $this->summaryService->getShiftMetrics($shiftAssignment);

        return ApiResponse::success('Shift sales summary retrieved successfully', $metrics);
    }

    public function recalculate(ShiftAssignment $shiftAssignment): JsonResponse
    {
        $summary = $this->summaryService->recalculateSummary($shiftAssignment->id);

        return ApiResponse::success(
            'Summary recalculated successfully',
            new ShiftSalesSummaryResource($summary)
        );
    }

    public function cashReconciliation(ShiftAssignment $shiftAssignment): JsonResponse
    {
        $summary = $shiftAssignment->salesSummary;
        $expectedCash = $this->summaryService->calculateExpectedCash($shiftAssignment);
        $variance = $this->summaryService->getCashVariance($shiftAssignment);
        $hasSignificantVariance = $this->summaryService->hasSignificantCashVariance($shiftAssignment);

        $data = [
            'opening_cash' => (float) ($shiftAssignment->opening_cash ?? 0),
            'total_cash_sales' => $summary ? (float) $summary->total_cash_sales : 0,
            'expected_cash' => $expectedCash,
            'closing_cash' => $shiftAssignment->closing_cash ? (float) $shiftAssignment->closing_cash : null,
            'variance' => $variance,
            'variance_percentage' => $expectedCash > 0 && $variance !== null
                ? round(($variance / $expectedCash) * 100, 2)
                : null,
            'is_significant' => $hasSignificantVariance,
            'threshold' => 100.00,
            'requires_explanation' => $hasSignificantVariance,
        ];

        return ApiResponse::success('Cash reconciliation details retrieved successfully', $data);
    }
}
