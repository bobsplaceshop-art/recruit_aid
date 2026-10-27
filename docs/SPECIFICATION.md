# Recruit Aid — Technical Specification

**Product:** Recruit Aid
**Suite:** Corporate Aid Solutions
**Version:** 0.6.0-alpha
**Status:** Active development
**Last revised:** 2026-09-25

---

**See also:**
- `docs/NAMING-CONVENTION.md` — naming rules for everything
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/RENAME-RECORD.md` — historical execution of the rename cycle
- `docs/REMEMBER.md` — current session state

---

## Purpose of This Document

This document captures the **why** behind Recruit Aid's design. It is a
living record. Every architectural decision that shapes the codebase is
recorded here as an ADR (Architecture Decision Record) with the
technical reasoning **and** the human context that produced it.

Future sessions and future developers should be able to read this and
understand not just what was built, but why it was built that way.

---

## 1. Product Overview

### 1.1 The Corporate Aid Solutions Suite

Corporate Aid Solutions is a family of business software products built
on a shared architectural foundation. Each product can be purchased and
deployed independently. Each can be integrated with the others through
**Corporate Connect** — a customer-local integration layer (not a
vendor-operated service).

| Product | Purpose | Status |
|---|---|---|
| **Recruit Aid** | Recruitment Operations & Applicant Tracking | Active development |
| HR Aid | Human Resource Management | Planned |
| CRM Aid | Customer Relationship Management | Planned |
| Payroll Aid | Payroll Processing | Planned |
| Leave Aid | Leave & Absence Management | Planned |
| Performance Aid | Performance Management | Planned |

Beyond these horizontal modules, the suite also includes **industry
vertical solutions** — compositions of modules plus industry-specific
extensions (Farm Aid, Clinic Aid, Supermarket Aid, etc.). See
`docs/NAMING-CONVENTION.md` §1 for the full taxonomy.

### 1.2 Recruit Aid

Recruit Aid is a complete Applicant Tracking System (ATS) for
recruitment agencies. It handles:

- Client organisations (the companies hiring)
- Client contacts (people at each client)
- Engagements (recruitment contracts with scope, duration, capacity)
- Vacancies (jobs being filled)
- Pipeline stages per vacancy
- Candidates (people being placed) — *planned*
- Applications (candidate × vacancy links) — *planned*
- Assessments, interviews, offers, reference checks — *planned*
- Client portal (employer-facing) — *planned*
- Candidate portal (applicant self-service) — *planned*

### 1.3 Business Model

The platform is sold as a **white-labelled product** to individual
recruitment agencies. Each agency:

1. Purchases a license for Recruit Aid (and optionally other modules)
2. Installs one instance on their own cPanel hosting
3. Rebrands it with their logo and colours
4. Operates it with their own team

**Corporate Aid Solutions has zero runtime involvement** after the
license key is issued. The software is the customer's.

### 1.4 Deployment Target

Every product in the suite deploys to **ordinary shared cPanel hosting**.
This constraint shapes every technical decision.

**Required environment:**
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.6+
- Apache with mod_rewrite
- Standard PHP extensions: pdo_mysql, mbstring, openssl, fileinfo,
  json, ctype, tokenizer, dom, gd, curl, zip
- Composer for dependency management
- Cron for background tasks
- SMTP for outbound email

**Explicitly excluded:**
- Node.js, npm, Webpack, Vite
- React, Vue, Angular, Alpine
- Docker, Kubernetes
- Redis, Memcached
- PostgreSQL, MongoDB
- Any service requiring a VPS or SSH-only access

These exclusions reflect the reality that recruitment agencies run on
shared hosting, not managed container platforms. Every technical choice
must work in a $5/month cPanel account with a browser-based file manager.

---

## 2. Architecture Decisions

Each ADR records:
- **Context** — what was happening when the decision was made
- **Decision** — what we chose
- **Reasoning** — why, both technical and human
- **Consequences** — what follows from this choice

---

### ADR-001 — Custom MVC Framework vs. Off-the-Shelf Framework

**Context (early 2026):** The project began with a blank slate. The
options were Laravel, Symfony, CodeIgniter, or a custom minimal MVC.
Each has trade-offs for cPanel deployment.

**Decision:** Build a custom minimal MVC framework.

**Reasoning:**
- **Deployment simplicity:** Frameworks like Laravel expect `artisan`,
  queue workers, and often Redis. Deploying Laravel to cPanel without
  SSH is constrained. A custom framework avoids every one of those
  dependencies.
- **No vendor lock-in:** The customer owns the code outright. When
  Laravel 12 ships, existing installs don't have to be rewritten.
- **Small surface area:** We use maybe 10% of what Laravel provides.
  The custom framework is ~2,000 lines of well-understood code.
- **Control over the middleware model:** We needed a specific middleware
  behaviour (onion model — see ADR-005) that we could shape precisely.
- **The customer's team can read it:** A recruitment agency's in-house
  developer can open any file and understand what it does. Laravel's
  magic (facades, service providers, eloquent) is a barrier.

**Consequences:**
- We must build features frameworks give for free (routing,
  migrations, container, validation). This is the cost.
- We control every line — no black boxes.
- Framework patterns we don't need (facades, ORM, jobs) are absent,
  keeping the mental model small.

**When to reconsider:** If the codebase grows past ~80,000 lines or past
3 products, the maintenance burden may outweigh the benefits. At that
point, extracting to a proper framework or establishing an internal
"Corporate Aid Framework" would be justified.

---

### ADR-002 — Repository Pattern for Data Access

**Context:** Controllers need to query the database. The direct
approach is to write SQL inline in controllers or services. That
approach works for small apps but couples business logic to schema.

**Decision:** All SQL lives in repository classes. Controllers and
services call repository methods that return models, never raw rows.

**Reasoning:**
- **Single source of truth for queries:** If a query needs to change
  (an index hint, a JOIN rewrite, a new filter), it changes in one
  place. Not scattered across the codebase.
- **Soft-delete consistency:** Every read method can be audited to
  confirm it filters `deleted_at IS NULL`. This audit is impossible if
  raw SQL lives in 50 files.
- **Testability:** Repositories can be mocked. Services that consume
  them can be tested without a database.
- **Schema evolution:** If a column is renamed, only the repository
  changes. The rest of the app uses model properties that keep the
  same names.

**Consequences:**
- More files. Every entity has a model, a repository, and often a
  service. That's three files for one conceptual thing.
- Repositories are thin wrappers around PDO. Some queries are simple
  enough that the repository feels like overhead.
- The pattern pays off the first time a query needs to change in a
  non-trivial way. It pays off again every time a soft-delete audit
  runs.

**Notable exception:** Migrations and seeders use raw SQL by design.
They must be explicit about schema operations and don't benefit from
the repository abstraction.

---

### ADR-003 — Service Layer for Business Logic

**Context:** Controllers handle HTTP. But HTTP is not the only entry
point — cron jobs, CLI commands, and future API endpoints all need the
same business operations. Where should the business logic live?

**Decision:** Business logic lives in service classes. Controllers are
thin adapters that translate HTTP requests into service calls.

**Reasoning:**
- **Multiple entry points:** A password reset can be triggered from
  the web form, from an admin acting on behalf of a user, or from a
  cron reminder job. All three call the same service method.
- **Testability:** A service can be called from a test without
  building an HTTP request. A controller cannot.
- **Composability:** A service can call other services. A user
  creation service can trigger an email service, a notification
  service, and an audit service — all without knowing about HTTP.
- **Transactional boundaries:** A service is a natural place to wrap
  operations in database transactions.

**Consequences:**
- Controllers are thin. This is intentional — a 20-line controller
  that only reads inputs, calls a service, and returns a response is
  correct.
- Some trivial operations (fetch a record, return it) live in the
  controller directly. Not every action needs a service. The rule is:
  if the operation has *any* non-HTTP logic, or might be called from
  another context, it goes in a service.
- A service method that grows past ~50 lines is a signal to extract
  sub-methods or split the service.

---

### ADR-004 — Custom Fields Engine

**Context:** Recruitment agencies have wildly different needs. One
agency cares about "right to work status" on candidates. Another cares
about "expected salary band". Building all these fields into the schema
would bloat the codebase.

**Decision:** Every entity supports custom fields. Custom fields are
defined by the admin via a field builder UI. Each field declares an
input type (text, number, date, dropdown, file, etc.), and the input
type knows how to render itself, validate itself, and cast its value.

**Reasoning:**
- **Configuration over code:** If the customer wants a new field,
  they add it. No developer, no deployment, no release.
- **Self-validating types:** The 12 built-in input types each know
  their own validation rules. The framework doesn't need to know
  "this is a phone number" — the PhoneType class does.
- **Uniform integration:** Every entity that supports custom fields
  uses the same UI, same validation, same storage.
- **Archive, not delete:** Custom fields can be archived. Values
  remain in the database.

**Consequences:**
- Values are stored in `core_custom_field_values` with a
  `(custom_field_id, entity_type, entity_id)` unique key. Not the
  most query-efficient storage model.
- Reporting on custom fields is more complex than on native columns.
  Acceptable — reporting is read-heavy and can afford joins.
- Field type errors show at the top of the form, not inline (Tier 2
  improvement).

**Extension point:** New input types can be added by creating a class
in `app/Platform/CustomFields/InputTypes/` that implements `InputType`.
Register it in `InputTypeRegistry`. It appears in the field builder UI
automatically.

---

### ADR-005 — Onion Middleware Model

**Context (2026-09-24):** The framework originally used gate-style
middleware — each middleware received the request and returned either a
`Response` (short-circuit) or `null` (pass through). This worked until
we tried to build audit logging, which needs to see the response
*after* the controller runs.

**Decision:** Convert to onion middleware. Each middleware receives the
request and a `$next` callable. It may short-circuit, pass through, or
wrap the response.

**Reasoning:**
- **Post-controller access:** Audit logging needs the response status
  code to record what happened. Only onion middleware can see the
  response.
- **Industry standard:** Laravel, Symfony, Slim, PSR-15, Express —
  every mature framework uses onion middleware.
- **Unlocks a class of features:** Response compression, timing
  headers, CORS header injection, error transformation, cache
  handling — all require post-controller access.
- **The human reason:** We were told explicitly: *"I would prefer it
  to prevent future break in the system that then translates to us
  having to do the entire redesign with customers already onboard."*
  Onion middleware is the model that avoids that future redesign.
  Doing it now, with 5 middlewares, is trivial. Doing it later, with
  20 middlewares across multiple products, is a multi-week nightmare.
- **Backwards compatibility:** Existing gate-style middlewares
  continued to work via a bridge that detected their signature via
  reflection. The bridge was removed once all 5 middlewares converted.

**Consequences:**
- All middlewares use the signature
  `handle(Request $request, callable $next): Response`.
- Middleware order matters: outer-most middleware runs first on the
  request, last on the response.
- Adding a post-controller action is now possible without
  restructuring the pipeline.

**Full migration details:** `docs/CHANGELOG-middleware.md`.

---

### ADR-006 — Two-Layer Audit Model

**Context:** The system needed to record what happened. Two different
audiences care about this information: end users (who want a business
timeline) and security teams (who want an immutable technical record).

**Decision:** Build two separate logs. `core_activities` records
business-visible events with human-readable descriptions.
`core_audit_logs` records every state-changing HTTP request with
technical metadata.

**Reasoning:**
- **Different purposes, different shapes:** A business event is
  "Jane Doe updated the salary for Candidate X". An HTTP audit entry
  is "user_id=5 POST /api/candidates/42 at 14:03:21, payload hash
  abc123, response 302". One is meaningful to a recruiter; one is
  meaningful to a compliance auditor.
- **The audit layer cannot be forgotten:** `core_activities` are
  logged by services calling `ActivityLogger::log()` explicitly. That
  requires discipline. `core_audit_logs` are written by middleware
  automatically — the guarantee is structural.
- **Compliance:** GDPR and Kenya DPA 2019 both require demonstrable
  records of processing. A per-request audit trail is the clearest
  form of that.
- **Forensics:** When something breaks, the audit log lets us
  reconstruct exactly what happened.

**Consequences:**
- Every POST/PUT/PATCH/DELETE writes one row to `core_audit_logs`.
  Will need a retention policy (Tier 5).
- `core_activities` may miss events if a developer forgets to log
  them. Acceptable — the business timeline is a convenience, not a
  compliance artifact.

**Extension point:** `AuditContext::set()` lets controllers enrich the
audit row with business metadata (entity_type, entity_id).

---

### ADR-007 — Soft-Delete Convention

**Context:** Records are often "deleted" but need to be recoverable.
Hard deletes lose data. A soft-delete convention is needed.

**Decision:** Every soft-deletable table has a `deleted_at DATETIME
NULL` column. Every read method in every repository filters
`deleted_at IS NULL`.

**Reasoning:**
- **Recoverability:** A mis-click that deletes a candidate doesn't
  destroy their record.
- **Referential integrity:** Foreign keys to a soft-deleted record
  still resolve.
- **Audit trail completeness:** The record history remains intact.
- **Consistency across the codebase:** The same pattern applies
  everywhere.

**Consequences:**
- Every query on a soft-deletable table must include the filter. A
  2026-09-24 audit found exactly one missing filter
  (`PipelineStageRepository::findById()`) — it was fixed.
- Deleted records still occupy storage. A purge cron (Tier 5) will
  physically remove records deleted more than N days ago.

**Rule for future repositories:** The default `findById()` **always**
filters `deleted_at IS NULL`. Callers needing deleted records use a
separately-named method (`findByIdIncludingDeleted()`).

---

### ADR-008 — Human-Friendly Labels for Internal Identifiers

**Context (2026-09-24):** During the notification system work, we
noticed that internal slugs (`candidate.created`,
`security.session.idle_timeout`, `role: super_admin`) were leaking into
the admin UI and notifications. These are database identifiers —
meaningless to end users.

**Decision:** Every user-facing surface translates internal identifiers
into human labels. The database keeps the internal form; the display
layer resolves it.

**Reasoning:**
- **UX:** A user shouldn't need to know that `security.session.idle_timeout`
  means "Session idle timeout". They should see the label directly.
- **Localization-ready:** When we add multi-language support, the
  translation happens at the label layer, not the database layer.
- **Non-technical users:** The customer's HR team shouldn't need to
  interpret a slug.
- **Trust:** Every time a raw slug appears, the app feels less finished.

**Implementation:**
- `SystemSettingService::getHumanLabel($key)` — resolves setting keys
- `SystemSettingService::formatValue($key, $value)` — formats values
  (e.g. `120` seconds → "2 minutes")
- `AdminNotificationRuleController::resolveRecipientLabel()` —
  resolves event types and recipient types
- `AdminNotificationRuleController::humanizeSlug()` — fallback for
  unknown identifiers

**Consequences:**
- Every new internal identifier introduced needs a corresponding label
  resolver.
- Every new module needs to think about its display layer.

---

### ADR-009 — Error Reference IDs

**Context (2026-09-24):** When something breaks in production, the user
sees "Something went wrong". Support has no way to correlate the
user's experience with the log entry.

**Decision:** Every unhandled 500 exception generates a short reference
ID (format: `ERR-` + 6 hex characters, e.g. `ERR-4a2f9c`). The ID is:

1. Prefixed to the log line
2. Shown on the 500 page: "Reference: ERR-4a2f9c"
3. Included in the JSON context: `"ref":"ERR-4a2f9c"`

The user reads the ID, quotes it to support, and support greps the log
for it.

**Reasoning:**
- **Support workflow:** One command — `grep ERR-4a2f9c storage/logs/app.log`
  — returns the exact trace.
- **User-friendly:** The ID is short enough to read aloud over the
  phone.
- **Random:** Not sequential, so it can't be used to probe or
  enumerate past errors.
- **Only for 500s:** 4xx errors are user errors, not bugs. They don't
  get reference IDs (though they are logged to `core_audit_logs`).

**Consequences:**
- Every 500 has a reference ID. Users can quote it or ignore it.
- No PII in the ID — just random hex.

---

### ADR-010 — Content Security Policy + Security Headers

**Context (2026-09-24):** The app was sending `X-Frame-Options` and
`X-Content-Type-Options` but not `Content-Security-Policy`. CSP is the
modern defense against XSS, content injection, clickjacking, and form
hijacking.

**Decision:** Send a strict CSP via `.htaccess` covering every response.
Allow `'unsafe-inline'` for scripts and styles (required because views
embed small inline blocks), but restrict everything else.

**CSP directives:**

```
default-src 'self'
script-src  'self' 'unsafe-inline' https://cdn.jsdelivr.net
style-src   'self' 'unsafe-inline' https://cdn.jsdelivr.net
font-src    'self' https://cdn.jsdelivr.net data:
img-src     'self' data:
connect-src 'self' https://cdn.jsdelivr.net
frame-ancestors 'none'
base-uri    'self'
form-action 'self'
object-src  'none'
```

**Reasoning:**
- **XSS defense:** Injected scripts can't load from arbitrary origins.
- **Clickjacking defense:** `frame-ancestors 'none'` (redundant with
  `X-Frame-Options` but more modern).
- **Form hijacking defense:** `form-action 'self'` prevents forms from
  being redirected to attacker servers.
- **Base tag hijacking defense:** `base-uri 'self'`.
- **Plugin attack defense:** `object-src 'none'`.
- **`'unsafe-inline'` compromise:** Required because views have inline
  styles and small inline scripts. A Tier 2 cleanup will move those to
  external files served with nonces, at which point `'unsafe-inline'`
  comes out.

**Consequences:**
- Every new CDN source must be whitelisted in the CSP.
- Adding new inline scripts requires either keeping `'unsafe-inline'`
  or introducing nonces.
- The `connect-src` allows `cdn.jsdelivr.net` for Bootstrap source
  maps. Harmless — the CDN is already trusted for scripts and styles.

---

### ADR-011 — File Storage Model

**Context (2026-09-24):** The custom fields engine had a `file` input
type that rendered but didn't persist. We needed a proper file system
to support CVs, certificates, IDs, contracts, etc.

**Decision:** Files are stored on disk outside the web root. Metadata
lives in `core_files`. Files can be attached to any entity via
`core_entity_files`. Files are always served through PHP, never
directly by Apache.

**Reasoning:**
- **Permission control:** Serving through PHP lets us check whether the
  current user has access to the linked entity.
- **Uniform API:** The same service (`FileService`) handles all uploads
  regardless of which entity they're attached to.
- **Security:**
  - Files stored outside web root (`storage/uploads/`)
  - `.htaccess` in `storage/uploads/` blocks all direct access
  - MIME sniffing on upload (never trust the extension)
  - Extension allowlist (not denylist)
  - Content sanity check for obvious script payloads
  - SHA-256 hash for integrity + dedup
  - UUID as public reference (never expose numeric ID)
  - Serve with `Content-Disposition: inline`, `nosniff`, and
    `CSP: default-src 'none'; sandbox`
- **Streaming:** `Response::file()` streams via `fpassthru()` so large
  files never sit in PHP memory.

**Consequences:**
- Every file download goes through a PHP process. For high-volume
  installs this could be a bottleneck, but for recruitment workloads
  it's fine.
- Files must be manually deleted (soft-delete marks the row; a purge
  cron removes the physical file later).

---

### ADR-012 — Notification Rules as Data

**Context (2026-09-24):** During the notification system work, we
realized that "who gets notified about what" was going to be hardcoded
in PHP. Every customer would need a code change to alter notification
recipients.

**Decision:** Notification rules are rows in `core_notification_rules`.
Each rule declares an event type, a recipient type (role, permission,
specific user, context-based like "assigned recruiter"), and channel
flags (in-app, email).

**Reasoning:**
- **Customer autonomy:** The admin can adjust rules without a
  developer.
- **Multi-module ready:** Every future module declares its events in
  the same table. No new code needed for notification routing.
- **Human-friendly UI:** The admin UI at `/admin/settings/notifications`
  shows rules grouped by event with human labels.
- **Testable:** Dispatch logic reads from data, not branching code.

**Consequences:**
- Every notification firing goes through
  `NotificationRuleService::resolveRecipients()`.
- The engine currently does actor exclusion by default (the actor
  doesn't get notified about their own action). A per-rule override is
  possible but not yet built.
- Email delivery is deferred to a daily digest (Tier 5).

---

### ADR-013 — Product Boundaries and the Naming Convention

**Context (2026-09-24):** The product was about to grow. HR Aid, CRM
Aid, and other modules were on the roadmap. Without clear boundaries,
the codebase would become a tangle of "which module owns this table?".

**Decision:** Adopt a comprehensive naming convention covering every
layer: database tables, foreign keys, CHECK constraints, indexes,
PHP namespaces, directory structure, permission slugs, event names,
API paths, and config keys. See `docs/NAMING-CONVENTION.md` for the
full specification.

**The naming convention is enforced through:**
- Table prefixes (`core_*` for shared, `{module}_*` for module-owned)
- Namespace roots (`CorporateAid\Core\*`, `CorporateAid\Platform\*`,
  `CorporateAid\Recruit\*`, `CorporateAid\Helpers\*`)
- Directory structure (mirrors the namespace)
- Permission slug prefixes (`core.*`, `recruit.*`)
- Event name prefixes (`recruit.candidate.created`)

**Reasoning:**
- **Clarity:** Any identifier tells you which module owns it.
- **Boundaries:** A module never reads another module's tables. All
  cross-module communication flows through Corporate Connect.
- **Extensibility:** Adding HR Aid is a mechanical process — new
  namespace root, new table prefix, new permission prefix.
- **The human reason:** We were told: *"when making the databases,
  this should be a clear design spec that normalizes naming conventions
  across all iterations and future documents."*

**Consequences:**
- Every new module must follow the convention.
- The rename cycle (2026-09-25) moved the entire codebase to the new
  convention. Details in `docs/RENAME-RECORD.md`.
- The full short-form vocabulary is maintained in
  `docs/NAMING-CONVENTION.md` §21.

---

### ADR-014 — Licensing Model

**Context (2026-09-24):** The product is sold to customers who install
it on their own hosting. Corporate Aid Solutions must be able to grant
a license for module X, and later for module Y, without any runtime
involvement in the customer's instance.

**Decision:** Licenses are signed keys generated by Corporate Aid and
validated offline by the customer's instance. No phone-home, no
telemetry, no vendor dependency.

**Structure:**
- `core_modules` — registry of known modules (shipped with every
  install, populated by a seeder)
- `core_module_licenses` — license keys for this install (one row per
  licensed module)
- `core_module_state` — which modules are enabled locally

**Key format (planned):**
- Signed JSON payload (customer ID, module, issue date, expiry,
  features enabled)
- HMAC signature using a key embedded in the software
- Base64-encoded for display

**Validation flow:**
1. Admin enters the key in the admin UI
2. System verifies the signature using the embedded public key
3. If valid, records the license in `core_module_licenses`
4. Enables the module in `core_module_state`
5. Module becomes available in the navigation

**Reasoning:**
- **Customer ownership:** Once the key is entered, the module is the
  customer's. No recurring validation calls, no service dependency.
- **No telemetry:** The vendor has no visibility into the customer's
  usage. The customer's data is theirs.
- **Support via separate channel:** If a customer needs help, they
  contact Corporate Aid directly. The software doesn't auto-report.

**Consequences:**
- License revocation requires the customer's cooperation (they must
  delete the key). This is a trade-off — the alternative (phone-home
  revocation) violates the customer-ownership model.
- Future: license expiry warnings in the admin dashboard.

---

### ADR-015 — Corporate Connect (Customer-Local Integration)

**Context (2026-09-24):** With multiple modules on the roadmap, we
needed a way for them to communicate. The initial idea was a
vendor-operated integration hub. This was rejected — the customer owns
the software.

**Decision:** Corporate Connect is a **customer-local** integration
layer. It lives inside the customer's instance. It connects the
customer's own licensed modules. Corporate Aid has zero runtime
involvement.

**Architecture:**
- `core_integration_events` — the event outbox
- `core_integration_mappings` — cross-module ID relationships
- A cron worker (Tier 5) drains the outbox every N minutes
- External HTTP webhooks for third-party integrations

**Reasoning:**
- **Customer ownership:** The customer's data stays in the customer's
  infrastructure. Corporate Aid never sees it.
- **No vendor dependency:** The customer's integrations work even if
  Corporate Aid's services go offline.
- **Local control:** The customer decides what syncs to what.
- **Simpler compliance:** No cross-border data flow to the vendor.
  The customer's GDPR/DPA compliance is their own.

**Consequences:**
- No central management console for integrations. The admin UI on
  each install shows status.
- Support is manual: if a customer's sync breaks, they diagnose
  locally.
- The event outbox pattern is required — synchronous integration
  would slow down business operations.

**Status:** Tables exist. Worker not yet built (Tier 5).

---

### ADR-016 — Server Manager and Update System

**Context (2026-09-24):** Customers on shared cPanel hosting need
self-service diagnostic tools. When something breaks, they shouldn't
have to call support to check basic health.

**Decision:** Build a **Server Manager** in the admin UI showing:
integrations, API status, health snapshots. Build an **Update System**
that fetches a manifest from Corporate Aid, shows available updates,
and lets the admin approve or skip them.

**Server Manager:**
- Lists configured integrations with last-check status
- Health snapshot: disk, DB size, PHP version, extensions, pending
  migrations, error count 24h
- Cron health check every hour (configurable)

**Update System:**
- Cron checks Corporate Aid's update manifest URL
- Shows available updates in the admin UI
- Admin clicks "Apply" or "Skip" for each
- Updates are signed; the customer's instance verifies the signature
- Rollback support: every update has a pre-apply snapshot

**Reasoning:**
- **Independence:** The customer manages their own environment.
- **No vendor friction:** Updates aren't forced. Customers choose
  when to apply them.
- **Transparency:** The customer sees what updates are available and
  what they change.
- **Security:** Signed updates prevent tampering. The customer trusts
  the vendor's signature, not the delivery mechanism.

**Consequences:**
- Corporate Aid must host an update manifest URL (public, signed).
- Every module release must be packaged as a downloadable update.
- Skipped updates must be tracked (so the admin knows what's pending).

**Status:** Tables exist. Feature not built (Tier 5).

---

### ADR-017 — Platform Foundation Tables

**Context (2026-09-24):** The tables needed for licensing, Server
Manager, Update System, and future integrations were not yet created.
We had two options: create them when the features ship, or create them
now as empty placeholders.

**Decision:** Create the tables now, before any customer exists. They
sit empty until the features are built.

**Reasoning:**
- **No live migration pain:** When a customer already has data,
  adding tables means a migration against production. Adding them now
  means every install has them from day one.
- **Schema stability:** The shape of the schema is fixed. Future
  work is code-only.
- **Cheap now:** Adding 20 table definitions to a migration takes 30
  minutes. Adding them later means a coordinated release with
  downtime planning.

**Tables added (migration 0024):**
- Module registry: `core_modules`, `core_module_licenses`,
  `core_module_state`
- Integrations: `core_integrations`, `core_integration_logs`
- Scheduling: `core_scheduled_tasks`, `core_scheduled_task_runs`
- Health: `core_health_checks`
- Updates: `core_updates_available`, `core_updates_history`,
  `core_update_manifest`

**Tables added (migration 0025):**
- Currencies: `core_currencies`
- Notes: `core_notes` (polymorphic)
- Feature flags: `core_feature_flags`
- Webhooks: `core_webhooks`, `core_webhook_deliveries`
- Email: `core_email_templates`
- GDPR: `core_data_requests`, `core_consent_log`
- Audit: `core_login_history`
- Permissions: `core_user_permissions`
- Workflow engine (tables only): `core_workflow_definitions`,
  `core_workflow_states`, `core_workflow_transitions`

**Consequences:**
- 22 new tables exist, all empty.
- Every future feature is code-only, not schema-then-code.

---

## 3. Doctrine Rules (Cross-Document)

The following rules were learned through specific failures during this
project's development. They appear in `docs/REMEMBER.md` in full, but
are referenced here because they shaped ADRs above.

1. **Read dependencies before rewriting.** For any file being changed,
   read the file, its callers, and one sibling file for pattern
   reference.
2. **Never use `CREATE TABLE IF NOT EXISTS`** in migrations for tables
   we expect to create.
3. **No hand-edits for diagnostics.** All diagnostic code goes in via
   full-file rewrite or a WSL Python injector that lints afterward.
4. **Full-file rewrites only.** No patch-based edits to code.
5. **`composer dump-autoload` after creating any new PHP class.**
6. **Command context matters.** Heredocs in WSL, PHP/Composer in
   Laragon.
7. **`Request::input()` requires a real key.** Never pass empty string.
8. **Re-auth redirects must target GET pages, not POST endpoints.**
9. **Pending-save data must be consumed somewhere.**
10. **Every response status must be a standard code PHP recognizes.**
11. **404s on unbuilt menu items are expected.**
12. **Always test the full loop, not just the last step.**
13. **Never mix contexts in one instruction block.**
14. **Verify class's actual directory before referencing its
    namespace.**
15. **Migrations and seeders drift separately.** Both need the same
    rename treatment in the same pass.
16. **Migrations run in order on an empty DB.** Any migration
    depending on a later migration's table is a bug.
17. **Test the fresh-install path after every non-additive migration.**
18. **Column references in code can masquerade as counter keys during
    grep audits.** Verify context.

---

## 4. References

- `docs/NAMING-CONVENTION.md` — naming rules for everything
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/RENAME-RECORD.md` — how the codebase got to this state
- `docs/REMEMBER.md` — current session state