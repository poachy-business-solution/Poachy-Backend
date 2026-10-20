# Switching Poachy from fakes to real providers

Locally every external provider is faked: M-Pesa and Paystack use `fake` drivers driven by
`poachy:simulate-mpesa` / `poachy:simulate-paystack`, email goes to Mailpit, SMS to
`storage/logs/sms.log`, Turnstile uses Cloudflare's always-pass test keys. Going live means
changing **environment variables only** — no code changes. Do it per provider, sandbox first,
and keep every secret in the server's env (never in a repo, never in chat).

Order that keeps the shop working at each step: **email → SMS → Turnstile → M-Pesa
(sandbox → production) → Paystack (test → live)**. After each step run the check listed.

## 0. Before anything

- `APP_URL` is the public HTTPS URL of the API (e.g. `https://api.poachy.com`). Every callback
  URL Poachy gives a provider is derived from it.
- `APP_KEY` is set and **never rotated casually**: the secret last segment of every M-Pesa
  callback URL (STK, C2B, B2C payouts, refunds) is derived from it. Rotating it means
  re-registering the C2B URLs (§4) and waiting for open STK prompts to expire.
- `php artisan config:cache` after any env change, then `php artisan queue:restart`
  (Horizon runs jobs with cached code/config).

## 1. Email (Mailpit → real SMTP/API)

| Variable | Value |
| --- | --- |
| `MAIL_MAILER` | `smtp` (or `postmark` / `resend` with `POSTMARK_TOKEN` / `RESEND_KEY`) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | from the provider |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | e.g. `hello@poachy.com`, `Poachy` (domain with SPF + DKIM + DMARC) |

Check: sign up a test shop on the website — the email code arrives; the admin's
**Email & SMS log** shows it `Sent`.

## 2. SMS (log → Africa's Talking)

| Variable | Value |
| --- | --- |
| `SMS_DRIVER` | `africastalking` |
| `SMS_SENDER_ID` | the approved sender ID (default `POACHY`) |
| `AFRICASTALKING_USERNAME`, `AFRICASTALKING_API_KEY` | from the AT dashboard |
| `AFRICASTALKING_BASE_URL` | `https://api.africastalking.com` (the sandbox value has `sandbox.` in it) |

Check: sign-up phone code arrives as an SMS; message log shows it `Sent`.

## 3. Turnstile (test keys → real)

| Where | Variable |
| --- | --- |
| backend | `TURNSTILE_DRIVER=cloudflare`, `TURNSTILE_SECRET_KEY=<secret>` |
| poachy-web | `NEXT_PUBLIC_TURNSTILE_SITE_KEY=<site key>` (public by design) |

Check: website sign-up and buyer registration still complete; a request without a token is
refused (422).

## 4. M-Pesa Daraja (fake → sandbox → production)

Set `MPESA_DRIVER=daraja` and `MPESA_ENVIRONMENT=sandbox` (later `production`), then the
credentials for that environment from the Daraja portal:

| Purpose | Variables (`SANDBOX` → `PRODUCTION`) |
| --- | --- |
| Auth | `MPESA_<ENV>_CONSUMER_KEY`, `MPESA_<ENV>_CONSUMER_SECRET` |
| Paybill (C2B + STK) | `MPESA_<ENV>_SHORTCODE`, `MPESA_<ENV>_PASSKEY` |
| B2C payouts + reversals | `MPESA_<ENV>_B2C_SHORTCODE` (defaults to the shortcode), `MPESA_<ENV>_INITIATOR_NAME`, `MPESA_<ENV>_SECURITY_CREDENTIAL` (encrypted with Safaricom's certificate) |
| Account numbers | `MPESA_ACCOUNT_PREFIX` (default `POA`) |

Callback URLs — leave the `MPESA_*_URL` variables **empty** so Poachy derives them from
`APP_URL` plus the secret segment. Set one only to put a different host in front; Poachy still
appends the secret.

Lock the callbacks to Safaricom: `MPESA_CALLBACK_IPS` = Safaricom's published callback
addresses, comma-separated (empty = any caller; the secret path is always required).

Register the Paybill's C2B URLs (once per environment, and again after an `APP_KEY` change):

```bash
php artisan mpesa:register-c2b          # prints the URLs with the secret masked
```

Checks (sandbox first, small amounts in production):
1. STK: top up from `/business/billing` → phone prompt → balance credited, receipt in the statement.
2. Paybill: pay to the Paybill with account `POA000xx` → `c2b/confirm` credits the balance
   (unmatched accounts land in the admin's unmatched list).
3. Marketplace: buy a product by M-Pesa → order paid; hand it over → next night's payout runs
   (`marketplace:payouts`, 02:00 Nairobi) → B2C result marks the payout paid.
4. Refund: cancel a paid order → reversal / B2C result marks the refund completed.
Watch `storage/logs/mpesa.log` and the admin's Payouts / Refunds monitors.

Payout fees, hold, minimum payout and the marketplace commission are set by finance in the
admin (**Marketplace → Payout fees** and **Commission**), from a date on. The launch values
(M-Pesa 0.55%, card 2.9%, KSh 0 per transfer, 24 h hold, KSh 10 minimum, 0% commission) are
placeholders: schedule the real Daraja / Paystack tariffs there before going live. The
`PAYOUT_*` env variables are only the fallback when no setting exists. `PAYOUT_ALERT_EMAIL`
gets failed-payout alerts.

## 5. Paystack (fake → test → live)

| Variable | Value |
| --- | --- |
| `PAYSTACK_DRIVER` | `paystack` |
| `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | `sk_test_…` / `pk_test_…`, later `sk_live_…` / `pk_live_…` |
| `PAYSTACK_CURRENCY` | `KES` |
| `PAYSTACK_CALLBACK_URL` | empty (the buyer returns to `/account/orders/{number}/paid` on the website) |

In the Paystack dashboard set the webhook URL to
`{APP_URL}/api/v1/central/marketplace/payments/paystack/webhook` (signature-verified with the
secret key; every webhook is re-verified with Paystack before an order is marked paid).

Checks: card checkout with a Paystack test card → order paid; cancel → card refund processed.

## 6. VAT

No VAT is added by default. A VAT-registered shop switches **Charge VAT** on (app → More → Shop
settings, or web → Business → Shop page); from then on its till and online orders add each
product's VAT rate on top of the price.

## 7. Production-only settings (not providers, but needed live)

| Variable | Where | Value |
| --- | --- | --- |
| `POACHY_WEB_SERVER_KEY` | backend **and** poachy-web | the same long random value — lets the API apply per-IP limits per website visitor |
| `CORS_ALLOWED_ORIGINS` | backend | the website origin(s), e.g. `https://poachy.com` |
| `CENTRAL_API_TOKEN`, `TENANT_API_TOKEN` | backend | long random values (tenant ↔ central sync) |
| `INTERNAL_ADMIN_TOKEN`, `INTERNAL_ADMIN_ALLOWED_IPS` | backend; token also in poachy-admin as `POACHY_INTERNAL_TOKEN` | admin server's address only |
| `SESSION_SECRET` (and the rest of `.env.example`) | poachy-web | random, ≥ 32 chars |

## Going back to fakes

Set the drivers back (`MPESA_DRIVER=fake`, `PAYSTACK_DRIVER=fake`, `SMS_DRIVER=log`,
`MAIL_MAILER=smtp` at Mailpit, Turnstile test keys) and `config:cache` again. Nothing else
changes; the simulate commands work only with the fake drivers.
