# REMEMBER — Recruit Aid Project State

**Paste this into a new chat to resume work.**

**Last updated:** 2026-11-14
**Version:** 0.9.0-alpha
**Status:** Candidate lifecycle shipped. RBAC four-axis shipped.
Platform surface shipped. Calendar shipped. Reports shipped.
External-facing features are the next horizon.

---

**See also:**
- `docs/ABOUT.md` — product overview and current feature set
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the 17 ADRs behind the design
- `docs/NAMING-CONVENTION.md` — naming rules for everything
- `docs/RBAC-ARCHITECTURE.md` — the four-axis authorization model
- `docs/CAS-PLATFORM-SURFACE.md` — the `/cas/*` platform surface
- `docs/CHANGELOG.md` — chronological version history
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/RENAME-RECORD.md` — the naming convention execution record

---

## Session Context

**Project:** Recruit Aid — Recruitment Operations & Applicant Tracking System
**Suite:** Corporate Aid Solutions (Recruit Aid is product #1)
**Working directory:** `C:\projects\recruitaid`
**Served at:** `http://localhost:8080`
**Version:** 0.9.0-alpha
**Model:** Sold to recruitment agencies as a white-labelled platform
they own and rebrand
**Target deployment:** Shared cPanel hosting (PHP 8.2+, MySQL 8, Apache)
**Repository:** `bobsplaceshop-art/recruit_aid` on GitHub

---

## Environment

| Tool | Version |
|---|---|
| OS | Windows 11 |
| WSL | Ubuntu |
| Laragon | 2026 v8.7.0 (Apache :8080, MySQL 3306, Mailpit 1025/8025) |
| PHP | 8.3.36 |
| MySQL | 8.4.3 |
| Composer | 2.10.3 |
| Node.js | NOT installed |

**Critical Runtime Notes:**
- **Laragon Terminal** for `php`, `composer`, `mysql`, `php bin/migrate.php`
- **WSL** for file operations (`cat`, `ls`, `grep`, `python3`, `git`)
- **WSL does NOT have php** — always use Laragon Terminal for PHP
- **WSL cannot reach MySQL** — different network namespaces
- **Heredocs** work in WSL only — never in Laragon
- **Git config set:** `user.name`, `user.email`, `credential.helper=store`
- **Never mix contexts in one instruction block**

**Database Users:**

| User | Password | Used by | Privileges |
|---|---|---|---|
| `recruitaid_app` | `recruitaid_app_dev_2026` | Web app | SELECT, INSERT, UPDATE, DELETE |
| `recruitaid_migrate` | `recruitaid_migrate_dev_2026` | CLI only | ALL |

**Config split:**
- `config/local.php` — web app, uses `recruitaid_app`
- `config/cli.php` — CLI scripts, uses `recruitaid_migrate`

**Error logs:**
- `C:\laragon\tmp\php_errors.log` — PHP fatals
- `storage/logs/app.log` — application exceptions via `Logger`
- `grep ERR-xxxxxx storage/logs/app.log` — find a specific error

---

## Database State

**Database:** `corporateaid_recruit`
**Migrations applied:** `0001` through `0074` — **79 migrations total**
**Version cursor:** `0074_fix_core_namespace_scope_v2.sql`

**Migration history notes:**
- Some sequence numbers have duplicate prefixes (e.g.
  `0028_add_delete_permissions.sql` + `0028_create_candidates.sql`).
  Migrations were authored across sessions and applied by
  alphabetical sort within sequence. Not a bug.
- `0039z_rename_client_org_and_add_tenant_id.sql` uses a `z` suffix to
  guarantee ordering after `0039_create_tenants.sql`. Pattern for
  ordering-constrained migrations.
- Migrations are **immutable** once applied. Corrections go in new
  migrations (see `0073`/`0074` as a worked example).

**Modules registered:** `recruit` (enabled), `hr` (registered, not
enabled). `core_module_state` shows `recruit` only.

**Users (seeded for dev):**

| Email | Role |
|---|---|
| `admin@recruitaid.test` | admin (tenant role) |
| `researcher@recruitaid.test` | researcher (tenant role) |
| `cas_admin@recruitaid.test` | cas_admin (platform role, no tenant) |
| `cas_developer@recruitaid.test` | cas_developer (platform owner) |

**Development only.**

---

## Completed Modules (v0.9.0-alpha)

Verified against `app/Recruit/`, `routes/web.php`,
`database/migrations/`, and `tests/Integration/`.

### Foundation
Custom PHP MVC framework, config system, error handling with reference
IDs, front controller, migrations + seeders, three-tier namespace
(Core / Platform / Recruit), explicit-bindings container.

### Authentication & Authorization
Login, logout, password change, password reset with email, session
security, CSRF, Argon2id hashing, rate limiting on auth endpoints,
re-auth gate with pending-save preservation.

Four-axis RBAC model:
- Home tenant (identity)
- Platform authority (`cas.*` via `core_user_platform_roles`)
- Tenant authority (`core_user_roles`, `core_user_permissions`,
  `core_permission_policies`)
