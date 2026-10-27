# Recruit Aid — Architecture

**Part of:** Corporate Aid Solutions suite
**Product:** Recruit Aid (module key: `recruit`)
**Status:** Active development
**Last revised:** 2026-09-25

---

**See also:**
- `docs/NAMING-CONVENTION.md` — naming rules for everything
- `docs/SPECIFICATION.md` — the ADRs that produced these rules
- `docs/RENAME-RECORD.md` — historical execution of the rename cycle
- `docs/REMEMBER.md` — current session state

---

## 1. Overview

Recruit Aid is built as a **modular monolith**. One codebase, one
deployment, one database per install — but with strict internal
boundaries that make each functional area independent and, eventually,
extractable into its own product.

The architecture has four layers, from most reusable to most specific:

| Layer | Namespace root | Directory | Purpose |
|---|---|---|---|
| **Core** | `CorporateAid\Core\*` | `app/Core/` | Framework — no business knowledge |
| **Platform** | `CorporateAid\Platform\*` | `app/Platform/` | Shared services any module uses |
| **Recruit** | `CorporateAid\Recruit\*` | `app/Recruit/` | Recruit Aid module code |
| **Helpers** | `CorporateAid\Helpers\*` | `app/Helpers/` | Global functions |

A module (like Recruit) may depend on Platform and Core. Platform may
depend on Core. Core depends on nothing. **Never the reverse.**

Future products add their own namespace root:
- `CorporateAid\HR\*` → `app/HR/`
- `CorporateAid\CRM\*` → `app/CRM/`

---

## 2. Directory Layout

```
app/
    Core/                       -- Framework
        App.php
        Auth.php
        Config.php
        Container.php
        Csrf.php
        Database.php
        Migrator.php
        Request.php
        Response.php
        Router.php
        Session.php
        View.php
        Http/
            Kernel.php          -- middleware pipeline
        Exceptions/
            HttpException.php

    Platform/                   -- Shared infrastructure
        Support/                -- utilities, loggers, helpers
        Services/               -- business-agnostic services
        Models/                 -- shared entities (User, File, ...)
        Repositories/           -- SQL for shared tables
        Validation/             -- shared validators
        Middleware/             -- HTTP middleware
        CustomFields/           -- custom field engine
        Deletion/               -- deletion workflow framework

    Recruit/                    -- Recruit Aid module
        Controllers/            -- HTTP handlers
        Controllers/Admin/      -- admin HTTP handlers
        Models/                 -- Recruit-specific entities
        Repositories/           -- SQL for recruit_* tables
        Services/               -- Recruit business logic
        Validation/             -- Recruit input validators
        Deletion/Entities/      -- Recruit-specific deletion handlers

    Helpers/                    -- global helper functions
        functions.php

    HR/                         -- future
    CRM/                        -- future
```

Everything outside `/public` is unreachable over HTTP. Only `public/`
is exposed by Apache.

---

## 3. Request Lifecycle

Every HTTP request follows the same path:

```
1. Apache receives request
2. .htaccess rewrites to public/index.php
3. public/index.php: boot the App
4. App::run():
     a. Request::capture()          — build Request object
     b. Router::dispatch($request)  — find matching route
5. Router::dispatch():
     a. Match path to a route
     b. Build the controller callable
     c. If middleware exists:
          Kernel::run($middleware, $request, $handler)
     d. Otherwise:
          Call the controller directly
6. Kernel::run() — onion middleware pipeline:
     a. Iterate middleware list (outer to inner)
     b. Each middleware may:
          - short-circuit (return a Response)
          - pass through by calling $next($request)
          - wrap the response
     c. The innermost callable invokes the controller
7. Controller returns a Response
8. Response travels back up through middleware (each may modify)
9. Response::send() — headers + body to browser
```

---

## 4. The Middleware Pipeline (Onion Model)

Middleware uses the **onion model** (also called pipeline or layered
middleware):

```
Request ───► [Middleware A] ───► [Middleware B] ───► [Middleware C] ───► Controller
                                                                              │
Response ◄── [Middleware A] ◄── [Middleware B] ◄── [Middleware C] ◄────────────┘
```

**Signature:**

```php
public function handle(Request $request, callable $next): Response
```

Each middleware receives the request **and a `$next` callable**. It may:

