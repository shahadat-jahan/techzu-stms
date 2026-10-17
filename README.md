# Techzu Support Ticket & SLA Management System

A multi-role support ticket management system with SLA tracking, business-hours-aware deadlines, auto-assignment, CSV bulk import, and a role-based dashboard.

## Features

- **Ticket lifecycle**: create, update, status transitions (open → in progress → waiting for customer → resolved → closed), reopen.
- **SLA engine**: per-priority resolution/warning minutes from `sla_policies`; business-hours-aware due dates (Sun–Thu, 09:00–18:00 Asia/Dhaka); approaching/breached status derivation.
- **Escalation**: scheduled `sla:check` command (every minute, idempotent) dispatches approaching/breached notifications.
- **Assignment**: manual assign, auto-assign (least-loaded, category match, max cap), concurrent accept with atomic conditional update (409 on conflict).
- **Conversations**: replies, internal notes (hidden from customers), file attachments with secure private-disk download.
- **CSV bulk import**: chunked (500 rows), validation, duplicate detection via `external_ref`, error report download, progress polling.
- **Dashboard**: conditional-aggregate KPIs, priority breakdown, agent workload; 60s per-user cache.
- **Authorization**: role-based (admin/agent/customer) via policies and `visibleTo` scoping.
- **Audit trail**: every state change writes a `ticket_activities` row.

## Tech Stack

- PHP 8.4+, Laravel 13
- PostgreSQL (SQLite in-memory for tests)
- Redis (optional; database cache/queue by default)
- Tailwind CSS, Vite, Blade
- Pest (testing), Laravel Pint (style)

## Requirements

- PHP 8.4+
- Composer 2
- Node.js 18+ / npm
- PostgreSQL 14+ (or use Docker Compose)
- (Optional) Redis

## Installation (fresh clone)

```bash
git clone <repo-url> techzu-stms
cd techzu-stms

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env (PostgreSQL), then:
php artisan migrate:fresh --seed

npm install --ignore-scripts
npm run build

php artisan serve
```

Or use the bundled setup script (note: it runs `migrate --force` but **not** `--seed`; seed manually):

```bash
composer setup
php artisan migrate:fresh --seed
```

### Demo data (optional, ~200 tickets)

```bash
php artisan db:seed --class=DemoDataSeeder
```

The seeder is opt-in and is **not** run by default, keeping the default seed fast and CI green.

## Environment Configuration

Key variables (see `.env.example`):

| Variable | Description |
|---|---|
| `APP_DEBUG` | `true` for local dev; set `false` in production |
| `DB_CONNECTION` / `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | PostgreSQL connection |
| `QUEUE_CONNECTION` | `database` (default) or `redis` |
| `CACHE_STORE` | `database` (default) or `redis` (required for cache tags/invalidation) |
| `SUPPORT_BUSINESS_TIMEZONE` | `Asia/Dhaka` |
| `SUPPORT_DEFAULT_SLA_MINUTES` | default resolution minutes |
| `SUPPORT_WARNING_PERCENT` | warning threshold percent |
| `SUPPORT_UPLOAD_MAX_SIZE` / `SUPPORT_UPLOAD_MAX_FILES` | attachment limits |
| `SUPPORT_IMPORT_MAX_SIZE` | CSV import size limit (MB) |
| `SUPPORT_PAGINATION_PER_PAGE` | page size |

## Demo Credentials

All seeded users use password `password`.

| Role | Email |
|---|---|
| Admin | admin@techzu.com |
| Agent (available) | agent1@techzu.com |
| Agent (available) | agent2@techzu.com |
| Agent (unavailable) | agent3@techzu.com |
| Customer | customer1@techzu.com |
| Customer | customer2@techzu.com |
| Customer | customer3@techzu.com |

## Running the Application

```bash
php artisan serve          # http://localhost:8000
```

### Queue worker (required for notifications & imports)

```bash
php artisan queue:work
```

### Scheduler (required for SLA escalation)

```bash
php artisan schedule:work
# or cron: * * * * * php /path/to/artisan schedule:run
```

### SLA check (manual)

```bash
php artisan sla:check
```

## Testing

```bash
composer test              # config:clear + artisan test
vendor/bin/pint            # code style
vendor/bin/pint --test     # style check (CI)
```

Tests use SQLite in-memory and the array cache store.

## CSV Import Format

Expected header (all required):

```
external_ref,subject,description,priority,category,status,customer_email,assigned_agent_email,created_at
```

- `priority`: `low`, `medium`, `high`, `critical`
- `status`: `open`, `in_progress`, `waiting_for_customer`, `resolved`, `closed`
- `category`: must match an existing category name
- `customer_email`: must match an existing customer
- `assigned_agent_email`: optional; must match an existing agent
- `created_at`: optional; `Y-m-d H:i:s`
- Duplicate `external_ref` (in DB or file) is rejected per row.

A sample file is available at `public/sample-import.csv` and via the "Download Sample CSV" link on the imports page.

## Architecture Overview

```
app/
  Console/Commands/     # sla:check
  Enums/                # Role, TicketStatus, Priority, SlaStatus
  Events/ Listeners/    # queued notifications
  Http/Controllers/     # thin controllers
  Http/Requests/        # form requests
  Jobs/                 # ProcessTicketImportJob
  Models/               # Eloquent models
  Policies/             # TicketPolicy, ReplyPolicy, AttachmentPolicy
  Services/             # business logic
