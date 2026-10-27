# Recruit Aid — About the Application

**Product:** Recruit Aid
**Suite:** Corporate Aid Solutions
**Type:** Recruitment Operations & Applicant Tracking System (ATS)
**Model:** Sold as a white-labelled product to recruitment agencies who own and rebrand their install
**Deployment target:** Shared cPanel hosting (PHP 8.2+, MySQL 8, Apache)
**Current version:** 0.6.0-alpha

---

**See also:**
- `docs/ARCHITECTURE.md` — how the system is built
- `docs/SPECIFICATION.md` — the design decisions behind it
- `docs/NAMING-CONVENTION.md` — naming rules across the suite
- `docs/REMEMBER.md` — current session state

---

## 1. What It Is

Recruit Aid is a complete Applicant Tracking System (ATS) built for
recruitment agencies. It helps an agency manage:

- **Client organisations** — the companies hiring
- **Client contacts** — the people at each client
- **Engagements** — recruitment contracts with scope, duration, and
  capacity
- **Vacancies** — the jobs being filled, with per-vacancy pipelines
- **Candidates** — the people being placed *(in progress)*
- **Applications** — candidate × vacancy links with stage tracking
  *(in progress)*
- **Assessments, interviews, offers, reference checks** *(planned)*
- **Client and candidate portals** *(planned)*

The buyer — a recruitment agency — installs **one instance** on their
own hosting, rebrands it with their logo and colours, and operates it
for their team, employers, and candidates.

The platform is designed to be **configured by the customer**: they
create custom fields on any entity, define validation by picking an
input type, and manage roles and permissions without a developer.

---

## 2. Key Product Principles

1. **Configuration over code** — if a customer might want to change
   it, they can change it from the UI.
2. **Archive, not delete** — records with dependents are archived, not
   destroyed. A purge cron handles true deletion later.
3. **Self-validating fields** — pick an input type, the platform
   validates automatically.
4. **Audit everything** — every state-changing HTTP request is logged
   (method, path, user, IP, response status). Business events are
   logged to a separate timeline.
5. **Least privilege** — the web app runs on a limited database user
   that cannot modify schema. Only CLI scripts run with elevated
   credentials.
6. **Security first** — built with Kenya Data Protection Act 2019 and
   GDPR-readiness in mind. CSP, CSRF, rate limiting, Argon2id,
   secure session handling.
7. **Owner owns everything** — the customer owns the code, the data,
   and the infrastructure. No vendor runtime dependency.

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

**What we deliberately do NOT use:**

Node.js, npm, Webpack, Vite, React, Vue, Angular, Alpine, Docker,
Kubernetes, Redis, Memcached, PostgreSQL, MongoDB, or any service
requiring a VPS.

Everything runs on ordinary shared cPanel hosting with no SSH required.

---

## 4. Suite Context

Recruit Aid is product #1 in the **Corporate Aid Solutions** suite.

The suite has three tiers:

| Tier | Format | Examples |
|---|---|---|
| **Tier 1** — Company | — | Corporate Aid Solutions (vendor) |
| **Tier 2** — Functional modules | `{Function} Aid` | Recruit Aid, HR Aid, CRM Aid, Payroll Aid, … |
| **Tier 3** — Industry verticals | `{Industry} Aid` | Farm Aid, Clinic Aid, Supermarket Aid, … |
| **Tier 0** — Integration layer | — | Corporate Connect (customer-local) |

**Each module can be purchased independently.** A customer can run
Recruit Aid alone, or later add HR Aid, CRM Aid, or any other module.
Corporate Connect handles the communication between modules — but it
runs **inside the customer's own instance**, not on Corporate Aid's
servers.

Full taxonomy: `docs/NAMING-CONVENTION.md` §1.

---

## 5. What's Built

### Foundation ✅

- Custom PHP MVC framework
- Config system with per-deployment overrides
- Error handling with friendly user pages (403, 404, 500)
- Error reference IDs on 500s (`ERR-xxxxxx`)
- Front controller + `.htaccess` routing
- Migrations system (`php bin/migrate.php`)
- Seeders system (`php bin/seed.php`)
- Three-tier namespace + directory structure (Core / Platform / Recruit)

### Authentication & Authorization ✅

- Login, logout, password change
- Password reset flow with email delivery
- Re-authentication gate for sensitive actions
- Session security (HttpOnly, SameSite, rotation, idle timeout)
- CSRF protection on all POST/PUT/PATCH/DELETE
- Password hashing with Argon2id
- 7 roles, 45+ permissions
- Granular permission middleware with human-friendly slugs

### Design System ✅

- Dark / Light / System themes with per-user preference
- Sidebar shell with scroll persistence
- Component library: page bands, tiles, cards, tables, badges,
  alerts, toggles
- Mobile-responsive with off-canvas drawer
- Notification bell with dropdown, badge, and AJAX polling

### Custom Fields Engine ✅

- 12 self-validating input types (text, long text, number, email,
  URL, phone, date, dropdown, multi-select, checkbox, file, currency)
- Field builder admin UI
- Live preview, auto-render, auto-validate
- Archive vs. delete

### Deletion Workflow ✅

- Reusable `DeletionService` for all modules
- `DeletableEntity` interface with confirmation phrases
- Modal with phrase matching, optional reason, dependent list
- Soft-delete with restore

### Organisations Module ✅

- List with search, filters, pagination
- Create / edit with custom fields
- Detail page with tabs
- Archive, restore, delete with confirmation
- Sequence counter (`ORG-000001`, never reused)

