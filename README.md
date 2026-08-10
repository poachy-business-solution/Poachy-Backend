# Poachy

A multi-tenant SaaS platform built on Laravel 12. Merchants get a full point-of-sale and inventory management system on their own subdomain. Customers shop across merchants through a central marketplace. The entire backend is a pure REST API — no server-rendered frontend.

---

## Table of Contents

- [Architecture](#architecture)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Local Development](#local-development)
- [Demo Tenant Seeder](#demo-tenant-seeder)
- [Environment Variables](#environment-variables)
- [CI/CD Pipeline](#cicd-pipeline)

---

## Architecture

The system runs two distinct contexts on a single Laravel codebase:

**Central** (`poachy.test` / `poachy.com`)
Serves the marketplace and manages all tenants. One shared database (`poachy`).

**Tenant** (`{slug}.poachy.test` / `{slug}.poachy.com`)
Each merchant's private POS environment. Every tenant gets its own isolated MySQL database (`poachy_tenant_{id}`), provisioned automatically when a merchant registers.

Domain-based tenancy is handled by [Stancl Tenancy](https://tenancyforlaravel.com). The nginx layer uses a catch-all `server_name _` so both the central domain and all tenant subdomains route through the same container — Laravel reads the `Host` header to switch context.

```text
                        ┌─────────────────────────────────────────┐
                        │           nginx (catch-all)             │
                        └───────────┬─────────────────────────────┘
                                    │  Host: poachy.test
                    ┌───────────────▼───────────────────┐
                    │        PHP-FPM (Laravel)          │
                    │                                   │
                    │  Central context  Tenant context  │
                    └───────┬───────────────┬───────────┘
                            │               │
                    ┌───────▼───┐   ┌───────▼──────────┐
                    │  poachy   │   │ poachy_tenant_*  │
                    │  (MySQL)  │   │    (MySQL)        │
                    └───────────┘   └──────────────────┘
```

---

## Features

### Central (marketplace & admin)

| Area | Capabilities |
| --- | --- |
| **Tenant management** | Business registration, document verification, 2FA OTP, status workflow (pending → active) |
| **Subscriptions** | Plans, trial periods, billing cycle tracking |
| **Marketplace catalog** | Products, variants, bundles synced from tenant POS systems |
| **Customer-facing** | Auth, delivery addresses, wishlist, cart, orders, payment stub |
| **Delivery zones** | Central zone definitions, synced back to tenants |
| **Reviews** | Product and merchant reviews, moderation, response sync back to tenant |
| **Analytics** | Admin dashboard metrics |

### Tenant (POS per merchant)

| Area | Capabilities |
| --- | --- |
| **Products** | Categories, brands, UoMs, variants, bundles, price history |
| **Inventory** | Stock movements, transfers, purchase orders, product batches, expiry/stock alerts, waste management, reservations |
| **Sales** | POS transactions, multi-method payments, refunds, daily aggregates |
| **Shifts** | Scheduling, clock-in/out, overtime calculation, swap requests, no-show detection, cash variance |
| **Customers** | Profiles, groups, loyalty points, credit accounts, journey events |
| **Expenses** | Categories, expense tracking, budget management |
| **Promotions** | Coupons, promotions |
| **Staff** | Multi-user with roles and permissions, scoped per tenant |
| **Suppliers** | Profiles, purchase orders, payment tracking |
| **Audit logging** | Full audit trail on all financial models, async-capable |

### Central ↔ Tenant sync

Tenant POS systems push catalog and operational data up to the central marketplace. The central app pushes marketplace events (orders, payments, approved reviews) back down to tenants.

---

## Tech Stack

| Layer | Technology |
| --- | --- |
| Framework | Laravel 12, PHP 8.4 |
| Multi-tenancy | Stancl Tenancy (domain-based, per-tenant DB) |
| Queue management | Laravel Horizon |
| Auth | Laravel Sanctum (separate guards for central / tenant) |
| Permissions | Spatie Laravel-Permission (cache isolated per tenant) |
| Database | MySQL 8.4 |
| Cache / Queue / Sessions | Redis |
| API docs | L5-Swagger (OpenAPI 3) |
| Containerisation | Docker (PHP 8.4-fpm-alpine, nginx 1.27-alpine) |

---

## Local Development

### Prerequisites

- Docker + Docker Compose
- GNU Make
- `/etc/hosts` entries for the domains you want to use

### 1. Add domains to `/etc/hosts`

```text
127.0.0.1   poachy.test
127.0.0.1   techhaven.poachy.test   # add one line per tenant you'll test locally
```

### 2. One-shot setup

```bash
git clone git@github.com:godiah/poachy.git
cd poachy
make setup
```

`make setup` handles the fresh-clone path in the required order:

- creates the shared `dbtools` Docker network if missing
- copies `.env.example` to `.env` if needed
- builds and starts the local Docker services
- runs `composer install` inside the app container, so the bind-mounted `vendor/` directory exists
- generates `APP_KEY` if it is still empty
- runs central migrations and central seeders
- rebuilds the full demo tenant with `tenant:seed-demo`

The `.env.example` ships with Docker-ready defaults (`CENTRAL_DB_HOST=mysql`, `REDIS_HOST=redis`, `CENTRAL_API_URL=http://nginx`, etc.) so minimal changes are needed for local dev.

### Manual setup

Use these only when you want to run the bootstrap one step at a time:

```bash
make ensure-dbtools
make env
make up
make composer-install
make key
make migrate
make seed-central
make seed-demo
```

`make seed-central` must run before `make seed-demo`; the demo command needs central business types, categories, plans, roles, and admin data.

### Useful commands

```bash
# Open a shell inside the app container
make shell

# Run artisan commands (sail-style)
make artisan cmd="route:list"

# Tail logs
make logs
docker compose logs -f horizon

# Stop everything
make down

# Stop and wipe volumes (destructive — deletes all DB data)
docker compose down -v
```

### Services and ports

| Service | Host port | Purpose |
| --- | --- | --- |
| nginx | `80` | App entry point (all requests) |
| MySQL | `3307` | Central DB + all tenant DBs |
| Redis | `6379` | Cache, queues, sessions |
| Mailpit SMTP | `1025` | Catch all outgoing mail |
| Mailpit dashboard | `8025` | View caught emails |

Your existing phpMyAdmin at `localhost:8888` can reach poachy's MySQL — both are on the shared `dbtools` Docker network. Connect to host `mysql` (the container name) or `host.docker.internal:3307`.

### API documentation

With `L5_SWAGGER_GENERATE_ALWAYS=true` in your `.env`, Swagger UI is available at:

```text
http://poachy.test/api/documentation
```

---

## Demo Tenant Seeder

For frontend development, `tenant:seed-demo` provisions a single, fully-populated demo tenant in one command — no manual API calls or empty screens.

```bash
docker compose exec laravel.test php artisan tenant:seed-demo
```

Safe to re-run any time: it deletes the existing `demo.poachy.test` tenant (and its database) first, then rebuilds from scratch, so the demo dataset is always fresh and predictable. It creates:

- Central business profile + an Enterprise-tier subscription
- 2 stores, 4 staff accounts with roles (owner, manager, 2 cashiers)
- A full product catalog — simple, variable, batch-tracked, and serial-tracked products, bundles, suppliers, tax rates
- Opening stock via real purchase orders (batches, serials, supplier payments)
- Customers, customer groups, coupons, promotions
- 9 days of shift history plus today's active shifts (clock-in/out, a swap, a no-show)
- ~50 POS sales (cash/mpesa/card/credit), refunds, and marketplace orders
- Stock transfers, waste records, inventory reservations, expiry alerts
- Expense categories, budgets, and expenses
- Product reviews and delivery zones

Add `demo.poachy.test` to `/etc/hosts` (see [step 1](#1-add-domains-to-etchosts) above) to reach it. Fixed login credentials are printed at the end of every run:

| Role | Email | Password |
| --- | --- | --- |
| Owner | `owner@demo.poachy.test` | `Demo@12345` |
| Manager | `manager@demo.poachy.test` | `Demo@12345` |
| Cashier | `cashier1@demo.poachy.test` | `Demo@12345` |
| Cashier | `cashier2@demo.poachy.test` | `Demo@12345` |

This is separate from `TenantDatabaseSeeder`, which runs automatically on every tenant's creation and only seeds structural/reference data (roles, product categories, UoMs, tenant configuration) — `tenant:seed-demo` builds the full operational dataset on top of that baseline.

---

## Environment Variables

A fully documented `.env.example` is included. The variables that **must** be set before the app will start:

| Variable | Description |
| --- | --- |
| `APP_KEY` | Generate with `php artisan key:generate` |
| `CENTRAL_DB_PASSWORD` | Password for the central database root user |
| `TENANT_DB_PASSWORD` | Same as above (same MySQL instance) |
| `CENTRAL_API_TOKEN` | Shared secret — tenant → central HTTP calls |
| `TENANT_API_TOKEN` | Shared secret — central → tenant HTTP calls |
| `CENTRAL_DOMAINS` | Comma-separated central domains (e.g. `poachy.test`) |

See `.env.example` for all 136 variables and their defaults.

---

## CI/CD Pipeline

Two workflow files in `.github/workflows/`:

### `ci.yml` — runs on every `feature/**` push and PR to `main`

| Job | What it does |
| --- | --- |
| `test` | Spins up MySQL 8.4 + Redis, patches all `CENTRAL_DB_*` / `TENANT_DB_*` env vars, runs central and tenant migrations, then `php artisan test` |
| `build-check` | Builds the production Docker image (no push) and runs `artisan config:cache`, `route:cache`, `view:cache`, `event:cache` inside it — catches Dockerfile regressions before `main` is touched |

Both jobs must pass for a PR to merge.

### `cd.yml` — runs on every push to `main`

| Job | What it does |
| --- | --- |
| `build` | Builds app (`sha-<short>`) and nginx (`nginx-sha-<short>`) images, pushes both to GHCR |
| `deploy` | SSHes into the production server, pulls the pinned image tag, runs central + tenant migrations, does a rolling healthcheck-gated restart, rebuilds all Laravel caches, drains Horizon gracefully, then restarts workers and nginx |

#### Required GitHub secrets

| Secret | Description |
| --- | --- |
| `GITHUB_TOKEN` | Auto-provided — used to push images to GHCR |
| `GHCR_PULL_TOKEN` | PAT with `read:packages` — server pulls images |
| `PROD_SSH_HOST` | Production server IP or hostname |
| `PROD_SSH_USER` | SSH login user on the server |
| `PROD_SSH_KEY` | SSH private key for that user |
