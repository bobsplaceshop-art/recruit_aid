# REMEMBER v6 — Recruit Aid Project State

**Paste this into a new chat to resume work.**

**Last updated:** 2026-10-27
**Version:** 0.6.0-alpha
**Status:** Tier 1 closed. Naming convention cycle complete. Ready for Tier 2.

---

**See also:**
- `docs/ABOUT.md` — product overview and current feature set
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the 17 ADRs behind the design
- `docs/NAMING-CONVENTION.md` — naming rules for everything
- `docs/CHANGELOG.md` — chronological version history
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/RENAME-RECORD.md` — the naming convention execution record
- `README.md` — quick start

---

## Session Context

**Project:** Recruit Aid — Recruitment Operations & Applicant Tracking System
**Suite:** Corporate Aid Solutions (Recruit Aid is product #1)
**Working directory:** `C:\projects\recruitaid`
**Served at:** `http://localhost:8080`
**Version:** 0.6.0-alpha
**Model:** Sold to recruitment agencies as a white-labelled platform
they own and rebrand
**Target deployment:** Shared cPanel hosting (PHP 8.2+, MySQL 8, Apache)

---

## Environment

| Tool | Version |
|---|---|
| OS | Windows 11 |
| WSL | Ubuntu |
| Laragon | 2026 v8.7.0 (Apache :8080, MySQL 3306, Mailpit 1025/8025) |
| PHP | 8.3.33 |
| MySQL | 8.4.3 |
| Composer | 2.10.3 |
| Node.js | NOT installed |

**Critical Runtime Notes:**
- Laragon Terminal for `php`, `composer`, `mysql` commands
- WSL for file operations (`cat`, `ls`, `grep`, `python3`)
- **WSL does NOT have php** — always use Laragon Terminal for PHP
- **WSL cannot reach MySQL** — different network namespaces
- **Heredocs (`cat > file << 'EOF'`) work in WSL only** — never in
  Laragon CMD
- **HeidiSQL** is the GUI DB client (connects to `127.0.0.1`)
- **Browser DevTools Console** for JavaScript debugging (F12)

**Database Users:**

| User | Password | Used by | Privileges |
|---|---|---|---|
| `recruitaid_app` | `recruitaid_app_dev_2026` | Web app | SELECT, INSERT, UPDATE, DELETE |
| `recruitaid_migrate` | `recruitaid_migrate_dev_2026` | CLI only | ALL |

**Config split:**
- `config/local.php` → web app, uses `recruitaid_app`, database
  `corporateaid_recruit`
- `config/cli.php` → CLI scripts, uses `recruitaid_migrate`, database
  `corporateaid_recruit`

**Error logs (Laragon):**
- `C:\laragon\tmp\php_errors.log` — PHP fatals
- `storage/logs/app.log` — application-level exceptions (via `Logger`)
- `grep ERR-xxxxxx storage/logs/app.log` — find a specific error by
  its reference ID

---

## Database State

**Database:** `corporateaid_recruit`
**Migrations applied:** `0001` through `0026`
**Total tables:** 53

**Table prefixes:**
- `core_*` — shared platform tables (43 of them)
- `recruit_*` — Recruit Aid module tables (10 of them)

**Sequence counters in use:**
- `rec_client_org_code`
- `rec_client_contact_code`
- `rec_engagement_code`
- `rec_vacancy_code`

**Users (seeded):**

| Email | Role |
|---|---|
| `admin@recruitaid.test` | super_admin |
| `researcher@recruitaid.test` | researcher |

Passwords: `AdminPass!2026` and `ResearchPass!2026` respectively.
**Development only.**

---

## Completed Modules

### Foundation ✅
Custom PHP MVC framework, config system, error handling with reference
IDs, front controller, migrations, seeders, three-tier namespace
(`CorporateAid\Core\*`, `CorporateAid\Platform\*`,
`CorporateAid\Recruit\*`, `CorporateAid\Helpers\*`)