### Contacts Module ✅

- Per-organisation contacts
- Role checkboxes (primary, hiring manager, finance, HR,
  decision maker)
- Custom fields support
- Sequence counter (`CNT-000001`)

### Engagements Module ✅

- Service types (3-month, 6-month, 12-month, Focused Search,
  Applicant Assessment)
- Package templates with auto-fill
- Capacity tracking with visual meter
- Recruiter assignment (lead, recruiter, researcher)
- Custom fields, full CRUD + archive + delete
- Sequence counter (`ENG-000001`)

### Vacancies Module ✅

- Full schema with per-vacancy pipeline stages (14 defaults cloned
  per vacancy)
- Custom fields, capacity check hook
- Engagement / contact dropdowns filtered by organisation
- Full CRUD, sequence counter (`VAC-000001`)

### Settings Hub ✅

- System settings with server-side validation
- Security settings page (session, password, rate limits)
- Notification rules admin UI
- Settings changes logged + trigger notifications to other super admins

### Notification System ✅

- Bell + badge + dropdown + AJAX polling
- Configurable rules in `core_notification_rules`
- 17 default rules seeded
- Human-friendly labels (no raw slugs shown to users)
- Actor-exclusion on settings changes

### Middleware Pipeline — Onion ✅

- All 5 middlewares converted to
  `handle(Request, callable): Response`
- Unlocks post-controller behavior
- Full history: `docs/CHANGELOG-middleware.md`

### Audit Logging ✅

- `core_audit_logs` captures every state-changing HTTP request
- Applied globally via `Router::dispatch()` — not per-route
- Captures user, method, path, IP, user agent, payload hash,
  response status, entity type, entity ID
- Failures (403, 404, 419, 500) captured with their status codes

### Error Reference IDs ✅

- Every 500 generates a short `ERR-xxxxxx` ID
- Shown on error page, prefixed to log line
- Support can `grep ERR-xxxxxx storage/logs/app.log`

### Security Headers ✅

- `Content-Security-Policy` (strict, with `'unsafe-inline'` for now)
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- `X-Powered-By` stripped

### File Storage Foundation ✅

- `core_files` + `core_entity_files` tables
- `FileService` orchestrates upload → validate → store → link
- Files streamed through PHP with proper headers
- `.htaccess` blocks direct access to `storage/uploads/`
- (The custom field integration is in progress)

### Platform Foundation Tables ✅

- Modules, licenses, module state (ready for licensing system)
- Integrations, integration logs (ready for Server Manager)
- Scheduled tasks, health checks (ready for cron + health)
- Updates available / history / manifest (ready for Update System)
- Currencies, notes, feature flags, webhooks, email templates,
  GDPR data requests, consent log, login history, user permissions,
  workflow engine tables (Tier 5 features, schema ready)

### Database Conventions ✅

- All tables prefixed `core_*` or `recruit_*`
- All foreign keys, CHECK constraints, indexes named per convention
- Two database users (limited web app, elevated CLI)
- Database name `corporateaid_recruit` for dev

---

## 6. What's Not Yet Built

### Tier 2 (next)

- File uploads custom field integration (in progress)
- Activity timeline UI
- Duplicate detection (email-based)
- Timezone-correct display
- Pipeline stages admin UI
- Engagement capacity enforcement
- CSV export
- Retention cron + right to erasure
- 2FA UI
- Configurable webhooks delivery
- Email templates admin UI
- Consent management UI

### Tier 3 (core ATS modules)

- Candidates module (central database)
- Applications module (candidate × vacancy with pipeline)
- Application stage history
- Interviews + scorecards
- Offers
- Reference checks
- Assessments
- Tasks
- Notes UI
- Reports + funnel analytics
- Talent pool / redeployment

### Tier 4 (business model)

- Client portal
- Candidate portal
- Public job board
- Self-registration + payments (Stripe / M-Pesa)
- Multi-channel job posting

### Tier 5 (operability & advanced)

- Vendor console / Server Manager UI
- Configurable lookup system
- Health check endpoint
- Email queue + cron
- Down migrations
- AI candidate matching
- License activation UI
- Corporate Connect worker
- Update system

---

## 7. Version History

### v0.6.0-alpha — Current

**This cycle (2026-09-24/25):**
- Onion middleware refactor
- Audit log system (HTTP request layer)
- Configurable notification rules (17 default rules)
- Human-friendly labels everywhere
- Error reference IDs
- Content Security Policy
- File upload foundation
- Full naming convention adopted (tables, namespaces, directories,
  permissions, events)
- 22 new platform foundation tables
- Tier 1 closed

### v0.5.0-alpha

- Vacancies module (complete, with pipeline stages)
- Password reset flow with email
- Rate limiting on auth endpoints
- Re-auth gate with pending-save preservation
- Settings hub with security page
- Notification system (bell + dropdown + polling)

### v0.4.0-alpha

- Engagements module
- Contacts module
- Recruiter assignment with user dropdown
- Sequence counters fully operational

### v0.3.0-alpha

- Custom fields engine (12 input types)
- Field builder admin UI
- DeletionService with confirmation phrases
- Organisations module
- Security hardening (limited DB user)

### v0.2.0-alpha

- Authentication + RBAC
- Design system foundation

### v0.1.0-alpha

- Custom PHP MVC framework
- Migrations + seeders
- Front controller + routing

Full chronological detail: `docs/CHANGELOG.md`.

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
- `docs/RENAME-RECORD.md` — how the codebase got to this state
- `docs/REMEMBER.md` — current session state
- `README.md` — quick start