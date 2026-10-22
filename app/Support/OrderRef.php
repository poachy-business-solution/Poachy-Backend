<?php

namespace App\Support;

use App\Models\Advert;
use App\Models\BusinessDetail;

/**
 * Which link sold an order or reservation (`?ref=`), checked against the shop it is for.
 *
 * A shop's own link (wa, ig, qr, poster…) counts only for that shop — the link's shop slug must be
 * the order's shop — so a buyer who once opened one shop's WhatsApp link doesn't make their orders
 * at every other shop commission-free. An advert ref (`ad{id}`) counts only for the shop that ran
 * that advert. Anything else is dropped.
 */
final class OrderRef
{
    public static function for(?string $ref, ?string $refShop, string $tenantId): ?string
    {
        $ref = strtolower(trim((string) $ref));
        if (! preg_match('/^[a-z0-9_-]{1,20}$/', $ref)) {
            return null;
        }

        if (preg_match('/^ad(\d{1,12})$/', $ref, $m)) {
            return Advert::query()->whereKey((int) $m[1])->where('tenant_id', $tenantId)->exists() ? $ref : null;
        }

        $slug = strtolower(trim((string) $refShop));
        if ($slug === '') {
            return null;
        }

        return BusinessDetail::on('central')->where('tenant_id', $tenantId)->value('slug') === $slug ? $ref : null;
    }
}
