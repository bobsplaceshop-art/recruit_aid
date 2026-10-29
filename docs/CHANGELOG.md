# Recruit Aid — Changelog

All notable changes to Recruit Aid are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

**See also:**
- `docs/ABOUT.md` — product overview and current feature set
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the design decisions behind it
- `docs/CHANGELOG-middleware.md` — deep dive on the onion migration
- `docs/RENAME-RECORD.md` — deep dive on the naming convention cycle
- `docs/REMEMBER.md` — current session state

---

## [0.7.0-alpha] — 2026-10-29

The "permissions and file uploads" release. Granular role-based access
control with full UI management, dynamic sidebar visibility, and a
middleware-based file upload architecture for custom fields. Also
shipped PHPStan Level 9 compliance (448 errors eliminated, 0 remaining).

### Added

**Custom field file uploads (middleware-based)**
- `CustomFieldFileMiddleware` intercepts `cf_*_file` uploads on every
  POST/PUT/PATCH request
- Stores the file via `FileService`, injects the resulting UUID into
  the request body
- `CustomFieldValueSaver` links the file to the entity and saves the
  value
- Works for any current or future module — zero per-controller code
- Preserves the legacy `$files` argument path for backward compatibility
- Handles PUT method spoofing (edit forms) correctly

**Granular roles & permissions management**
- `/admin/roles-perm/roles` — role list with Create/Edit/Delete
- `/admin/roles-perm/roles/create` — create new role
- `/admin/roles-perm/roles/{id}/edit` — edit role (name, description)
- `/admin/roles-perm/permissions?role_id=X` — permission matrix
  with role selector, human-readable labels, and save
- `/admin/roles-perm/roles/{id}/edit` shows "Users with this role"
  table with inline Remove buttons
- `POST /admin/roles-perm/roles/{id}/users/{userId}/remove` — detach
  a user from a role (preserves other roles)

**User management CRUD**
- `/admin/users` — list all users with status, roles, last login
- `/admin/users/create` — create user with role assignment
- `/admin/users/{id}/edit` — edit user (name, email, status, roles,
  optional password change)
- Role checkboxes on user form show name + system badge + description

**Dynamic sidebar visibility**
- Sidebar items wrapped in `$user->can('permission.slug')`
- Users see only nav items their roles grant them
- Super admins bypass all checks via `User::isSuperAdmin()`
- Changes take effect on next page load after permission update

**Branded 403 page**
- `PermissionMiddleware` now throws `HttpException::forbidden()`
- `App::renderException()` renders `errors/403.php`
- Page shows shield icon, message, required permission, and
  Back / Go-back buttons

**PHPStan Level 9 compliance**
- 448 errors eliminated (0 remaining, PHP 8.3 with `phpVersion: 80300`)
- `composer analyse` script for one-word verification
- `composer.json platform.php` bumped to 8.3.36 to match runtime

### Changed

- **Only `super_admin` is a system role.** All other seeded roles
  (admin, recruitment_manager, recruiter, researcher, client_admin,
  client_user) are now `is_system = 0` — admins can rename, delete, or
  replace them without touching code.
- Seeder updated so fresh installs get the correct `is_system` values.
- Migration `0027_demote_non_super_admin_roles.sql` fixes existing installs.

### Fixed

- **`/admin/users` was broken** — route aliased the old (incomplete)
  `UserController` to the name `AdminUserController`, hiding the fact
  that two controllers existed. Removed the alias.