database/
  factories/ seeders/ migrations/
resources/views/        # Blade templates
routes/web.php console.php
```

### Key Decisions & Assumptions

- **No DB enum columns**: backed PHP enums cast on models.
- **UTC in DB**: business-hours math uses `Asia/Dhaka`.
- **SLA values from `sla_policies` table**, not hardcoded.
- **Soft deletes on tickets only**.
- **Demo seeder is opt-in** to keep default seed fast.
- **Spec docs** (`MASTERCONTEXT.md`, `PLAN.md`, `AGENTS.md`, `CLAUDE.md`) are gitignored; this README carries architecture/decision content.
- **Cache tags** require a tag-capable store (redis/memcached/array); the default database store falls back to plain keys with TTL.

## Security

- CSRF protection on all state-changing forms (`@csrf`).
- No `{!! !!}` rendering of user content (XSS-safe).
- Mass assignment guarded via `#[Fillable]` attributes.
- IDOR prevented by policies + `visibleTo` scoping.
- SQL injection prevented via bound parameters.
- File downloads served from private `local` disk through `AttachmentPolicy`.
- Rate limiting: login (`RateLimiter`), reply/attachment uploads (`throttle:30,1`).
- `APP_DEBUG=false` required in production.
- `.env` is not tracked; no secrets in seeders (demo passwords only).

## Performance

- List query uses compound index `tickets_created_at_index` / `(status, assigned_to)` etc.
- Dashboard uses single conditional-aggregate query (`SUM(CASE WHEN ...)`).
- `sla:check` uses `(due_at, status)` index via `slaApproaching`/`slaBreached` scopes.
- Eager loading on list/show to avoid N+1.
- CSV import streams via `fgetcsv` in 500-row chunks.

## Docker (bonus)

```bash
docker compose up -d
# app: http://localhost:8000
```

Services: app (php-fpm), nginx, postgres, redis, queue worker, scheduler.

## CI (bonus)

GitHub Actions workflow (`.github/workflows/ci.yml`) runs on push/PR: setup PHP 8.4, composer install, migrate+seed, npm build, Pint check, Pest tests.

## Known Limitations

- No REST API (Sanctum) — bonus item not implemented.
- No demo URL provided.
- No real-time websockets — bonus item not implemented.
- No advanced reporting — bonus item not implemented.
- Cache tags require a tag-capable store; default database store uses TTL-only caching.

## Fresh Clone Verification

Follow the Installation steps above. Verified on a clean environment: `composer install` → `cp .env.example .env` → `key:generate` → `migrate:fresh --seed` → `npm install --ignore-scripts` → `npm run build` → `php artisan serve`. All tests pass via `composer test`.