### Authentication & Authorization ✅
Login, logout, password change, password reset flow with email, session
security, CSRF, Argon2id hashing, 7 roles, 45+ permissions, permission
middleware, re-auth gate with pending-save preservation, human-friendly
permission slugs (`core.*`, `recruit.*`)

### Design System ✅
Dark / Light / System themes with per-user preference, sidebar shell,
component library, notification bell

### Custom Fields Engine ✅
12 self-validating input types, field builder admin UI, live preview,
auto-render, auto-validate, archive vs. delete

### Deletion Workflow ✅
Reusable `DeletionService`, `DeletableEntity` interface, confirmation
phrases, modal

### Organisations Module ✅
Full CRUD, search/filter/pagination, custom fields, archive, delete,
sequence counter

### Contacts Module ✅
Per-organisation contacts, role flags, custom fields, full CRUD

### Engagements Module ✅
Service types, package templates, capacity meter, recruiter assignment
with user dropdown, custom fields, full CRUD, sequence counter

### Vacancies Module ✅
Full schema with pipeline stages (14 defaults cloned per vacancy),
custom fields, capacity check hook, engagement/contact dropdowns
filtered by org, full CRUD, sequence counter

### Settings Hub ✅
System settings with server-side validation, security settings page,
notification rules page

### Notification System ✅
Bell + badge + dropdown + AJAX polling, configurable rules
(`core_notification_rules`, 17 default rules seeded), human-friendly
labels, actor-exclusion on settings changes, admin UI at
`/admin/settings/notifications`

### Middleware Pipeline — Onion ✅
All 5 middlewares converted to
`handle(Request, callable): Response`. Compatibility bridge removed.
Unlocks post-controller behavior. Deep dive:
`docs/CHANGELOG-middleware.md`.

### Audit Log System ✅
`core_audit_logs` captures every state-changing HTTP request. Applied
globally via `Router::dispatch()` — no per-route opt-in. Captures
user_id, method, path, ip_address, user_agent, request_hash,
response_status, entity_type, entity_id, timestamp. Failures captured
with their status codes.

### Error Reference IDs ✅
Every 500 generates a short `ERR-xxxxxx` ID (6 hex chars), prefixed to
the log line and shown on the error page. Support can `grep ERR-xxxxxx
storage/logs/app.log`.

### Security Headers ✅
CSP via `.htaccess` (strict, `'unsafe-inline'` for now),
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`X-Powered-By` stripped.

### File Upload Foundation ✅
`core_files` + `core_entity_files` tables, `FileService` orchestrating
upload → validate → store → link, `FileController` streaming through
PHP with permission checks, `storage/uploads/.htaccess` blocking direct
access. Custom field integration in progress.

### Platform Foundation Tables ✅
22 new tables added empty for future features: module registry,
licenses, module state, integrations, integration logs, scheduled
tasks, health checks, updates available/history/manifest, currencies,
notes, feature flags, webhooks, email templates, GDPR data requests,
consent log, login history, user permissions, workflow engine.

### Database Conventions ✅
All tables prefixed `core_*` / `recruit_*`, all FKs and CHECK
constraints renamed per convention, two DB users, database renamed to
`corporateaid_recruit`.

---

## What's Next

**Tier 2 — remaining items:**

1. **File uploads custom field integration** — in progress. The
   `FileType` custom field input renders but doesn't yet persist
   uploads through `FileService`.
2. **Activity timeline UI** — `core_activities` is logged but no view
   displays it. Add a timeline component to entity detail pages.
3. **Duplicate detection** — email-based dedup check when creating
   candidates/orgs.
4. **Timezone-correct display** — server timezone is `Africa/Nairobi`;
   users can have their own. Display times in the viewer's timezone.
5. **Pipeline stages admin UI** — 14 defaults seeded; no UI to
   customize per organisation.
6. **Engagement capacity enforcement** — meter shows numbers; no
   warning when exceeded at create time.
