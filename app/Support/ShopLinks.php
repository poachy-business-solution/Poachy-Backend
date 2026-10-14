<?php

namespace App\Support;

use App\Models\BusinessDetail;

/**
 * The links a shop shares: its page and a product's page on the web. Null until the
 * shop is listed (verified and active), because the pages answer 404 before that.
 */
final class ShopLinks
{
    /** @var array<string, string|null> */
    private static array $shopUrls = [];

    public static function shopUrl(string $tenantId): ?string
    {
        if (! array_key_exists($tenantId, self::$shopUrls)) {
            $detail = BusinessDetail::on('central')->where('tenant_id', $tenantId)->first(['slug', 'is_verified', 'status']);
            self::$shopUrls[$tenantId] = $detail && $detail->isListed() ? $detail->public_url : null;
        }

        return self::$shopUrls[$tenantId];
    }

    public static function productUrl(string $tenantId, ?string $productSlug): ?string
    {
        $shop = self::shopUrl($tenantId);

        return $shop && $productSlug ? $shop.'/p/'.$productSlug : null;
    }

    /** Tests and long-running workers call this when a shop's listing changes. */
    public static function forget(?string $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$shopUrls = [];
        } else {
            unset(self::$shopUrls[$tenantId]);
        }
    }
}