- **Role delete produced a 500 page** instead of a flash message when
  the role had users assigned. Business-rule rejections now use flash +
  redirect (Doctrine #42).
- **`PermissionMiddleware` returned raw HTML** for 403s instead of
  using the branded error page. Now throws `HttpException::forbidden()`.
- **`functions.php` full-file rewrite** accidentally dropped
  `csrf_field()` and broke every form. Restored from backup and fixed
  `config()` to resolve `Config::class` instead of `'config'`.
- **`Hasher.php`** was analyzing for PHP 7.4 while running on 8.3.
  `phpstan.neon` now specifies `phpVersion: 80300`. Narrowed
  `algorithm()` return type to `string`; replaced dead `=== false`
  check with `try/catch (ValueError)`.

### Removed

- `app/Platform/CustomFields/FileCollector.php` — replaced by middleware
- `app/Recruit/Controllers/Admin/UserController.php` — superseded by
  `AdminUserController.php`

---

## [0.6.0-alpha] — 2026-09-25

The "foundation hardening" release. Renamed the entire codebase to
adopt a comprehensive naming convention, added the audit log system,
made notifications configurable, and shipped error reference IDs.

### Added

**Middleware pipeline (onion model)**
- Full refactor from gate-style to onion-style middleware
- `Kernel::run()` builds a nested callable chain
- All 5 middlewares converted to
  `handle(Request, callable): Response`
- Compatibility bridge removed after migration
- Post-controller behavior unlocked (audit, timing, compression,
  headers, error transformation)
- Deep dive: `docs/CHANGELOG-middleware.md`

**Audit log system**
- `core_audit_logs` table — HTTP request shape
- `AuditLogger` service — writes rows
- `AuditLoggerMiddleware` — captures every state-changing request
- `AuditContext` — request-scoped entity context
- Applied globally via `Router::dispatch()` — no per-route opt-in
- Captures: user_id, method, path, ip_address, user_agent,
  request_hash (SHA-256), response_status, entity_type, entity_id,
  timestamp
- Failures captured with their status codes (403, 404, 419, 500, etc.)

**Notification system — configurable**
- `core_notification_rules` table
- 17 default rules seeded (settings changes, rate limits, candidate
  events, interview events, offer events, task assignments, user
  changes)
- `NotificationRuleService::resolveRecipients()` — resolves rules to
  user IDs
- Human-friendly labels in the admin UI
- Actor-exclusion on settings changes (the actor doesn't get notified
  about their own action)
- Admin UI at `/admin/settings/notifications`

**Human-friendly labels everywhere**
- `SystemSettingService::getHumanLabel()` — resolves setting keys
- `SystemSettingService::formatValue()` — formats values with proper
  units (seconds → minutes, etc.)
- Notification events and recipients resolve to natural language
- No more raw DB slugs leaking to the UI

**Error reference IDs**
- Every unhandled 500 generates a short `ERR-xxxxxx` ID (6 hex chars)
- ID is prefixed to the log line and shown on the 500 page
- `grep ERR-xxxxxx storage/logs/app.log` returns the exact trace

**Content Security Policy**
- Strict CSP via `.htaccess`
- CDN whitelisting for jsdelivr (Bootstrap)
- `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'`,
  `object-src 'none'`
- `'unsafe-inline'` allowed for scripts and styles for now
  (Tier 2 cleanup to remove)

**File upload foundation**
- `core_files` table — file metadata
- `core_entity_files` table — file-to-entity links
- `FileService` — orchestrates upload → validate → store → link
- `FileValidator` — MIME sniffing, extension allowlist, size limits,
  content sanity check
- `FileController` — serves files through PHP with permission checks
- `storage/uploads/.htaccess` — blocks direct access
- `Response::file()` — streams via `fpassthru()` (no memory blowup)
- (Custom field integration in progress)

**Platform foundation tables**
- Module registry: `core_modules`, `core_module_licenses`,
  `core_module_state`
- Integrations: `core_integrations`, `core_integration_logs`
- Scheduling: `core_scheduled_tasks`, `core_scheduled_task_runs`
- Health: `core_health_checks`
- Updates: `core_updates_available`, `core_updates_history`,
  `core_update_manifest`

**Strategic foundation tables**
- `core_currencies` — multi-currency support
- `core_notes` — polymorphic notes (any entity)
- `core_feature_flags` — feature toggles
- `core_webhooks`, `core_webhook_deliveries` — outbound webhooks
- `core_email_templates` — configurable templates
- `core_data_requests` — GDPR/DSAR tracking
- `core_consent_log` — consent management
- `core_login_history` — login attempts, devices, IPs
- `core_user_permissions` — direct user → permission grants
- `core_workflow_definitions`, `core_workflow_states`,
  `core_workflow_transitions` — workflow engine (schema only)

**Naming convention**
- `docs/NAMING-CONVENTION.md` — the authoritative spec
- Three-tier namespace: `CorporateAid\Core\*`,
  `CorporateAid\Platform\*`, `CorporateAid\Recruit\*`,
  `CorporateAid\Helpers\*`
- Table prefixes: `core_*` (shared), `recruit_*` (module)
- FK/CHECK/index naming convention with short-form reference
- Permission slugs: `core.*` / `recruit.*`
- Event names: `{module}.{entity}.{verb}`
- Composer package: `corporateaid/recruit`
- Full execution record: `docs/RENAME-RECORD.md`

**Documentation**
- `docs/ARCHITECTURE.md` — how the system is built (new)
- `docs/SPECIFICATION.md` — 17 ADRs (new)
- `docs/RENAME-RECORD.md` — historical execution (new)
- `docs/CHANGELOG.md` — this file (new)
- `docs/NAMING-CONVENTION.md` — rewrite with corrected content
- `docs/REMEMBER.md` → v6 — session state rewrite
- `README.md` — project entry point (new)

**Seeders**
- `0001_seed_roles_and_permissions.sql` — 7 roles, 45 permissions,
  role assignments
- `0002_seed_currencies.sql` — 11 currencies (KES, USD, GBP, EUR,
  ZAR, UGX, TZS, NGN, GHS, AED, INR)
- `0003_seed_modules.sql` — `recruit` module registration
- `0004_seed_module_state.sql` — enables `recruit` locally
- `0005_seed_admin_user.sql` — admin + researcher test accounts

### Changed

**Database**
- Renamed `recruitaid` database → `corporateaid_recruit`
- All 53 tables prefixed per convention
- All FKs, CHECK constraints, indexes audited
- 22 new tables added (platform + strategic foundation)
- 3 stale counter rows removed

**Migrations**
- All 25 migration files rewritten in place to use new table names
- Counter keys updated to `rec_*` short form
- Migration 0008 rewritten to create `core_sequence_counters`
  (previously assumed it existed)
- Migration 0026 added to clean up obsolete counter rows

**Code**
- 242 SQL strings updated across 33 files
- ~800 `use` statements rewritten across ~130 files
- 104 files moved to new directory structure
- 42 misplaced class references corrected
- 70 route middleware permission references updated

**Views**
- Form action URLs and href links updated for consistency

### Fixed

- **Login error flashes not displayed** — `layouts/auth.php` now reads
  `$error` and `$success` from view data, falls back to flash
- **CSRF middleware returned 419, PHP downgraded to 500** — changed to
  403 (standard code PHP recognizes everywhere)
- **Re-auth redirect to POST endpoint** — gate now redirects to the GET
  page for settings, not the POST endpoint
- **Pending save never consumed** — gate stores via `$request->all()`,
  `showSecurity()` consumes and applies it
- **`PipelineStageRepository::findById()` missing soft-delete filter** —
  fixed and verified with test script
- **`App::VERSION` constant was `1.0.0`** — updated to `0.6.0-alpha`
- **Migration 0008 depended on a table no migration created** — now
  creates its own table

### Security

- Content-Security-Policy added to every response
- CSRF failures now use 403 (standard) instead of 419 (silently
  downgraded)
- File upload validation: MIME sniffing, extension allowlist, content
  sanity check
- Files always served through PHP with permission checks
- `storage/uploads/.htaccess` blocks direct access as defense-in-depth
- Error reference IDs help support correlate user reports with logs

---

## [0.5.0-alpha] — 2026-09-24

The "auth and settings" release. Password reset, rate limiting,
re-auth gate, settings hub, and the notification system.

### Added

**Password reset flow**
- `/password/forgot` → email with token → `/password/reset`
- Token generation, validation, expiry
- Email delivery via Mailpit (dev) / SMTP (prod)

**Email infrastructure**
- `EmailService` wraps PHPMailer
- Templates for reset, confirmation, welcome
- Mailpit integration for local testing

**Rate limiting**
- `RateLimiter` service with configurable buckets
- `RateLimitMiddleware` for route-level enforcement
- Applied to login, password reset, confirm-password

**Re-auth gate**
- Sensitive actions require password confirmation within a window
- Configurable window via `security.session.reauth_window_minutes`
- Pending-save mechanism so user edits survive the re-auth round trip

**Settings hub**
- `/admin/settings` — tile-based hub
- `/admin/settings/security` — session, password, login settings
- Server-side validation with per-field error messages
- Human-friendly labels and value formatting

**Notification system**
- `core_notifications` table
- Bell + badge + dropdown + AJAX polling
- `/notifications` page for full history
- Notifications marked as read individually or all at once

**Security hardening**
- Limited DB user for web app (SELECT/INSERT/UPDATE/DELETE only)
- Elevated DB user for CLI only
- CHECK constraints on key tables
- 419 CSRF status corrected to 403

### Changed

- Session idle timeout now configurable (60–86400 seconds)
- Login max attempts configurable (1–100)
- Password minimum length configurable (8–64)
- Re-auth window configurable (1–120 minutes)

### Fixed

- Settings persistence bug (browser `min="300"` attribute was blocking
  submissions below the server's allowed range)
- Silent clamping of out-of-range values replaced with explicit error
  messages

---

## [0.4.0-alpha] — 2026-09-23

The "engagement module" release. Engagements, contacts, and full
recruitment contract management.

### Added

**Engagements module**
- Service types: 3-month, 6-month, 12-month, Focused Search,
  Applicant Assessment
- Package templates (5 seeded) with auto-fill
- Capacity tracking with visual meter
- Recruiter assignment (lead, recruiter, researcher) with user
  dropdown
- Auto-computed end dates from package duration
- Full CRUD + archive + delete
- Sequence counter (`ENG-000001`)

**Contacts module**
- Per-organisation contacts
- Role checkboxes (primary, hiring manager, finance, HR, decision
  maker)
- Custom fields support
- Sequence counter (`CNT-000001`)

### Changed

- Recruiter assignment dropdown shows names + role labels
- Filter out already-assigned users from the dropdown

---

## [0.3.0-alpha] — 2026-09-23

The "custom fields" release. Self-validating fields, deletion workflow,
and Organisations module.

### Added

**Custom fields engine**
- 12 built-in input types: text, long text, number, email, URL,
  phone, date, dropdown, multi-select, checkbox, file, currency
- Field builder admin UI with live preview
- Auto-render on entity forms
- Auto-validate submitted values
- Archive vs. delete (archived fields keep their values)

**Deletion workflow**
- `DeletionService` for all modules
- `DeletableEntity` interface
- Confirmation phrase system (`DELETE`, or phrase matching)
- Modal with phrase matching, optional reason, dependent list
- Soft-delete with restore

**Organisations module**
- List with search, filters, pagination
- Create/edit with custom fields
- Detail page with tabs
- Archive, restore, delete with confirmation
- Sequence counter (`ORG-000001`, never reused)

**Security hardening**
- Limited MySQL user for web app
- Elevated MySQL user for CLI
- `core_sequence_counters` table for monotonic IDs
- CHECK constraints on key tables

---

## [0.2.0-alpha] — 2026-09-23

The "auth + design" release. Authentication, RBAC, and the design
system.

### Added

**Authentication & authorization**
- Login, logout, password change
- Session security (HttpOnly, SameSite, secure cookies, rotation,
  idle timeout)
- CSRF protection on all POST/PUT/DELETE
- Argon2id password hashing
- 7 roles: super_admin, admin, recruitment_manager, recruiter,
  researcher, client_admin, client_user
- 40+ granular permissions
- `PermissionMiddleware` for route-level enforcement

**Design system**
- Dark / Light / System themes with per-user preference
- Sidebar shell with scroll persistence
- Component library: page bands, tiles, cards, tables, badges,
  alerts, toggles
- Mobile-responsive with off-canvas drawer
- Notification bell shell (empty)

---

## [0.1.0-alpha] — 2026-09-23

The "framework" release. Custom PHP MVC, migrations, routing.

### Added

- Custom PHP MVC framework
- Config system with per-deployment overrides (`config/local.php`)
- Error handling with friendly user pages (403, 404, 500)
- Front controller + `.htaccess` routing
- Migrations system (`php bin/migrate.php`)
- Seeders system (`php bin/seed.php`)
- PDO database wrapper with prepared statements
- Basic container for dependency injection

---

## Version Numbering

- **Major version bump** — breaking architectural/API changes
- **Minor version bump** — backward-compatible features
- **Patch** — bug and security fixes

Given the pre-launch status, versions are currently in the `0.x.y-alpha`
range. The first stable release will be `1.0.0`.

---

## References

- `docs/ABOUT.md` — current feature set
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the design decisions
- `docs/CHANGELOG-middleware.md` — deep dive on the onion migration
- `docs/RENAME-RECORD.md` — deep dive on the naming convention cycle
- `docs/REMEMBER.md` — current session state