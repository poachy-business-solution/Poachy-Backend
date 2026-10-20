<?php

namespace App\Http\Controllers\Api\Tenant\Marketplace;

use App\Exceptions\MpesaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\BuyAdvertRequest;
use App\Http\Resources\Tenant\Marketplace\AdvertResource;
use App\Http\Responses\ApiResponse;
use App\Models\Advert;
use App\Models\AdvertType;
use App\Models\BillingAccount;
use App\Models\MarketplaceProduct;
use App\Services\Central\Marketplace\AdvertService;
use Illuminate\Http\JsonResponse;

/**
 * The shop's adverts: what can be bought, what is running and how it does (views, clicks,
 * orders). Bought on the web business account only — the app shows results, never a buy button.
 */
class ShopAdvertController extends Controller
{
    public function __construct(private readonly AdvertService $adverts) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/adverts",
     *     summary="Advert types with prices, the shop's adverts with results, and the Poachy balance",
     *     operationId="tenantAdverts",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="types[] (key, name, description, price, days, needs_product), adverts[] (status, dates, views, clicks, orders), balance, products[] (id, name: listed products an advert can point to)"),
     *     @OA\Response(response=403, description="No view-financial-reports")
     * )
     */
    public function index(): JsonResponse
    {
        $tenantId = (string) tenant()->id;

        return ApiResponse::success('Adverts', [
            'types' => AdvertType::query()->where('is_active', true)->orderBy('price')->get(['key', 'name', 'description', 'price', 'days', 'needs_product'])
                ->map(fn (AdvertType $t) => ['key' => $t->key, 'name' => $t->name, 'description' => $t->description, 'price' => (float) $t->price, 'days' => $t->days, 'needs_product' => $t->needs_product]),
            'adverts' => AdvertResource::collection(Advert::query()->where('tenant_id', $tenantId)->with(['type', 'product'])->latest('id')->limit(50)->get()),
            'balance' => (float) (BillingAccount::query()->where('tenant_id', $tenantId)->value('balance') ?? 0),
            'products' => MarketplaceProduct::on('central')->active()->where('tenant_id', $tenantId)->orderBy('name')->limit(300)->get(['id', 'name'])
                ->map(fn (MarketplaceProduct $p) => ['id' => $p->id, 'name' => $p->name])->values(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/marketplace/adverts",
     *     summary="Buy an advert (owner): from the Poachy balance, or by an M-Pesa prompt that buys it once paid",
     *     operationId="tenantBuyAdvert",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"type","pay_with"},
     *
     *         @OA\Property(property="type", type="string", enum={"product_boost","featured_shop","near_you","home_spot"}),
     *         @OA\Property(property="product_id", type="integer", nullable=true, description="Marketplace product id (product_boost, home_spot)"),
     *         @OA\Property(property="pay_with", type="string", enum={"balance","mpesa"}),
     *         @OA\Property(property="phone", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Advert (active when paid from the balance; pending_payment while the prompt is open)"),
     *     @OA\Response(response=403, description="Not the owner"),
     *     @OA\Response(response=422, description="Not enough balance, product not listed, shop not verified, or M-Pesa refused")
     * )
     */
    public function store(BuyAdvertRequest $request): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '' || strlen($key) > 100) {
            return ApiResponse::error('Idempotency-Key header is required.', ['missing_header' => 'Idempotency-Key'], 400);
        }
        $product = $request->validated('product_id');
        try {
            $advert = $this->adverts->buy((string) tenant()->id, $request->validated('type'), $product === null ? null : (int) $product,
                $request->validated('pay_with'), $request->validated('phone'), $key, 'tenant_user:'.$request->user()->id);
        } catch (MpesaException $e) {
            return ApiResponse::error('M-Pesa could not send the prompt: '.$e->getMessage(), null, 422);
        }

        return ApiResponse::created($advert->status === Advert::ACTIVE ? 'Advert is live' : 'Check your phone and enter your M-Pesa PIN',
            new AdvertResource($advert->load(['type', 'product'])));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/adverts/{id}",
     *     summary="One of the shop's adverts (poll after an M-Pesa prompt)",
     *     operationId="tenantAdvert",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Advert"),
     *     @OA\Response(response=404, description="Not this shop's advert")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $advert = Advert::query()->where('tenant_id', (string) tenant()->id)->with(['type', 'product'])->find($id);

        return $advert ? ApiResponse::success('Advert', new AdvertResource($advert)) : ApiResponse::notFound('Advert not found');
    }
}