- Developer super-administrator authority (`platform_owners` + Rule 3b)
- Support delegations (`core_support_delegations`) with asymmetric
  revocation
- Step-up re-auth on `/cas/context/switch`
- `ProtectPlatformOwnerMiddleware` guards owner accounts
- `core_rbac_audit_logs` captures every RBAC mutation

### CAS Platform Surface
`/cas/dashboard`, `/cas/tenants` (CRUD lifecycle), `/cas/delegations`
(grant/revoke/cancel), `/cas/context` (switch/reset), `/cas/calendar`.

### Client Management
Organisations and contacts — full CRUD, custom fields, sequence
counters, archive/restore/delete, per-org contacts tab.

### Engagements
Service types, package templates, capacity meter, recruiter assignments.

### Vacancies
Pipeline stages cloned per vacancy (14 defaults), hiring managers,
recruiters, add-candidate action, custom fields.

### Candidates
Central candidate database with education, experience, skills, tags.
Person identity deduplication via `core_persons`. Tenant isolation at
repository level with tests. Archive/restore/delete.

### Applications
Candidate × vacancy with per-vacancy pipeline stage tracking. Stage
history, move between stages, auto-advance with passed-through history.
Schedule-interview and make-offer actions from application detail.

### Interviews & Scorecards
Schedule from application, participant management, status lifecycle,
scorecards with attribute-based scoring.

### Offers
Full lifecycle: draft → send → accept/decline/withdraw. Offer history.
`PersonHiredEvent` dispatched on accept via event bus.

### Reports
Pipeline funnel, time-to-hire (with CSV export), recruiter
productivity. CSV export at `/recruit/{entity}/export.csv`.

### Calendar (RA-2)
Month view at `/recruit/calendar` and `/cas/calendar`. Aggregates
interviews, offers, tasks, vacancy deadlines.

### Tasks (RA-1)
Full task management with entity attachment, priorities, due dates,
assignee, statuses.

### Global & Live Search
Cross-entity global search, AJAX autocomplete, full-text API.

### Activity & Audit
Per-entity timeline, global activity feed, HTTP audit trail, RBAC
audit trail.

### Notifications
In-app bell, rules engine with 17 defaults, admin UI.

### Custom Fields Engine
12 self-validating input types, admin field builder, per-entity
support, file uploads via middleware.

### Configurable Lookups
Admin-managed enums at `/recruit/admin/lookups`.

### File Storage
UUID-based, streamed via PHP with permission checks.
`/recruit/files/{uuid}`.

### Admin
Users, roles, permissions matrix, custom fields, lookups, branding,
email templates, audit, notification rules, settings.

### Design System
Themes, sidebar shell, component library, notification bell.

---

## Test Coverage

**Integration tests (PHPUnit):** 63 tests, all passing.
**Isolation suites (CLI):** 7 tenant-boundary test files.
**Authorization suite (CLI):** 24 precedence-chain assertions.
**Unit tests:** 5.

Run:

    composer test

Expected: `OK (63 tests, N assertions)`.

Isolation suites (Laragon Terminal):

    php tests\candidate_repository_tenant_isolation_test.php
    php tests\organisation_repository_tenant_isolation_test.php
    php tests\engagement_assignment_tenant_isolation_test.php
    php tests\interview_scorecard_tenant_isolation_test.php
    php tests\live_search_tenant_isolation_test.php
    php tests\vacancy_tenant_isolation_test.php

Authorization suite:

    php tests\authorization_test.php

---

## What's Next (v0.10.0-alpha targets)

### Priority 1 — PHPStan cleanup

`composer analyse` currently times out at 300s and reports 100+ errors.
ABOUT.md previously claimed "Level 9, 0 errors" — that claim is stale.

1. Read full output:
   `vendor\bin\phpstan analyse --memory-limit=2G --no-progress --timeout=600`
2. Group errors by class (cast/mixed, return type, date() int|false,
   missing iterable value type).
3. Fix in batches, one category per commit.
4. Update ABOUT.md and CHANGELOG.md once clean.

### Priority 2 — External-facing features (the remaining 25%)

**Candidate self-apply** — highest value. Public form at
`/jobs/{slug}/apply`. Requires public routes file
(`routes/public.php` — currently missing), rate limiting, CAPTCHA or
similar bot protection, resume parser or manual upload, anti-duplicate
check on email.

**Public job board** — `/jobs`, `/jobs/{slug}`. Public listing scoped
to `is_public = 1` vacancies.

**Client portal** — external hiring managers. Requires invite link
auth flow, permissions scoped to one organisation, separate layout.

**Candidate portal** — candidate self-service. Email + token auth,
application status view, re-apply prevention.

### Priority 3 — Peripheral ATS features

- **Assessments module** — controller, service, route.
- **Reference checks** — controller, service, route.
- **Email sync / two-way communication** — IMAP inbound, thread per
  candidate.
- **2FA UI** — schema exists; UI at `/recruit/account/2fa`.
- **Payments (Stripe / M-Pesa)** — stub at minimum.

### Priority 4 — Cross-module operability