7. **CSV export** — no list exports yet.
8. **Retention cron + right to erasure** — GDPR enforcement.
9. **2FA UI** — schema columns exist; no UI.
10. **Configurable webhooks** — async delivery queue + cron worker.
11. **Email templates admin UI** — currently hardcoded.
12. **Consent management UI** — schema exists; no UI.

**Tier 3 — core ATS modules:**

Candidates, Applications, Application stage history, Interviews +
scorecards, Offers, Reference checks, Assessments, Tasks, Notes UI,
Reports + funnel analytics, Talent pool / redeployment.

**Tier 4 — business model:**

Client portal, Candidate portal, Public job board, Self-registration +
payments (Stripe / M-Pesa), Multi-channel job posting.

**Tier 5 — operability & advanced:**

Server Manager UI, Configurable lookup system, Health check endpoint,
Email queue + cron, Down migrations, AI candidate matching, License
activation UI, Corporate Connect worker, Update system.

---

## Doctrine — Rules Learned the Hard Way

These rules were accumulated across sessions. Each one has a real
failure attached. They are not suggestions.

1. **Read dependencies before rewriting.** For any file being changed,
   read the file, its callers, and one sibling file for pattern
   reference. State what was read at the top of the proposal.

2. **Never use `CREATE TABLE IF NOT EXISTS`** in migrations for tables
   we expect to create. Verify existence with `DESCRIBE` first, or use
   a fresh name. Silent no-op wasted two hours.

3. **No hand-edits for diagnostics.** All diagnostic code — even
   temporary — goes in via full-file rewrite or a WSL Python injector
   that lints afterward. Manual edits to brace-sensitive PHP files have
   a proven failure rate.

4. **Full-file rewrites only.** No `python3 replace(old, new)` patches
   on files we haven't just read. Every change gets a full-file
   heredoc.

5. **`composer dump-autoload` after creating any new PHP class.** The
   autoloader is `--optimize --authoritative`; it does not discover new
   files at runtime.

6. **Command context matters.** Heredocs (`<< 'EOF'`) run in WSL only.
   PHP / Composer / MySQL commands run in Laragon Terminal only. Never
   mix them in a single instruction block.

7. **`Request::input()` requires a real key.** Never pass empty string.
   For the full payload, use `Request::all()`.

8. **Re-auth redirects must target GET pages, not POST endpoints.**
   After re-auth, the browser makes a GET request.

9. **Pending-save data must be consumed somewhere.** Storing it in
   session is half the fix; the read side must exist.

10. **Every response status must be a standard code PHP recognizes.**
    Non-standard codes like 419 fall back silently. Prefer 403, 401,
    422, 429, 451.

11. **404s on unbuilt menu items are expected.** The sidebar links to
    routes that aren't registered yet. Confirm the URL in the browser
    bar before assuming a bug.

12. **Always test the full loop, not just the last step.** "No more
    404" was not the same as "the save worked." The pending-save bug
    was invisible until end-to-end verification.

13. **Never mix contexts in one instruction block.** Heredocs go in
    WSL. PHP commands go in Laragon. Label each step explicitly.

14. **Verify a class's actual directory before referencing its
    namespace.** The rename cycle produced 42 misplaced `use`
    statements because we trusted a pattern instead of the file tree.

15. **Migrations and seeders drift separately.** Both need the same
    rename treatment, in the same pass, or the seeder will fail on a
    fresh install.

16. **Migrations run in order on an empty DB.** Any migration that
    depends on a table created by a later migration is a bug that will
    only surface on a fresh install.

17. **Always test a fresh-install path after every non-additive
    migration.** Migrations that pass on an existing DB can fail on an
    empty one (migration 0008 proved this).

18. **Column references in code (form field names, view keys) can
    masquerade as counter keys or table names during grep audits.**
    Verify context before assuming a match means a bug.

19. **Doctrine rules survive renaming.** Well-designed rules are tied
    to *behavior*, not to specific file paths or names. The rules
    above applied throughout the rename cycle unchanged.

