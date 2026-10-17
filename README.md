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
- **Redis required for queue worker, cache, and notifications**

## Tech Stack

- PHP 8.4+, Laravel 13
- PostgreSQL (SQLite in-memory for tests)
- **Redis (required)**
- Tailwind CSS, Vite, Blade
- Pest (testing), Laravel Pint (style)

## Requirements

- PHP 8.4+
- Composer 2
- Node.js 18+ / npm
- PostgreSQL 14+ (or use Docker Compose)
- **Redis (required for queue, cache, and notifications)**

## Setup

### Installation (fresh clone)

```bash
git clone <repo-url> techzu-stms
cd techzu-stms

composer install
cp .env.example .env
php artisan key:generate

# Set DB_PASSWORD (and other DB_* credentials) in .env for PostgreSQL:
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_DATABASE=techzu_stms
#   DB_USERNAME=postgres
#   DB_PASSWORD=your_password

php artisan migrate --seed

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

## Database Setup

### Requirements

- PostgreSQL 14+ (or use Docker Compose)
- **Redis (required for queue worker, cache, and notifications)**

### Migration & Seeding

1. Configure database credentials in `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=techzu
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

2. Run migrations and seed:
   ```bash
   php artisan migrate:fresh --seed
   ```

3. For demo data (optional):
   ```bash
   php artisan db:seed --class=DemoDataSeeder
   ```

## Environment Configuration

Key variables (see `.env.example`):

| Variable | Description |
|---------|-------------|
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
|---------|-------------|
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

### Queue Worker

The application requires a queue worker for notifications and CSV imports:

```bash
php artisan queue:work
```

### Queue Configuration

- Default driver: `database` (works without Redis but slower)
- Recommended: `redis` for better performance
- Configure in `.env`:
  ```env
  QUEUE_CONNECTION=redis
  ```

## Scheduler

The SLA escalation system uses a scheduled command that runs every minute:

### Local Development

```bash
php artisan schedule:work
```

### Production (Cron)

```bash
* * * * * php /path/to/artisan schedule:run
```

The `sla:check` command is idempotent and uses `withoutOverlapping` to prevent duplicate processing.

## Testing

```bash
composer test              # config:clear + artisan test
vendor/bin/pint            # code style
vendor/bin/pint --test     # style check (CI)
```

### Test Environment

- Tests use SQLite in-memory database
- Cache store: `array` (no Redis required for tests)
- All tests pass: 220 passed / 0 failed

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

#### Architecture Decisions

1. **No DB enum columns**: Backed PHP enums cast on models for type safety and better IDE support
2. **UTC in DB**: Business-hours math uses `Asia/Dhaka` timezone for calculations, stored as UTC
3. **SLA values from `sla_policies` table**: No hardcoded minutes, easily configurable per priority
4. **Soft deletes on tickets only**: Maintains audit trail while allowing data recovery
5. **Demo seeder is opt-in**: Keeps default seed fast and CI green
6. **Spec docs (`MASTERCONTEXT.md`, `PLAN.md`, `AGENTS.md`, `CLAUDE.md`) are gitignored**: This README carries architecture/decision content
7. **Cache tags require a tag-capable store**: Redis/memcached/array; default database store uses TTL-only caching

#### Business Assumptions

1. **Business hours**: Sun–Thu, 09:00–18:00 Asia/Dhaka (configurable via admin UI)
2. **SLA not paused on waiting**: Resolution clock continues ticking even when ticket is waiting for customer
3. **Stored `due_at` not recomputed on policy change**: Existing tickets keep their original deadlines
4. **Internal notes never visible to customers**: Hidden at query level, in notifications, and in attachment access
5. **Auto-assignment respects availability, category, and workload caps**: Uses least-loaded strategy with max active tickets per agent

   **Strategy detail**: When a ticket is created (or an admin runs "Auto-assign Unassigned"), the `LeastLoadedAssignmentService` picks an agent:
   - **Candidates**: available agents mapped to the ticket's category; fallback: all available agents
   - **Skip**: agents at `max_active_tickets` (counting `open`/`in_progress`/`waiting_for_customer`)
   - **Pick**: fewest active tickets; tie-break by oldest `last_assigned_at` (never-assigned agents first)
   - **If none**: ticket is left unassigned
   - **Concurrent accept**: atomic conditional update (`whereNull('assigned_to')`) — exactly one agent wins, the other gets 409

6. **CSV import behavior**:
   - `external_ref` is **required** — empty values are rejected per row
   - Upload file is **deleted from storage** after processing (success or failure)
   - `due_at`/`sla_warning_at` calculated from **import start time**, not CSV `created_at`
   - Duplicates in DB and within the same file are both rejected per row
   - Import fires **no notifications**; imported tickets get an `imported` activity entry
   - Progress polled via JSON endpoint; failed rows downloadable as CSV error report
6. **CSV import is idempotent**: Re-uploading the same file imports 0 new rows
7. **Notifications are queued**: All email and database notifications use `ShouldQueue`

### Known Limitations

- No REST API (Sanctum) — bonus item not implemented
- No demo URL provided
- No real-time websockets — bonus item not implemented
- No advanced reporting — bonus item not implemented
- Cache tags require a tag-capable store; default database store uses TTL-only caching

## Security

- CSRF protection on all state-changing forms (`@csrf`)
- No `{!! !!}` rendering of user content (XSS-safe)
- Mass assignment guarded via `#[Fillable]` attributes
- IDOR prevented by policies + `visibleTo` scoping
- SQL injection prevented via bound parameters
- File downloads served from private `local` disk through `AttachmentPolicy`
- Rate limiting: login (`RateLimiter`), reply/attachment uploads (`throttle:30,1`)
- `APP_DEBUG=false` required in production
- `.env` is not tracked; no secrets in seeders (demo passwords only)

## Performance

- List query uses compound index `tickets_created_at_index` / `(status, assigned_to)` etc.
- Dashboard uses single conditional-aggregate query (`SUM(CASE WHEN ...)`)
- `sla:check` uses `(due_at, status)` index via `slaApproaching`/`slaBreached` scopes
- Eager loading on list/show to avoid N+1
- CSV import streams via `fgetcsv` in 500-row chunks

## Docker (bonus)

```bash
docker compose up -d
# app: http://localhost:8000
```

After the containers start, run inside the app container:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force --seed
```

Services: app (php-fpm), nginx, postgres, **redis**, queue worker, scheduler.

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