1. **Short-circuit** — return its own Response without calling `$next`
2. **Pass through** — call `$next($request)` and return its Response
3. **Wrap** — call `$next($request)`, inspect or modify the response,
   then return

**What this unlocks:**
- Audit logging with response status codes
- Response compression
- `X-Response-Time` and rate-limit headers
- CORS header injection
- Global error transformation
- Request ID propagation

**Registered middlewares** (in `app/Core/Http/Kernel.php`):

| Route spec | Middleware |
|---|---|
| `auth` | `AuthMiddleware` — requires login |
| `guest` | `GuestMiddleware` — requires logged-out |
| `csrf` | `CsrfMiddleware` — verifies token on POST/PUT/PATCH/DELETE |
| `permission:slug` | `PermissionMiddleware` — requires a permission |
| `ratelimit:action,max,window` | `RateLimitMiddleware` — rate limits |
| `audit` | `AuditLoggerMiddleware` — logs state-changing requests |

The audit middleware is applied globally by `Router::dispatch()` — it
runs before every POST/PUT/PATCH/DELETE without needing to be declared
on each route.

Full migration history: `docs/CHANGELOG-middleware.md`.

---

## 5. Layer Responsibilities

### Controllers

- Translate HTTP requests into service calls
- Never touch SQL
- Never contain business logic beyond routing
- Return a `Response`
- **Thin by design** — 20-line controllers are correct

### Services

- Contain business logic
- Called from controllers, cron jobs, CLI commands
- May call other services
- Wrap multi-step operations in transactions
- Return models or DTOs

### Repositories

- Contain **all SQL** for their entity
- Return models, never raw rows
- Filter soft-deleted records by default (`deleted_at IS NULL`)
- Are the only place raw SQL appears (outside of migrations)

### Models

- Represent database rows
- Simple property bags + accessor methods
- No database access, no business logic
- `fromRow(array)` static factory

### Middleware

- Cross-cutting concerns (auth, csrf, audit, rate limit)
- Onion signature: `handle(Request, callable): Response`
- Registered in Kernel, container, and applied via route specs

---

## 6. Dependency Injection

A custom container (`app/Core/Container.php`) provides dependency
injection. Services are registered as singletons in `App::registerServices()`:

```php
$c->singleton(UserRepository::class, fn () => new UserRepository(
    $c->get(Database::class)
));
```

Controllers receive the container via constructor injection:

```php
final class OrganisationController
{
    public function __construct(private Container $container) {}

    public function index(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(Auth::class);
        // ...
    }
}
```

**No autowiring.** Every binding is explicit. If a class isn't
registered, the container throws.

---

## 7. Custom Fields Engine

Any entity can extend itself with custom fields defined at runtime. The
engine is in `app/Platform/CustomFields/`.

**How it works:**

1. Admin defines a field: label, entity type, input type, constraints
2. Field is stored in `core_custom_fields`
3. Values are stored in `core_custom_field_values` keyed by
   `(custom_field_id, entity_type, entity_id)`
4. On entity forms, `CustomFieldRenderer` renders each field using
   its input type
5. On submit, `CustomFieldValueValidator` validates each value against
   its input type's rules, then `CustomFieldValueSaver` persists it

**12 built-in input types** (in `Platform/CustomFields/InputTypes/`):
text, long text, number, email, URL, phone, date, dropdown,
multi-select, checkbox, file, currency.

Each input type implements `InputType` and knows how to render itself,
validate its values, and cast values for display.

---

## 8. Audit & Activity Logging

Two separate logging systems, different purposes:

### `core_activities` — Business Timeline

- Written by `ActivityLogger::log()` (called explicitly by services)
- Human-readable entries ("organisation created", etc.)
- Displayed in the entity's timeline UI
- Used by the "What happened recently" feed on dashboards

### `core_audit_logs` — HTTP Audit Trail

- Written by `AuditLoggerMiddleware` (automatic)
- Every POST/PUT/PATCH/DELETE request
- Captures: user_id, method, path, ip, user_agent, request_hash,
  response_status, entity_type, entity_id, timestamp
- Never edited, never deleted
- Used for security review and support forensics

**Enrichment:** controllers call `AuditContext::set('candidate', $id)`
to attach business context to the current request. The middleware reads
it when writing the row.