20. **If the error handler cannot write, it cannot help.** A logger
    that silently fails when `is_writable()` returns `false` is worse
    than no logger — it produces the illusion of observability while
    hiding every failure. Either log loudly (fall back to `error_log()`
    when the primary path is not writable) or refuse to boot. Verify
    `is_writable(storage/logs/)` after any filesystem change.

21. **Every working state gets a git commit. Every finished feature
    gets a tag.** The September 25 rename cycle left `RecruitAid\`
    namespaces in 12 view files because there was no commit-based
    diff to catch them. From now on: commit at every working state,
    tag at every completed feature. Tarballs are for wanton
    destruction only. `git log -p` is the recovery tool.

22. **The repository return shape is the contract. Controllers and
    views are consumers.** A `paginate()` returning `['rows' => ...]`
    while the controller reads `$result['items']` produces a runtime
    500 with no compile-time signal. Fix: every repository method
    that returns an array gets a PHPDoc `@return array{...}` shape.
    PHPStan Level 9 will refuse to pass without it. Add the shape in
    the same commit that adds the method.

23. **Shell redirection characters (`>`, `<`, `|`) in pasted commands
    create phantom files.** Files named `12,`, `5,`, `getMessage()`,
    `path()` were sitting in the project root for weeks from a stray
    `>` in an earlier session. `git status` surfaces them immediately.
    Tar backups do not. If a `git status` shows an unexpected file
    with a weird name, do not stage it — investigate. It's almost
    always a shell accident.

24. **`git ls-remote` before the first push.** Always confirm the
    target GitHub repo is empty (or has known history) before
    pushing. An unexpected initial commit from the GitHub UI (README,
    LICENSE) turns a clean first push into an unrelated-history
    merge. Ten seconds of `ls-remote` prevents thirty minutes of
    merge conflict resolution.

---

41. **Cross-cutting concerns belong in middleware.** A feature that must
    run before every controller (CSRF, rate limiting, audit logging, custom
    field file uploads) belongs in middleware. A feature that runs for one
    specific controller belongs inline. The file-upload saga (2026-10-29)
    proved the cost: per-controller plumbing required touching 2 controllers
    + 2 services for every entity type, and every new module would have
    needed the same. The middleware pattern means zero changes for new
    modules, and it's the pattern to follow for any future cross-cutting
    concern.

42. **Business-rule rejections are not exceptions.** When a controller
    detects a valid user request that cannot proceed due to a business
    rule (duplicate name, users assigned, system role, capacity exceeded),
    it should flash an error message and redirect. Throwing RuntimeException
    and letting App::renderException() catch it produces a 500 page with an
    ERR-xxxxxx reference — misleading, because nothing is actually broken.
    The exception handler is for unexpected bugs, not expected rejections.

    - 500 errors = unexpected bugs (uncaught exceptions, DB failures)
    - 403 errors = authorization failures (HttpException::forbidden())
    - 404 errors = resource not found (HttpException::notFound())
    - Flash + redirect = business rule rejections

43. **Relationship management is bidirectional.** When two entities have
    a many-to-many relationship (users to roles, organisations to
    recruiters, etc.), provide management from both sides: user form lists
    available roles, role form lists assigned users. Each side has its own
    guard (can't delete role with users, can't unassign super_admin from
    yourself), its own flash, and its own link back to the other side.
    This eliminates the "I have to remember where I saw that" cognitive
    load. Example: role edit page shows "Users with this role" with Remove
    buttons; user edit page shows role checkboxes.

44. **When two files claim the same route, check the `use` clause.** The
    /admin/users 500 bug (2026-10-29) came from `use UserController as
    AdminUserController;` in routes/web.php — the alias hid the fact that
    two controllers existed, and the older (broken) one was winning.
    `grep -n "use .*Controller" routes/web.php` before debugging any route
    that seems broken. Aliases in `use` statements are a footgun.

## How to Resume in a New Chat

Paste this file as the first message, then say:

> "Resume from where we left off. Tier 1 is closed, naming convention
> cycle is complete. Next: Tier 2 — finish file uploads custom field
> integration."

**Two commands to verify health (Laragon Terminal):**

```cmd
cd C:\projects\recruitaid
php bin\migrate.php
```
Expected: `Nothing to apply. All migrations are up to date.`

```cmd
composer dump-autoload
```
Expected: `Generated optimized autoload files containing 346 classes`

**One command to verify database (HeidiSQL or Laragon Terminal):**

```sql
SHOW TABLES FROM corporateaid_recruit;
```
Expected: ~53 tables, all prefixed `core_*` or `recruit_*`.

---

## Backup History

Location (in WSL): `~/recruitaid-backups/`

| Milestone | Description | Size |
|---|---|---|
| milestone1 | Framework scaffold | 16K |
| milestone2 | Auth + design system | 31K |
| milestone3 | Custom fields + Organisations | 32K |
| milestone4 | Deletion workflow | 88K |
| milestone5 | (intermediate) | 102K |
| milestone6 | Engagements done | 113K |
| milestone7 | Password reset | 143K |
| milestone8 | Tier 1 core | 157K |
| milestone9 | Notifications + human labels | 170K |
| milestone10 | Onion middleware | 175K |
| milestone11 | Audit log live | 178K |
| milestone12 | Re-auth fixed | 179K |
| milestone13 | Tier 1 closed | 184K |
| milestone14 | **Naming convention complete** | **208K** |

**Database dumps:** in `~/recruitaid-backups/db/`

**Latest DB dump:** `corporateaid-recruit-schema-milestone14.sql` (93K)

**Restore command:**

```bash
cd /mnt/c/projects/recruitaid
tar -xzf ~/recruitaid-backups/recruitaid-milestone14-naming-complete-*.tar.gz
```

---

## Known Issues / Backlog

- Configurable lookup system — hardcoded enums need migration to a
  `lookups` table
- File upload storage — foundation exists, custom field integration
  in progress
- 2FA UI — schema exists, no UI
- No CSP nonces — inline scripts and styles require `'unsafe-inline'`
- Custom field errors shown at top of form — not per-field inline
- No Git repository — backups are manual tarballs
- Route file split (`routes/recruit.php`, `routes/hr.php`) is planned
  but not done — all routes are in `web.php` and `auth.php`
- `audit_logs_legacy` table — leftover from migration 0021; safe to
  drop in future cleanup
- Index and unique key names grandfathered (see
  `docs/NAMING-CONVENTION.md` §21.6)
- Three placeholder routes for admin features not yet built
  (`/admin/audit`, `/admin/branding`, `/admin/email-templates`) return
  404s

---

## Middleware Model Reference

**Current model:** ONION. Every middleware receives
`handle(Request, callable $next): Response`.

**Full details:** `docs/CHANGELOG-middleware.md` and
`docs/ARCHITECTURE.md` §4.

**Route spec grammar:**

- `['auth']` → `AuthMiddleware`
- `['guest']` → `GuestMiddleware`
- `['csrf']` → `CsrfMiddleware`
- `['permission:recruit.organisations.view']` → `PermissionMiddleware`
  with arg
- `['ratelimit:login,5,15']` → `RateLimitMiddleware` with args
- `['audit']` → `AuditLoggerMiddleware` (applied globally)

**New middleware checklist:**

1. Create `app/Platform/Middleware/YourMiddleware.php`
2. Signature:
   `handle(Request $request, callable $next): Response`
3. Register in `app/Core/Http/Kernel.php` `$map`
4. Register in `app/Core/App.php` container
5. Apply to routes: `['yourname']` or `['yourname:arg']`
6. Run `composer dump-autoload`

---

## Quick Reference — Where Things Live

| What | Where |
|---|---|
| Framework | `app/Core/` (`CorporateAid\Core\*`) |
| Shared services, models, middleware | `app/Platform/` (`CorporateAid\Platform\*`) |
| Recruit module code | `app/Recruit/` (`CorporateAid\Recruit\*`) |
| Helper functions | `app/Helpers/` (`CorporateAid\Helpers\*`) |
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

---

End of REMEMBER v7