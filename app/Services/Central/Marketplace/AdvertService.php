<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\BillingLedgerType;
use App\Models\Advert;
use App\Models\AdvertType;
use App\Models\BillingAccount;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Services\Central\Billing\BillingLedgerService;
use App\Services\Central\Billing\BillingTopupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adverts: bought on the web business account from the Poachy balance or by an M-Pesa prompt
 * (a balance top-up for the price that buys the advert as soon as it is paid), shown as
 * Sponsored (at most two per page), counted (views, clicks, orders through `ref=ad{id}`) and
 * stoppable by Poachy with the unused days refunded to the balance.
 */
class AdvertService
{
    public const MAX_SPONSORED = 2;

    public function __construct(
        private readonly BillingLedgerService $ledger,
        private readonly BillingTopupService $topups,
    ) {}

    public function buy(string $tenantId, string $typeKey, ?int $productId, string $payWith, ?string $phone, string $idempotencyKey, string $by): Advert
    {
        $key = "{$tenantId}:{$idempotencyKey}";
        if ($existing = Advert::query()->where('idempotency_key', $key)->first()) {
            return $existing;
        }

        $type = AdvertType::query()->where('key', $typeKey)->where('is_active', true)->first();
        $shop = BusinessDetail::query()->listed()->where('tenant_id', $tenantId)->first();
        if ($type === null) {
            throw ValidationException::withMessages(['type' => 'This kind of advert is not available.']);
        }
        if ($shop === null) {
            throw ValidationException::withMessages(['type' => 'Adverts are for verified shops on the marketplace.']);
        }
        if ($type->needs_product) {
            $listed = $productId !== null && MarketplaceProduct::on('central')->active()->fromListedShops()->where('tenant_id', $tenantId)->whereKey($productId)->exists();
            if (! $listed) {
                throw ValidationException::withMessages(['product_id' => 'Choose one of your products that is listed online.']);
            }
        }

        $advert = Advert::query()->create([
            'tenant_id' => $tenantId, 'type_key' => $type->key, 'marketplace_product_id' => $type->needs_product ? $productId : null,
            'county' => $type->key === 'near_you' ? $shop->county : null, 'price' => $type->price, 'days' => $type->days,
            'status' => Advert::PENDING_PAYMENT, 'paid_with' => $payWith === 'balance' ? 'balance' : 'mpesa', 'created_by' => $by, 'idempotency_key' => $key,
        ]);

        if ($payWith === 'balance') {
            $this->payFromBalance($advert, strict: true);

            return $advert->fresh();
        }

        $topup = $this->topups->startStk($tenantId, (float) $type->price, (string) ($phone ?: $shop->business_phone), $by, "advert-{$advert->id}");
        $advert->update(['billing_topup_id' => $topup->id]);

        return $advert->fresh();
    }

    /**
     * The M-Pesa prompt for an advert completed (credited to the balance) or failed.
     */
    public function onTopupFinished(BillingTopup $topup): void
    {
        $advert = Advert::query()->where('billing_topup_id', $topup->id)->where('status', Advert::PENDING_PAYMENT)->first();
        if ($advert === null) {
            return;
        }
        if ($topup->status === BillingTopup::COMPLETED) {
            $this->payFromBalance($advert, strict: false);
        } elseif ($topup->status === BillingTopup::FAILED) {
            $advert->update(['status' => Advert::PAYMENT_FAILED, 'stopped_reason' => $topup->failure_reason]);
        }
    }

    /** Poachy stops an advert (moderation); unused days go back to the shop's balance. */
    public function stop(Advert $advert, string $reason, string $by): Advert
    {
        if (! in_array($advert->status, [Advert::ACTIVE, Advert::PENDING_PAYMENT], true)) {
            throw ValidationException::withMessages(['advert' => "This advert is {$advert->status}."]);
        }

        DB::connection('central')->transaction(function () use ($advert, $reason, $by) {
            $refund = 0.0;
            if ($advert->status === Advert::ACTIVE && $advert->ends_at?->isFuture()) {
                $total = max(1, $advert->starts_at->diffInSeconds($advert->ends_at));
                $left = now()->diffInSeconds($advert->ends_at);
                $refund = round((float) $advert->price * min(1, $left / $total), 2);
            }
            if ($refund > 0 && ($account = BillingAccount::query()->where('tenant_id', $advert->tenant_id)->first())) {
                $this->ledger->post($account, BillingLedgerType::Refund, $refund, 'Advert stopped by Poachy — unused days refunded', 'advert_refund',
                    "AD-{$advert->id}", "advert-refund:{$advert->id}", $by, ['advert_id' => $advert->id, 'reason' => $reason]);
            }
            $advert->update(['status' => Advert::STOPPED, 'stopped_reason' => $reason, 'ends_at' => now()]);
        });

        return $advert->fresh();
    }

