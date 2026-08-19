# POS — Negotiated pricing, giveaways and open items

**Requested by:** Mobile (Flutter Merchant POS)
**Priority:** Blocking the first pilot shops
**Scope:** Tenant sales — `CalculateSaleRequest`, `CreateSaleRequest`, `SaleCalculationService`, `SaleService`, `sale_items`

---

## Why

The first shops are small general traders in Eastleigh. They do not issue
receipts, are not VAT-registered, and **every price is negotiated at the
counter**. The shopkeeper opens above their real price, the customer walks them
down, and the sale settles somewhere above a floor the owner never goes below.
After a large purchase the cashier will often hand over a small item free as
goodwill.

The API cannot record any of that. `CreateSaleRequest` accepts
`product_id`, `variant_id`, `bundle_id`, `uom_id`, `quantity` and
`serial_numbers` — **no price and no line discount**. Prices resolve entirely
server-side from `store_selling_price ?? base_selling_price`, and the only
discounts that exist are pre-configured promotions and coupons.

So a sale agreed at KES 1,320 gets recorded as 1,200, and a free item either
doesn't decrement stock or gets fudged into another line's quantity. Once the
till is lying about what happened, stock, margin and cash-up are all fiction and
the cashier abandons the system within days.

This is ahead of offline mode, printing and M-Pesa on the mobile critical path.

---

## The price ladder

Four numbers per line. The system already knows two of them.

| | Example | Where it lives today |
|---|---|---|
| **Asking price** | 1,500 | Nowhere — cashier's opening quote |
| **Agreed price** | 1,320 | Nowhere — **this is what's missing** |
| **Floor** | 1,200 | `store_products.store_selling_price ?? products.base_selling_price` |
| **Cost** | 1,050 | `product_batches.cost_per_base_uom`, already copied to `sale_items.unit_cost` |

The key constraint, and the reason this is safer than general price override:
**the cashier only ever negotiates upward.** The floor is the existing catalogue
price; the system just needs to treat it as a lower bound rather than a fixed
value. A cashier can capture upside but cannot undercut, so the common case
needs no permission at all.

Asking price is a client-side display concern (floor × a markup the owner sets)
and needs nothing from the backend beyond a setting to store the markup.

---

## 1. Accept an agreed price per line

Add to both `CalculateSaleRequest` and `CreateSaleRequest`:

```php
'items.*.agreed_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
'items.*.price_override_reason' => ['nullable', 'string', 'max:255'],
```

Behaviour in `SaleCalculationService::buildLineItem()` (and the variant/bundle
equivalents at lines ~259 and ~299):

- Resolve `$listPrice` exactly as `$unitPrice` is resolved today. This is the floor.
- If `agreed_unit_price` is absent, behave exactly as now — no change for any
  existing caller.
- If present, use it as `unit_price` for all downstream maths (line total, tax,
  promotions, loyalty). **Tax is calculated on the agreed price, not the floor.**
- Keep `$listPrice` on the line item array so it can be persisted.

Validation, in `withValidator()` so the message is specific:

- `agreed_unit_price < listPrice` → reject unless the user holds a new
  permission (below), with a message naming both figures:
  *"Cannot sell Mango Juice 1L below the floor of KES 1,200. You entered KES 1,150."*
- `agreed_unit_price >= listPrice` → always allowed, no permission needed.
- `agreed_unit_price == 0` → only valid on a giveaway line (section 2).

Please do **not** model this as a discount percentage. Shopkeepers think
"we said 1,320", never "12% off". The client sends the settled figure; any
discount representation is derived server-side.

## 2. Giveaway lines

A zero-price goodwill item must still decrement stock and still carry its cost
into margin, so it cannot be a 100% discount hidden in `discount_amount`.

```php
'items.*.is_giveaway' => ['nullable', 'boolean'],
```

When `is_giveaway` is true:

- `unit_price` is forced to 0 regardless of what was sent.
- Inventory depletes normally (including batch/FEFO and serials).
- `unit_cost` is captured as usual — the shop needs to see what the gift cost.
- Requires the same permission as selling below floor.
- Tax on the line is 0.