---

## 9. Soft Delete Convention

Every soft-deletable table has:

```sql
deleted_at DATETIME NULL
```

**Every read method in every repository filters:**

```sql
WHERE deleted_at IS NULL
```

Deletion sets `deleted_at = NOW()`. Restore sets it back to `NULL`.

**Exceptions:**
- Read methods that explicitly need deleted records (e.g. admin restore
  views) are named `findByIdIncludingDeleted()` or take a
  `$includeDeleted` flag.
- Tables that don't have `deleted_at` (log tables, cross-reference
  tables) are hard-deleted.

**Purge cron** (Tier 5): physically removes records deleted more than
N days ago.

---

## 10. File Storage

Files are stored on disk outside the web root and served through PHP,
never directly by Apache.

**Storage layout:**

```
storage/uploads/
    avatars/            -- user avatars
    branding/           -- logos, favicons
    candidate_docs/     -- CVs, certificates, IDs
    reports/            -- generated exports
```

**Metadata:** every file is recorded in `core_files`. Files can be
attached to any entity via `core_entity_files`.

**Serving:** `FileController::serve($uuid)` looks up the file, checks
permissions against the linked entity, and streams the file with:

- `Content-Type: <sniffed MIME>`
- `Content-Disposition: inline` (or `attachment` for downloads)
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy: default-src 'none'; sandbox`

Apache never serves files from `storage/` directly — `.htaccess` blocks
the entire directory as defense-in-depth.

---

## 11. Notification Rules Engine

Notifications are **data-driven**, not hardcoded. Rules are rows in
`core_notification_rules`:

| Column | Purpose |
|---|---|
| `event_type` | Trigger (e.g. `candidate.created`) |
| `recipient_type` | Who to notify (role, permission, assigned_recruiter, ...) |
| `recipient_value` | The slug/ID for value-bearing types |
| `channel_inapp` | Send in-app? |
| `channel_email` | Send in daily digest? |

**Dispatch flow:**

1. Event fires: `$notificationService->dispatch('candidate.created', $context, $title, $body, $link)`
2. `NotificationRuleService::resolveRecipients()` reads all active
   rules for that event type
3. Each rule resolves to a list of user IDs (via role lookup,
   permission lookup, or context — e.g. "the assigned recruiter")
4. The actor is excluded by default
5. Rows are written to `core_notifications`

**Admin UI** at `/admin/settings/notifications` shows all rules grouped
by event, with toggles for each channel and add/remove capability.

---

## 12. Database Conventions

- **Table names:** `core_*` for shared, `{module}_*` for module-owned
  (see `NAMING-CONVENTION.md`)
- **All SQL** in repository classes
- **All schema changes** via migrations (`database/migrations/`)
- **All seed data** via seeders (`database/seeders/`)
- **Every FK, CHECK, and unique key** named per convention
- **Two database users:**
  - `recruitaid_app` — web app, SELECT/INSERT/UPDATE/DELETE only
  - `recruitaid_migrate` — CLI scripts, full privileges
- **Config split:** `config/local.php` (web) uses the limited user;
  `config/cli.php` uses the elevated user

---

## 13. Route Organization

Routes are declared in `routes/`:

| File | Loaded for |
|---|---|
| `web.php` | All authenticated + public routes |
| `auth.php` | Login, logout, password reset |

**Planned future split** (post-tier-2):
- `routes/recruit.php` — Recruit Aid routes
- `routes/hr.php` — HR Aid routes
- `routes/crm.php` — CRM Aid routes

For now, all routes are in `web.php` and `auth.php`. The URL conventions
already reserve space for the split (`/recruit/*`, `/hr/*`, `/crm/*`).

---

## 14. Views

Views live in `resources/views/`:

```
resources/views/
    layouts/
        app.php         -- authenticated app shell
        auth.php        -- login + password flows
    partials/
        sidebar.php
        topbar.php
        notifications-dropdown.php
    auth/               -- login, forgot password, reset
    dashboard/
    organisations/
    contacts/
    engagements/
    vacancies/
    account/
    admin/
        settings/
    errors/
```

Views are PHP templates. Escaping: `e($value)` for HTML escaping.

---

## 15. Configuration

Three config files:

| File | Purpose | Committed? |
|---|---|---|
| `config/app.php` | Shared defaults (timezone, session, mail) | Yes |
| `config/local.php` | Per-install overrides (DB creds, APP_KEY) | No — gitignored |
| `config/cli.php` | CLI-only DB creds (elevated user) | No — gitignored |

**Rule:** `local.php` overrides values from `app.php`. Never commit
`local.php` or `cli.php`.

---

## 16. Future Architecture

### When HR Aid ships

- New top-level directory: `app/HR/`
- New namespace root: `CorporateAid\HR\*`
- New database: `corporateaid_hr` (or same DB, different table prefix)
- Communication with Recruit Aid via Corporate Connect (event outbox +
  ID mappings)

### When Corporate Connect is built

- Location: `app/Platform/Integrations/`
- Uses `core_integration_events` as the outbox
- Uses `core_integration_mappings` for cross-module ID relationships
- Cron worker drains the outbox every N minutes

### When the module registry ships

- `core_modules` becomes the source of truth for which modules exist
- `core_module_licenses` records which modules this install has licensed
- `core_module_state` tracks which modules are enabled
- Admin UI: "Activate HR Aid" flow

None of this is built yet. The tables exist, empty.

---


---

## 18. Framework Contract (Strict Rules)

This section documents the strict behavioral contracts of the custom
framework classes. It exists because the September/October 2026
development cycle proved that "guessing" how these classes behave
causes silent data loss, unhandled 500s, and invisible errors.

**Rule:** If the code and this contract disagree, the code is wrong.

### 18.1 The Container (Explicit Bindings Only)

- **Contract:** `Container::get(string $id): mixed`. The generic
  PHPDoc (`@template R of object`) tells PHPStan the return type.
- **Rule:** Every class resolved via `get()` MUST be explicitly bound
  in `App::registerServices()`. There is no autowiring.
- **Rule:** Controllers must be bound in the container. If a route
  references a controller that isn't bound, the Router will crash at
  dispatch time.

### 18.2 The Request (Typed Casting)

- **Contract:** `Request::input()` and `Request::query()` return
  `mixed`. 
- **Rule:** Controllers MUST cast values explicitly before using them
  (e.g., `trim((string) $request->input('name'))`). 
- **Rule:** Never access `$_POST` or `$_GET` directly in controllers.

### 18.3 The Response (Factories Only)

- **Contract:** `Response` provides `html()`, `json()`, `redirect()`,
  `text()`, `noContent()`, and `file()`.
- **Rule:** Never instantiate `new Response(...)` directly. Factories
  set the correct headers.
- **Rule:** `Response::file()` streams the file via `readfile()` to
  avoid memory exhaustion. It sets `X-Content-Type-Options: nosniff`
  and `Content-Security-Policy: sandbox`.

### 18.4 The Database (No lastInsertId)

- **Contract:** `Database::insert(string $sql, array $params): int`
  returns the newly inserted ID.
- **Rule:** Do not call `Database::execute()` for INSERTs if you need
  the ID. `execute()` returns affected rows, and `Database` has no
  `lastInsertId()` method.
- **Contract:** `Database::scalar()` returns `mixed`. Repositories
  must cast it before returning.

### 18.5 The Logger (Loud Failure)

- **Contract:** `Logger` writes to `storage/logs/app.log`.
- **Rule:** A silent failure is a bug. If the directory is not
  writable, the logger MUST fail loudly (fallback to `error_log()`).
- **Rule:** Every 500 exception generates an `ERR-xxxxxx` reference
  ID that is shown to the user and written to the log. Support can
  `grep ERR-xxxxxx` to find the exact trace.

### 18.6 File Uploads and Permissions

- **Contract:** `FileController` derives download permission from the
  entity the file is attached to via `core_entity_files`.
- **Rule:** Zero entity links equals zero permissions equals a 404
  response. Fail closed.
- **Rule:** Physical files are stored outside the web root in
  `storage/uploads/` and streamed exclusively through PHP.


## 17. References

- `docs/NAMING-CONVENTION.md` — the naming rules
- `docs/SPECIFICATION.md` — ADRs behind the architecture
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/RENAME-RECORD.md` — how the codebase got to this state
- `docs/REMEMBER.md` — current session state