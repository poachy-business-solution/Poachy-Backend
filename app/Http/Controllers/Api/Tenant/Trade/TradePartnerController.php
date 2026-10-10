<?php

namespace App\Http\Controllers\Api\Tenant\Trade;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Trade\RecordTradeGoodsRequest;
use App\Http\Requests\Tenant\Trade\RecordTradePaymentRequest;
use App\Http\Requests\Tenant\Trade\RecordTradeReturnRequest;
use App\Http\Requests\Tenant\Trade\StoreTradePartnerRequest;
use App\Http\Requests\Tenant\Trade\TradePriceHintRequest;
use App\Http\Resources\Tenant\Trade\TradeEventResource;
use App\Http\Resources\Tenant\Trade\TradeLineResource;
use App\Http\Resources\Tenant\Trade\TradePartnerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\TradePartner;
use App\Services\Tenant\Trade\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Neighbour shops: who owes whom, the goods still out, and the four trade
 * actions — they took goods, I took goods, goods brought back, paid.
 */
class TradePartnerController extends Controller
{
    public function __construct(protected TradeService $tradeService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/trade-partners",
     *     summary="Shops you trade with, with balances",
     *     description="Each partner with they_owe / you_owe / net and goods still out. filter=owe_you|you_owe|all. Ordered oldest open line first, then by name. totals sums the lot.",
     *     operationId="listTradePartners",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="filter", in="query", @OA\Schema(type="string", enum={"all","owe_you","you_owe"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Partners and totals")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $partners = TradePartner::query()
            ->when($request->query('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->with('openLines.product')
            ->withMax('events', 'occurred_at')
            ->orderBy('name')
            ->get();

        $filter = $request->query('filter', 'all');
        $partners = $partners
            ->filter(fn (TradePartner $partner) => match ($filter) {
                'owe_you' => $partner->balance()['they_owe'] > 0,
                'you_owe' => $partner->balance()['you_owe'] > 0,
                default => true,
            })
            ->sortBy(fn (TradePartner $partner) => $partner->balance()['oldest_open_at'] ?? '9999')
            ->values();

        $balances = $partners->map(fn (TradePartner $partner) => $partner->balance());

        return ApiResponse::success('Trade partners', [
            'partners' => TradePartnerResource::collection($partners),
            'totals' => [
                'they_owe' => round($balances->sum('they_owe'), 2),
                'you_owe' => round($balances->sum('you_owe'), 2),
                'goods_out_quantity' => round($balances->sum('goods_out_quantity'), 4),
                'goods_out_value' => round($balances->sum('goods_out_value'), 2),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/trade-partners",
     *     summary="Add a shop you trade with",
     *     operationId="createTradePartner",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string"), @OA\Property(property="phone", type="string"), @OA\Property(property="note", type="string"))),
     *
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(StoreTradePartnerRequest $request): JsonResponse
    {
        $partner = TradePartner::create($request->validated() + ['created_by' => $request->user()->id]);

        return ApiResponse::created('Shop added', new TradePartnerResource($partner->load('openLines')));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/trade-partners/{id}",
     *     summary="One shop's account: balance, open lines, history",
     *     operationId="showTradePartner",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Partner account")
     * )
     */
    public function show(TradePartner $tradePartner): JsonResponse
    {
        $tradePartner->load('openLines.product')->loadMax('events', 'occurred_at');
        $history = $tradePartner->events()->with(['product', 'recorder'])->latest('occurred_at')->latest('id')->limit(100)->get();

        return ApiResponse::success('Trade partner', [
            'partner' => new TradePartnerResource($tradePartner),
            'history' => TradeEventResource::collection($history),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/trade-partners/{id}",
     *     summary="Edit a shop you trade with",
     *     operationId="updateTradePartner",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(StoreTradePartnerRequest $request, TradePartner $tradePartner): JsonResponse
    {
        $tradePartner->update($request->validated());

        return ApiResponse::success('Shop updated', new TradePartnerResource($tradePartner->load('openLines')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/trade-partners/{id}/goods-out",
     *     summary="They took goods",
     *     description="Stock leaves the branch; a line opens at the agreed price per item (my cost recorded).",
     *     operationId="tradeGoodsOut",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Recorded"),
     *     @OA\Response(response=422, description="Not enough stock / validation")
     * )
     */
    public function goodsOut(RecordTradeGoodsRequest $request, TradePartner $tradePartner): JsonResponse
    {
        $lines = $this->tradeService->recordGoodsOut($tradePartner, $request->user(), (int) $request->validated('store_id'), $request->validated('items'), $request->validated('note'));

        return $this->recorded('Goods given', $tradePartner, $lines);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/trade-partners/{id}/goods-in",
     *     summary="I took goods",
     *     description="Stock arrives at the branch with the agreed price as its cost; a line opens that I owe.",
     *     operationId="tradeGoodsIn",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Recorded")
     * )
     */
    public function goodsIn(RecordTradeGoodsRequest $request, TradePartner $tradePartner): JsonResponse
    {
        $lines = $this->tradeService->recordGoodsIn($tradePartner, $request->user(), (int) $request->validated('store_id'), $request->validated('items'), $request->validated('note'));

        return $this->recorded('Goods taken', $tradePartner, $lines);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/trade-partners/{id}/returns",
     *     summary="Goods brought back (either way, partial ok)",
     *     operationId="tradeReturns",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Recorded"),
     *     @OA\Response(response=422, description="More than is out, or already paid for")
     * )
     */
    public function returns(RecordTradeReturnRequest $request, TradePartner $tradePartner): JsonResponse
    {
        $lines = $this->tradeService->recordReturn($tradePartner, $request->user(), (int) $request->validated('store_id'), $request->validated('returns'), $request->validated('note'));

        return $this->recorded('Goods brought back', $tradePartner, $lines);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/trade-partners/{id}/payments",
     *     summary="Paid in money (either way)",
     *     description="direction to_me (they paid me) or by_me (I paid them); cash or M-Pesa. Settles open lines oldest first; cash goes through the till drawer.",
     *     operationId="tradePayments",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Recorded"),
     *     @OA\Response(response=422, description="More than is owed")
     * )
     */
    public function payments(RecordTradePaymentRequest $request, TradePartner $tradePartner): JsonResponse
    {
        $event = $this->tradeService->recordPayment(
            $tradePartner,
            $request->user(),
            (int) $request->validated('store_id'),
            $request->validated('direction'),
            (float) $request->validated('amount'),
            $request->validated('method'),
            $request->validated('reference'),
            $request->validated('note'),
        );

        $tradePartner->load('openLines.product');

        return ApiResponse::created('Payment recorded', [
            'event' => new TradeEventResource($event),
            'partner' => new TradePartnerResource($tradePartner),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/trade-partners/{id}/price-hint",
     *     summary="Suggested price: last agreed with this shop + my cost",
     *     operationId="tradePriceHint",
     *     tags={"Trade Partners"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="product_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="store_id", in="query", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Price hint")
     * )
     */
    public function priceHint(TradePriceHintRequest $request, TradePartner $tradePartner): JsonResponse
    {
        return ApiResponse::success('Price hint', $this->tradeService->priceHint(
            $tradePartner,
            (int) $request->validated('product_id'),
            (int) $request->validated('store_id'),
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Tenant\TradeLine>  $lines
     */
    protected function recorded(string $message, TradePartner $partner, $lines): JsonResponse
    {
        $partner->load('openLines.product');

        return ApiResponse::created($message, [
            'lines' => TradeLineResource::collection($lines),
            'partner' => new TradePartnerResource($partner),
        ]);
    }
}