- **HR Aid activation** — `core_module_state.is_enabled = 1` for hr;
  routes for `/hr/dashboard` and above; `PersonHiredHandler`
  integration verification.
- **Corporate Connect worker** — background cron that drains
  `core_integration_events`.

---

## Doctrine — Rules Learned the Hard Way

Each rule has a real failure attached.

1. Read dependencies before rewriting. Read the file, its callers, one
   sibling for pattern reference.

2. Never use `CREATE TABLE IF NOT EXISTS` in migrations for tables we
   expect to create.

3. No hand-edits for diagnostics. Full-file rewrite or linted Python
   injector only.

4. Full-file rewrites only. No `python3 replace(old, new)` on files
   not just read.

5. `composer dump-autoload` after creating any new PHP class.

6. Command context matters. Heredocs run in WSL only. PHP / Composer /
   MySQL run in Laragon only.

7. `Request::input()` requires a real key. Use `Request::all()` for
   the full payload.

8. Re-auth redirects target GET pages, not POST endpoints.

9. Pending-save data must be consumed somewhere.

10. Every response status must be a standard code PHP recognizes.

11. 404s on unbuilt menu items are expected.

12. Always test the full loop, not just the last step.

13. Never mix contexts in one instruction block. Label each step.

14. Verify a class's actual directory before referencing its namespace.

15. Migrations and seeders drift separately. Both need the same
    treatment in the same pass.

16. Migrations run in order on an empty DB. Any migration that depends
    on a later migration's table is a bug that surfaces only on a
    fresh install.

17. Always test a fresh-install path after every non-additive
    migration.

18. Column references in code can masquerade as counter keys or table
    names during grep audits.

19. Doctrine rules survive renaming.

20. If the error handler cannot write, it cannot help.

21. Every working state gets a git commit. Every finished feature gets
    a tag.

22. The repository return shape is the contract.

23. Shell redirection characters in pasted commands create phantom
    files. `git status` surfaces them.

24. `git ls-remote` before the first push.

25. Cross-cutting concerns belong in middleware. CSRF, rate limiting,
    audit logging, file uploads — all middleware.

26. Business-rule rejections are not exceptions. Flash + redirect,
    not `RuntimeException`.

27. Relationship management is bidirectional.

28. When two files claim the same route, check the `use` clause.

29. `php -l` proves syntax; `grep` proves presence. A file can be
    syntactically perfect and missing the change. Every write cycle
    ends with a `grep` of the anchor.

30. A migration's WHERE clause must match the actual data, not the
    data you assume. Migration `0073` missed every `core.*`
    permission because the WHERE clause used `module = 'core'` but
    the actual rows have `module = 'core.settings'` etc. Migration
    `0074` fixed it by using `slug LIKE 'core.%'`.

31. Rule 3b (`platform_owners`) is contractual authority, not a role.
    It bypasses tenant scoping for `scope='app'` permissions but not
    `scope='global'` platform permissions.

32. The portal tile is the last mile of the resource-gating
    invariant. `cas.dashboard.access` grants the dashboard, not
    every resource on it. Tiles render in three states: active,
    no_access, coming_soon.

---

## Backups

Backups are git-based. Pushing to `bobsplaceshop-art/recruit_aid` is
the backup.

Recovery: `git clone https://github.com/bobsplaceshop-art/recruit_aid.git`
followed by `composer install`, config setup, `php bin/migrate.php`,
`php bin/seed.php`.

---

## How to Resume in a New Chat

Paste this file as the first message, then:

> "Resume from where we left off. Version 0.9.0-alpha. Candidate
> lifecycle, RBAC four-axis, platform surface, calendar, and reports
> are all shipped. Next priority: [PHPStan cleanup / Candidate
> self-apply / Public job board / HR Aid activation]."

Two commands to verify health:

    cd C:\projects\recruitaid
    php bin\migrate.php status

Expected: `Total: 79 available, 79 applied.`

    composer test

Expected: `OK (63 tests, ...)`

---

## Quick Reference — Where Things Live

| What | Where |
|---|---|
| Framework | `app/Core/` |
| Shared services, models, middleware | `app/Platform/` |
| Recruit module code | `app/Recruit/` |
| HR module code | `app/Modules/HR/` (registered, disabled) |
| Helper functions | `app/Helpers/` |
| Migrations | `database/migrations/` |
| Seeders | `database/seeders/` |
| Views | `resources/views/` |
| Routes | `routes/web.php`, `routes/auth.php` |
| Container bindings | `app/Core/App.php` `registerServices()` |
| Middleware registry | `app/Core/Http/Kernel.php` |
| Config (web) | `config/local.php` (gitignored) |
| Config (CLI) | `config/cli.php` (gitignored) |
| Application log | `storage/logs/app.log` |
| File uploads | `storage/uploads/` (blocked from direct access) |
| Documentation | `docs/` |
| RBAC doctrine | `docs/RBAC-ARCHITECTURE.md` |
| Platform surface | `docs/CAS-PLATFORM-SURFACE.md` |
| Test map | `docs/RBAC-TESTING.md` |

---

End of REMEMBER
