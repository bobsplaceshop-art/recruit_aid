# Recruit Aid — About the Application

**Product:** Recruit Aid
**Suite:** Corporate Aid Solutions
**Type:** Recruitment Operations & Applicant Tracking System (ATS)
**Model:** Sold as a white-labelled product to recruitment agencies who own and rebrand their install
**Deployment target:** Shared cPanel hosting (PHP 8.2+, MySQL 8, Apache)
**Current version:** 0.9.0-alpha

---

**See also:**
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the design decisions behind it
- `docs/NAMING-CONVENTION.md` — naming rules across the suite
- `docs/REMEMBER.md` — current session state
- `docs/RBAC-ARCHITECTURE.md` — the four-axis authorization model
- `docs/CAS-PLATFORM-SURFACE.md` — the /cas/* platform surface

---

## 1. What It Is

Recruit Aid is a complete Applicant Tracking System (ATS) built for
recruitment agencies. It manages the full candidate lifecycle from
sourcing through offer acceptance, with a working CRM for client
organisations and a rich administrative surface for customisation.

The buyer — a recruitment agency — installs **one instance** on their
own hosting, rebrands it with their logo and colours, and operates it
for their team.

The platform is designed to be **configured by the customer**: they
create custom fields on any entity, define validation by picking an
input type, manage roles and permissions, and configure lookups —
without a developer.

---

## 2. Key Product Principles

1. **Configuration over code** — custom fields, lookups, and role
   permissions are all admin-configurable at runtime.
2. **Archive, not delete** — records with dependents are archived, not
   destroyed. Purge is a separate scheduled task.
3. **Self-validating fields** — pick an input type, the platform
   validates automatically.
4. **Audit everything** — every state-changing HTTP request is logged
   to `core_audit_logs`; every RBAC mutation is logged to
   `core_rbac_audit_logs`.
5. **Least privilege** — the web app runs on a limited DB user;
   schema changes only run via CLI.
6. **Security first** — CSP, CSRF, rate limiting, Argon2id, secure
   sessions, step-up re-auth on privileged context switches.
7. **Owner owns everything** — the customer owns the code, the data,
   and the infrastructure. No vendor runtime dependency.
8. **Multi-tenancy by design** — one install, many tenants, with a
   four-axis authorization model (see docs/RBAC-ARCHITECTURE.md).

---

## 3. Technical Stack

| Layer | Technology | Why |
|---|---|---|
| Runtime | PHP 8.2+ | Works on shared cPanel hosting |
| Web server | Apache + .htaccess | No server config needed |
| Database | MySQL 8 / MariaDB 10.6+ | cPanel standard |
| Framework | Custom minimal MVC | Full control, no vendor lock-in |
| Database access | PDO with prepared statements | SQL injection prevention |
| Frontend | Bootstrap 5 (CDN) + Vanilla JS | No build step, no Node.js |
| CSS | Custom design system | Themeable (dark / light / system) |
| Emails | PHPMailer | Reliable SMTP |
| PDFs | Dompdf | Pure PHP, no binaries |

**Deliberately NOT used:** Node.js, npm, Webpack, Vite, React, Vue,
Angular, Alpine, Docker, Kubernetes, Redis, Memcached, PostgreSQL,
MongoDB, or any service requiring a VPS.

---

## 4. Suite Context

Recruit Aid is product #1 in the **Corporate Aid Solutions** suite.

| Tier | Format | Examples |
|---|---|---|
| **Tier 1** — Company | — | Corporate Aid Solutions (vendor) |
| **Tier 2** — Functional modules | `{Function} Aid` | Recruit Aid, HR Aid, CRM Aid, Payroll Aid, … |
| **Tier 3** — Industry verticals | `{Industry} Aid` | Farm Aid, Clinic Aid, Supermarket Aid, … |
| **Tier 0** — Integration layer | — | Corporate Connect (customer-local) |

Each module can be purchased independently. HR Aid is registered as a
module (`app/Modules/HR/`) but is disabled in `core_module_state` until
its first features ship.

---

## 5. What's Built

Everything below is verified against code (`app/Recruit/`,
`routes/web.php`, `database/migrations/`, `tests/Integration/`) at
v0.9.0-alpha.

### Foundation ✅

- Custom PHP MVC framework (`app/Core/`)
- Config system with per-deployment overrides
- Error handling with friendly 403/404/500 pages
- Error reference IDs on 500s (`ERR-xxxxxx`)
- Front controller + `.htaccess` routing
- Migrations system (`php bin/migrate.php`)
- Seeders system (`php bin/seed.php`)
- Three-tier namespace (Core / Platform / Recruit)
- Container with explicit bindings (no autowiring)

### Authentication & Authorization ✅

- Login, logout, password change, password reset with email
- Re-authentication gate for sensitive actions
- Session security (HttpOnly, SameSite, rotation, idle timeout)
- CSRF protection on all POST/PUT/PATCH/DELETE
- Password hashing with Argon2id
- Rate limiting on auth endpoints
- **Four-axis authorization model** — home tenant, platform
  authority, tenant authority, Developer super-administrator
  (see docs/RBAC-ARCHITECTURE.md)
- **Tenant-scoped roles** — `core_roles` has `tenant_id NOT NULL`;
  composite FKs enforce role-user tenant consistency
- **Platform roles** — `cas_admin` and `cas_developer` via
  `core_platform_roles`
- **Developer super-administrator authority** — members of
  `platform_owners` get implicit tenant-domain access across all
  tenants under Rule 3b of the authorization chain
- **Support delegations** — explicit, expiring, auditable
  cross-tenant access grants with asymmetric revocation
- **Step-up re-auth** on privileged `/cas/context/switch`
- **Protected platform owner accounts** — cannot be modified by a
  non-owner through the tenant admin UI (`ProtectPlatformOwnerMiddleware`)
- **Full RBAC audit trail** — every mutation writes to
  `core_rbac_audit_logs`
- **Roles CRUD** — create, edit, delete with guards
- **Permission matrix** — role selector + checkbox grid with
  human-readable labels
- **User management** — full CRUD at `/recruit/admin/users` with
  role assignment
- **Dynamic sidebar** — nav items hidden based on user permissions
- **Branded 403 page** — shield icon, required permission, actions

### CAS Platform Surface ✅

The `/cas/*` routes expose platform-level operations, isolated from
any single tenant's business data.

- **Dashboard** at `/cas/dashboard` — aggregate tenant metrics only
- **Tenant lifecycle** at `/cas/tenants` — create, edit, suspend,
  archive, restore (10 routes)
- **Support delegations** at `/cas/delegations` — grant, revoke,
  cancel pending revocation
- **Context switching** at `/cas/context` — effective-tenant
  override, requires step-up re-auth
- **Platform calendar** at `/cas/calendar`
- Full audit trail on every action

### Client Management ✅

- **Organisations** — full CRUD, custom fields, sequence counters,
  archive/restore/delete, per-org contacts tab
- **Contacts** — per-organisation, role checkboxes (primary, hiring
  manager, finance, HR, decision maker), custom fields

### Engagements ✅

- Service types (3-month, 6-month, 12-month, Focused Search,
  Applicant Assessment)
- Package templates with auto-fill
- Capacity tracking with visual meter
- Recruiter assignment (lead, recruiter, researcher)

### Vacancies ✅

- Full schema with per-vacancy pipeline stages (14 defaults cloned
  per vacancy)
- Hiring managers + recruiters (pivot tables)
- Engagement and contact dropdowns filtered by organisation
- Add-candidate action from vacancy detail
- Archive / restore / delete

### Candidates ✅

- Central candidate database with deduplication via `core_persons`
- **Education** (`recruit_candidate_education`)
- **Experience** (`recruit_candidate_experience`)
- **Skills** (`recruit_candidate_skills`)
- **Tags** (`recruit_candidate_tags`)
- Custom fields support
- Tenant isolation enforced at repository level with tests
- Archive / restore / delete

### Applications ✅

- Candidate × vacancy link with per-vacancy pipeline stage tracking
- Stage history (`recruit_application_stage_history`)
- Move between stages via `POST /applications/{code}/move`
- Auto-advance with passed-through history
- Schedule-interview action from application
- Make-offer action from application

### Interviews & Scorecards ✅

- Schedule from application, participant management
- Status lifecycle (scheduled, completed, cancelled)
- Scorecards with attribute-based scoring
- Per-interview scorecard UI
- Calendar source (RA-2)

### Offers ✅

- Full lifecycle: draft → send → accept/decline/withdraw
- Offer history (`recruit_offer_history`)
- Event bus integration (`PersonHiredEvent` dispatched on accept)
- Calendar source (RA-2)

### Reports ✅

- **Pipeline funnel** — applications per stage per vacancy
- **Time-to-hire** — days from application to offer, with CSV export
- **Recruiter productivity** — activity counts per recruiter
- CSV export at `/recruit/{entity}/export.csv`

### Calendar ✅ (RA-2)

- Month view at `/recruit/calendar` and `/cas/calendar`
- Aggregates events from interviews, offers, tasks, and vacancies
- Permission: `recruit.calendar.view` / `cas.calendar.view`
- Tenant-scoped event sources

### Tasks ✅ (RA-1)

- Full task management at `/recruit/tasks`
- Attach to any entity (candidate, vacancy, application, interview,
  offer) or stand alone
- Priority, due date, assignee, statuses (open, completed, cancelled)
- Per-user overdue and open counts

### Global & Live Search ✅

- **Global search** at `/recruit/search` — cross-entity lookup
- **Live search API** at `/recruit/api/live-search` — AJAX autocomplete
  per entity type
- **Full-text API** at `/recruit/api/search`

### Activity & Audit ✅

- **Per-entity timeline** at `/recruit/activity/{type}/{id}` — human-
  readable business events from `core_activities`
- **Global activity feed** at `/recruit/activity`
- **HTTP audit trail** — every state-changing request written to
  `core_audit_logs` via `AuditLoggerMiddleware`
- **RBAC audit trail** — every role/tenant/delegation mutation written
  to `core_rbac_audit_logs`

### Notifications ✅

- In-app bell with badge, dropdown, AJAX polling
- Configurable rules engine (`core_notification_rules`)
- 17 default rules seeded
- Admin UI at `/recruit/admin/settings/notifications`
- Human-friendly labels

### Custom Fields Engine ✅

- 12 self-validating input types (text, long text, number, email, URL,
  phone, date, dropdown, multi-select, checkbox, file, currency)
- Admin field builder at `/recruit/admin/custom-fields`
- Per-entity support (organisation, contact, engagement, vacancy,
  candidate)
- Live preview, auto-render, auto-validate, archive vs. delete,
  move-up/down, toggle-required, restore

### Configurable Lookups ✅

- Admin-managed enums at `/recruit/admin/lookups`
- Groups and values (`/lookups/{group}`, create, edit, delete)
- Replaces hardcoded enums for status, roles, and communication
  methods
- System-managed vs. user-managed distinction

### File Storage ✅

- `core_files` + `core_entity_files` tables
- UUID-based public identifiers
- Files stored outside web root, streamed via PHP
- Permission-checked at download time
- Middleware-based upload integration (`CustomFieldFileMiddleware`)
- `/recruit/documents` and `/recruit/files/{uuid}`

### Admin ✅

Full administrative surface at `/recruit/admin`:

- **Users** — CRUD with role assignment
- **Roles & Permissions** — role CRUD, permission matrix, user
  assignment
- **Custom Fields** — field builder per entity
- **Lookups** — enum management
- **Branding** — logo, colours, login message (per tenant)
- **Email Templates** — admin-editable templates
- **Notification Rules** — rules engine configuration
- **Audit** — HTTP and RBAC audit views
- **Settings** — security, session, password policy, rate limits

### Database Conventions ✅

- All tables prefixed `core_*` or `recruit_*`
- All foreign keys, CHECK constraints, indexes named per convention
- Two database users (limited web app, elevated CLI)
- Multi-tenant isolation enforced at repository level

---

## 6. What's Not Yet Built

The following are **verified absent** from code:

### External-Facing Features

These are the remaining gap to a competitive market ATS:

- **Sourcing & job distribution** — no multi-board posting, no
  careers-page builder, no LinkedIn/Indeed integration. Jobs are
  managed internally only.
- **Candidate self-apply** — no public application form. Candidates
  are recruiter-entered.
- **Resume parsing** — candidates typed manually; no parser.
- **Client portal** — no external-user routes for hiring managers.
- **Candidate portal** — no self-service for applicants.
- **Public job board** — no public routes; the entire route surface
  requires authentication.

### Peripheral ATS Features

- **Assessments module** — no controller, no service, no route.
- **Reference checks** — not built.
- **Email sync / two-way communication** — outbound only (system
  notifications). No candidate inbox logging.
- **2FA UI** — schema columns exist; no UI at `/recruit/account`.
- **Payments (Stripe / M-Pesa)** — no controller, no route, no stub.

### Cross-Module

- **HR Aid** — registered as a module (`app/Modules/HR/`) but disabled
  in `core_module_state`. `PersonHiredHandler` exists to receive
  `recruit.person.hired` events; no HR routes ship yet.
- **Corporate Connect worker** — event outbox exists; background
  worker not built.

### Operability

- **Down migrations** — migrations are forward-only.
- **Health check endpoint** — `/health` returns a static OK.
- **Email queue** — emails sent synchronously.
- **Vendor console / Server Manager UI** — not built.
- **License activation UI** — not built.

---

## 7. Version History

### v0.9.0-alpha — Current

The "candidate lifecycle + RBAC overhaul" release. Complete candidate
through offer pipeline, four-axis authorization model, platform
surface, calendar, and reports.

**This cycle:**
- Candidates, Applications, Interviews, Scorecards, Offers modules —
  full lifecycle from sourcing through offer acceptance
- Reports: pipeline funnel, time-to-hire, recruiter productivity, CSV
- Four-axis RBAC model (migrations 0061-0067)
- Developer super-administrator authority (Rule 3b)
- Platform surface (`/cas/*`) — tenant CRUD, delegations, context
  switch
- RBAC audit trail (`core_rbac_audit_logs`)
- `ProtectPlatformOwnerMiddleware` — owner accounts protected from
  non-owner modification
- Tasks module (RA-1)
- Calendar feature (RA-2) — aggregates interviews, offers, tasks,
  vacancy deadlines
- Configurable lookups system
- Global + live search
- Global + per-entity activity feed
- Custom field file uploads via middleware

### v0.6.0-alpha — 2026-09-25

Foundation hardening. Naming convention, audit logs, notifications,
error reference IDs.

**This cycle:**
- Onion middleware refactor
- Audit log system
- Configurable notification rules
- Error reference IDs
- Content Security Policy
- File upload foundation
- Full naming convention adopted
- 22 new platform foundation tables

### v0.5.0-alpha — 2026-09-24

Password reset, rate limiting, re-auth gate, settings hub,
notification bell.

### v0.4.0-alpha — 2026-09-23

Engagements, contacts, recruitment contracts.

### v0.3.0-alpha — 2026-09-23

Custom fields engine, deletion workflow, Organisations module.

### v0.2.0-alpha — 2026-09-23

Authentication + RBAC, design system.

### v0.1.0-alpha — 2026-09-23

Custom PHP MVC framework, migrations, seeders, routing.

---

## 8. Deployment Target

**For the customer:**

- Shared cPanel hosting
- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
  `json`, `ctype`, `tokenizer`, `dom`, `gd`, `curl`, `zip`
- MySQL 8.0+ or MariaDB 10.6+
- Apache with `mod_rewrite`
- Free SSL (AutoSSL)
- Cron job support
- SMTP email account

**No SSH, no VPS, no Docker required.**

---

## 9. License

Proprietary. Sold as a white-labelled product to recruitment agencies.

Each install is licensed per-module. The customer owns the code, the
data, and the infrastructure. Corporate Aid Solutions has no runtime
involvement after the license key is issued.

---

## 10. References

- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the ADRs behind the design
- `docs/NAMING-CONVENTION.md` — naming rules across the suite
- `docs/CHANGELOG.md` — chronological version history
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/RENAME-RECORD.md` — the naming convention execution record
- `docs/RBAC-ARCHITECTURE.md` — the four-axis authorization model
- `docs/CAS-PLATFORM-SURFACE.md` — the `/cas/*` platform surface
- `docs/RBAC-TESTING.md` — acceptance criteria to test map
- `docs/REMEMBER.md` — current session state