    /**
     * Up to two sponsored listings for a page. Context: home, category ids, a search term,
     * a county filter. Each one served counts a view.
     *
     * @param  array<int, int>|null  $categoryIds
     * @param  array<int, int>  $exclude
     * @return Collection<int, MarketplaceProduct>
     */
    public function sponsoredProducts(string $context, ?array $categoryIds = null, ?string $term = null, ?string $county = null, array $exclude = []): Collection
    {
        $adverts = Advert::query()->running()->get();
        $candidates = collect();

        foreach ($adverts as $advert) {
            if ($context === 'home' && $advert->type_key === 'home_spot') {
                $candidates->push([$advert, $advert->marketplace_product_id]);
            } elseif ($context !== 'home' && $advert->type_key === 'product_boost') {
                $candidates->push([$advert, $advert->marketplace_product_id]);
            } elseif ($context !== 'home' && $advert->type_key === 'near_you' && $county !== null && strcasecmp((string) $advert->county, $county) === 0) {
                $candidates->push([$advert, null]); // any of the shop's matching products
            }
        }

        $picked = new Collection;
        foreach ($candidates->shuffle() as [$advert, $productId]) {
            if ($picked->count() >= self::MAX_SPONSORED) {
                break;
            }
            $query = MarketplaceProduct::on('central')->active()->fromListedShops()->with('item:id,slug')
                ->where('tenant_id', $advert->tenant_id)->whereNotIn('id', array_merge($exclude, $picked->pluck('id')->all()));
            $productId !== null ? $query->whereKey($productId) : $query->inRandomOrder();
            if ($categoryIds !== null) {
                $query->whereIn('marketplace_category_id', $categoryIds);
            }
            if ($term !== null) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('tenant_category_name', 'like', "%{$term}%")->orWhere('tenant_brand_name', 'like', "%{$term}%"));
            }
            $product = $query->first();
            if ($product !== null) {
                $product->setAttribute('sponsored_advert_id', $advert->id);
                $picked->push($product);
                $advert->increment('views');
            }
        }

        return $picked;
    }

    /**
     * Featured shops first: shops with a running featured-shop advert, flagged sponsored.
     *
     * @return Collection<int, BusinessDetail>
     */
    public function sponsoredShops(int $limit = self::MAX_SPONSORED): Collection
    {
        $adverts = Advert::query()->running()->where('type_key', 'featured_shop')->get()->shuffle()->take($limit);
        $shops = new Collection;
        foreach ($adverts as $advert) {
            $shop = BusinessDetail::on('central')->listed()->where('tenant_id', $advert->tenant_id)->first();
            if ($shop !== null && ! $shops->contains('tenant_id', $shop->tenant_id)) {
                $shop->setAttribute('sponsored_advert_id', $advert->id);
                $shops->push($shop);
                $advert->increment('views');
            }
        }

        return $shops;
    }

    public function recordClick(int $advertId): bool
    {
        return Advert::query()->whereKey($advertId)->where('status', Advert::ACTIVE)->increment('clicks') > 0;
    }

    /** Orders that came through this advert's links (`ref=ad{id}`). */
    public function ordersFor(Advert $advert): int
    {
        return MarketplaceOrder::on('central')->where('ref', $advert->ref())->count();
    }

    /** Adverts whose time is up. */
    public function endFinished(): int
    {
        return Advert::query()->where('status', Advert::ACTIVE)->where('ends_at', '<=', now())->update(['status' => Advert::ENDED]);
    }

    private function payFromBalance(Advert $advert, bool $strict): void
    {
        DB::connection('central')->transaction(function () use ($advert, $strict) {
            $account = BillingAccount::query()->where('tenant_id', $advert->tenant_id)->lockForUpdate()->first();
            if ($account === null) {
                $advert->update(['status' => Advert::PAYMENT_FAILED, 'stopped_reason' => 'No Poachy balance account']);

                return;
            }
            // From the balance it must cover the price; an M-Pesa payment was made for this advert,
            // so it is always charged (a shop already in debt stays in the same debt).
            if ($strict && (float) $account->balance < (float) $advert->price) {
                $advert->update(['status' => Advert::PAYMENT_FAILED, 'stopped_reason' => 'Not enough balance']);
                throw ValidationException::withMessages(['pay_with' => 'Your Poachy balance is KSh '.number_format((float) $account->balance, 2).'. Top up or pay with M-Pesa.']);
            }
            $type = $advert->type;
            $entry = $this->ledger->post($account, BillingLedgerType::Charge, -(float) $advert->price, "Advert: {$type?->name} ({$advert->days} days)", 'advert',
                "AD-{$advert->id}", "advert-charge:{$advert->id}", (string) $advert->created_by, ['advert_id' => $advert->id]);
            $advert->update(['status' => Advert::ACTIVE, 'billing_ledger_id' => $entry->id, 'starts_at' => now(), 'ends_at' => now()->addDays($advert->days)]);
        });
        Log::info('Advert paid', ['advert_id' => $advert->id, 'status' => $advert->fresh()->status]);
    }
}