## 3. Open / unlisted items

Much of this stock has no barcode and will never reach the catalogue. Without a
"type a description and an amount" line, cashiers hit an item they can't find on
day one and stop using the till.

`sale_items.product_id` is already nullable (migration
`2026_08_05_224212_make_product_id_nullable_on_sale_items_table`), so the column
work is done. Needed:

```php
'items.*.description' => ['required_without_all:items.*.product_id,items.*.bundle_id', 'string', 'max:255'],
```

- `product_id`, `variant_id`, `bundle_id` all null; `description` and
  `agreed_unit_price` required.
- No inventory movement, no batch depletion.
- `unit_cost` = 0, and these lines should be **excluded from margin reporting**
  rather than counted as 100% margin.
- Permission-gated, same as below-floor.

## 4. Persist enough to report on it

Migration on `sale_items`:

| Column | Type | Purpose |
|---|---|---|
| `list_unit_price` | `decimal(15,2)` nullable | The floor at time of sale. Without this the uplift is unrecoverable later, because catalogue prices change. |
| `is_giveaway` | `boolean` default false | Separates gifts from discounts in reporting. |
| `price_override_reason` | `string(255)` nullable | Free text, only meaningful when below floor. |
| `description` | `string(255)` nullable | Open-item label. |

`unit_price` keeps its current meaning of "price actually charged" and now holds
the agreed figure. Add all four to `SaleItem::$fillable` and write them in
`SaleService` at the `SaleItem::create()` call (~line 534).

**This is the payoff.** With `list_unit_price` and `unit_cost` stored next to
`unit_price`, negotiation performance per cashier becomes a report — *"Amina
settles 11% above floor, Brian 3%"* — from data already being collected. For an
owner whose margin currently vanishes into unrecorded haggling, that is the most
valuable number the system can produce, and it falls out of this change rather
than needing separate work.

## 5. Permission

Add one tenant permission alongside the existing `apply-discounts`:

```
override-sale-price
```

Granted to `owner` and `manager` by default, **not** `cashier`. It gates exactly
three things: selling below the floor, giveaway lines, and open items.
Negotiating upward is unrestricted.

A cashier hitting the limit should get a 403 with a message the cashier can act
on, not a generic denial.

---

## Bug found while specifying this

**`sale_items.tax_rate_id` is `NOT NULL`, but the calculation service can now
emit `null`.**

`SaleCalculationService` was null-guarded for products without a tax rate
(`'tax_rate_id' => $taxRate?->id`, lines 241/283/324), so `sales/calculate`
succeeds and returns 0% tax. The insert then fails, verified directly:

```
sale_items.tax_rate_id  Null=NO  Type=bigint unsigned
INSERT REJECTED: SQLSTATE[23000]: Integrity constraint violation:
                 1048 Column 'tax_rate_id' cannot be null
```

Not currently reachable in the demo tenant because every product there has a tax
rate — the mobile app stamps the tenant default on create precisely to avoid the
older NPE. Any product created by another path (import, admin, seeder, or a
future non-app client) will calculate cleanly and then 500 at the point of sale.

Fix: make the column nullable to match the service. Relevant to this ticket
because **giveaway and open-item lines are untaxed by definition**, so they'll
hit it immediately.

---

## Out of scope

- eTIMS / tax invoices — the pilot shops aren't VAT-registered.
- Asking-price markup — client-side, needs only a stored setting.
- Refund handling for negotiated lines — refunds already work off
  `sale_items.unit_price`, so they should follow automatically. Worth a test.

## Acceptance

1. A sale with `agreed_unit_price` above the floor is accepted from a cashier and
   stores both agreed and list price.
2. The same sale below the floor is rejected for a cashier, accepted for an
   owner, and stores the reason.
3. A giveaway line decrements stock, records cost, contributes 0 to revenue and
   0 tax.
4. An open item with no `product_id` saves, moves no stock, and is excluded from
   margin.
5. A sale containing a product with no tax rate completes end to end.
6. Omitting all new fields produces byte-identical behaviour to today —
   the existing sale tests must pass untouched.
