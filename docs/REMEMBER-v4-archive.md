# remember v3 — Recruit Aid Project State

**Paste this into a new chat to resume exactly where we left off.**

---

## Session Context

**Date range:** 23 September 2026
**Project:** Recruit Aid — Recruitment Operations Platform
**Working directory:** C:\projects\recruitaid
**Served at:** http://localhost:8080
**Version:** 0.4.0-alpha

---

## Recent Changes (since v0.3.0-alpha)

**Engagements module — complete**
- Migration 0010 (engagements, engagement_packages, engagement_recruiters)
- Engagement model + EngagementRepository with capacity tracking
- EngagementValidator + EngagementService
- EngagementEntity (DeletableEntity)
- EngagementController with top-level /engagements routes
- Views (list, form, detail)
- Package templates: 5 seeded (3/6/12-month, Focused Search, Applicant Assessment)
- Capacity meter with visual bar
- Recruiter assignment with user dropdown (auto-filters already-assigned users)

**Contacts module — complete**
- Migration 0009 (organisation_contacts + contact_code counter)
- Full CRUD, custom fields, archive/delete

**Sequence counters — fully operational**
- organisation_code, contact_code, engagement_code
- Codes never reused after hard delete

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
- Laragon Terminal for php, composer, mysql commands
- WSL for file operations
- WSL does NOT have php — always use Laragon Terminal
- WSL cannot reach MySQL — different network namespaces
- HeidiSQL is the GUI DB client (127.0.0.1)
- Browser DevTools Console for JS debugging (F12)

**Database Users:**
| User | Used by | Privileges |
|---|---|---|
| recruitaid_app | Web app | SELECT, INSERT, UPDATE, DELETE |
| recruitaid_migrate | CLI only | ALL |
| recruitaid | Legacy fallback | ALL |

**Config split:**
- config/local.php → web app, uses recruitaid_app
- config/cli.php → CLI scripts, uses recruitaid_migrate

---

## Database State

**Database:** recruitaid
**Migrations applied:** 0001 through 0010
**Sequence counters:** organisation_code, contact_code, engagement_code

**Users:**
- admin@recruitaid.test — super_admin
- researcher@recruitaid.test — researcher — ResearchPass!2026

---

## Completed Chunks

### Step 6 — Authentication, RBAC, Themes (Complete)
### Step 7.0 — Custom Fields Foundation (Complete)
### Step 7.1 — Organisations Module (Complete)
### Step 7.2 — Contacts Module (Complete)
### Step 7.3 — Engagements Module (Complete)

---

## Last Action Taken

- Recruiter assignment dropdown fixed — user dropdown now populates with names + role labels, filters out already-assigned users
- Confirmed dropdown works in both directions (assign/unassign)
- Backlog section was supposed to be added to REMEMBER.md but the file was corrupted by a bad paste

### Next Immediate Step

**Step 8.1 — Vacancies module.**

The first module in the Recruitment tier. When built:
- The `activeVacancyCount()` method (currently returns 0) will return real numbers
- The capacity meter on engagement detail pages will start filling in
- The "Add vacancy (soon)" quick action becomes real
- Pipeline stages (14 defaults already seeded in `pipeline_stages`) start being used

**Scope of Vacancies:**
- `vacancies` table with all fields (title, org, engagement, dept, country, work_mode, employment_type, seniority, dates, salary, currency, description, requirements, skills, languages, status)
- Vacancy pipeline stages (per-vacancy, cloned from defaults)
- Vacancy controller + views
- Custom fields support (entity type `vacancy`)
- Full CRUD + archive + delete
- Capacity check: warn when engagement capacity is exceeded
- Sequence counter for vacancy codes (VAC-000001)

---

## 🚧 Backlog — Must Ship Before Customer Deployment

These items are **required for this development cycle**, not deferred indefinitely.

### Configurable Lookup System (High Priority)

**Problem:** Several enums are hardcoded:
- Engagement roles (lead / recruiter / researcher)
- Contact roles (5 boolean flags)
- Preferred communication methods
- Organisation status, engagement status, service type, reporting frequency
- (Future) Vacancy statuses, application stages, seniority levels, employment types

**Solution:** Build one generic `lookups` table + admin UI, then migrate each hardcoded enum to a lookup group.

**Schema:**
```sql
CREATE TABLE lookups (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lookup_group    VARCHAR(60) NOT NULL,
    key_slug        VARCHAR(60) NOT NULL,
    label           VARCHAR(150) NOT NULL,
    description     VARCHAR(500) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_system       TINYINT(1) NOT NULL DEFAULT 0,
    meta            JSON NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uniq_group_key (lookup_group, key_slug)
);
Admin UI: Admin → Configuration → Lookups → {group}

Estimated effort: 4-6 pastes

Other Backlog Items
File upload storage (FileType renders but doesn't persist)

Pipeline stages admin UI (schema exists, 14 defaults seeded)

Activity timeline on detail pages

Email templates

2FA UI

Rate limiting

Vendor console

Payment integration (Stripe + M-Pesa stubs)

Data retention cron jobs

Full security audit

Debugging Doctrine (Locked In)
When something breaks:

Diagnose with evidence — read the log, run console diagnostics, check DB state

Rewrite the affected file — full-file rewrite, not patches

Verify — run a specific test that proves it works

Never patch on top of patch

Recent diagnostic wins:

Sidebar scroll container is .ra-nav, not .ra-sidebar

Migrator exec() cursor-draining bug

View path resolution bug (rtrim stripping trailing chars)

Sequence counter bootstrapping

Recruiter assignment user dropdown

Known Issues
File uploads don't persist

No 2FA UI

No rate limiting

Custom field errors shown at top of form (not per-field inline)

No Git repository — backups are manual tarballs

php -l must run in Laragon Terminal, not WSL

When adding new modules, register repository + validator + service in app/Core/App.php

Long pastes (>300 lines) can truncate silently — split into parts and verify

Debugging Commands
bash
# Log tail (WSL)
tail -5 /mnt/c/projects/recruitaid/storage/logs/app.log

# Migrations (Laragon Terminal)
cd C:\projects\recruitaid
php bin\migrate.php

# Autoload (Laragon Terminal)
composer dump-autoload
Backups
Location (in WSL): ~/recruitaid-backups/

recruitaid-milestone1 through milestone5 tarballs

db/recruitaid-schema.sql

How to Resume in a New Chat
Paste this file as the first message, then say:

"Resume from where we left off. Next step: Step 8.1 — Vacancies."

Two Commands to Verify Health
bash
# 1. Migrations up to date (Laragon Terminal)
php bin\migrate.php
# Expected: "Nothing to apply. All migrations are up to date."

# 2. Autoloader (Laragon Terminal)
composer dump-autoload
# Expected: "Generated optimized autoload files containing 310+ classes"
End of remember v3

---

## Backlog — Must Ship Before Customer Deployment

**Configurable Lookup System (High Priority).** Several enums are hardcoded (engagement roles, contact role flags, preferred communication methods, organisation/engagement statuses, service types, reporting frequencies). Solution: build one generic `lookups` table + admin UI, then migrate each hardcoded enum to a lookup group.

**Other backlog items:**
- File upload storage (FileType renders but doesn't persist)
- Pipeline stages admin UI (schema exists, 14 defaults seeded)
- Activity timeline on detail pages
- Email templates
- 2FA UI
- Rate limiting
- Vendor console
- Payment integration (Stripe + M-Pesa stubs)
- Data retention cron jobs
- Full security audit


# remember v4 — Recruit Aid Project State

**Paste this into a new chat to resume work.**

---

## Session Context

**Project:** Recruit Aid — Recruitment Operations & Applicant Tracking System
**Working directory:** `C:\projects\recruitaid`
**Served at:** `http://localhost:8080`
**Version:** 0.5.0-alpha
**Model:** Sold to recruitment agencies as a white-labelled platform they own and rebrand
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
- **HeidiSQL** is the GUI DB client (connects to 127.0.0.1)
- **Browser DevTools Console** for JavaScript debugging (F12)

**Database Users:**
| User | Password | Used by | Privileges |
|---|---|---|---|
| recruitaid_app | recruitaid_app_dev_2026 | Web app | SELECT, INSERT, UPDATE, DELETE |
| recruitaid_migrate | recruitaid_migrate_dev_2026 | CLI only | ALL |
| recruitaid | recruitaid_local_dev | Legacy fallback | ALL |

**Config split:**
- `config/local.php` → web app, uses `recruitaid_app`
- `config/cli.php` → CLI scripts, uses `recruitaid_migrate`

---

## Database State

**Database:** `recruitaid`
**Migrations applied:** 0001 through 0011
**Sequence counters in use:** organisation_code, contact_code, engagement_code, vacancy_code

**Users:**
- `admin@recruitaid.test` — super_admin
- `researcher@recruitaid.test` — researcher — `ResearchPass!2026`

---

## Completed Modules

### Foundation (Complete)
Custom PHP MVC framework, config system, error handling, front controller, migrations, seeders

### Authentication & Authorization (Complete)
Login, logout, password change (no reset yet), session security, CSRF, password hashing (Argon2id), 7 roles, 42+ permissions, permission middleware

### Design System (Complete)
Dark/Light/System themes with per-user preference, sidebar shell, component library

### Custom Fields Engine (Complete)
12 self-validating input types, field builder admin UI, live preview, auto-render, auto-validate, archive vs delete

### Deletion Workflow (Complete)
Reusable DeletionService, DeletableEntity interface, confirmation phrases, modal

### Organisations Module (Complete)
Full CRUD, search/filter/pagination, custom fields, archive, delete, sequence counter, capacity

### Contacts Module (Complete)
Per-organisation contacts, role flags, custom fields, full CRUD

### Engagements Module (Complete)
Service types, package templates, capacity meter, recruiter assignment with user dropdown, custom fields, full CRUD, sequence counter

### Vacancies Module (Complete)
Full schema with pipeline stages, custom fields, capacity check hook, engagement/contact dropdowns filtered by org, full CRUD, sequence counter, pipeline stage cloning (14 stages)

### Security Hardening (In Progress)
Limited MySQL user, elevated CLI user, sequence counters, CHECK constraints on key tables, friendly error pages with context-aware messages

---

## Files & Structure
app/
Controllers/ (Auth, Dashboard, Home, Account, Organisation, Contact, Engagement, Vacancy + Admin/*)
Core/ (App, Auth, Config, Container, Csrf, Database, Migrator, Request, Response, Router, Session, View, Exceptions/HttpException)
Helpers/functions.php
Http/Kernel.php
Middleware/ (Auth, Csrf, Guest, Permission)
Models/ (User, Organisation, Contact, Engagement, Vacancy, VacancyStage, VacancyRequirement, CustomField, CustomFieldValue, PipelineStage)
Repositories/ (User, Role, Organisation, Contact, Engagement, Vacancy, VacancyStage, CustomField, CustomFieldValue, PipelineStage)
Services/ (CustomField, Deletion, Organisation, Contact, Engagement, Vacancy)
Support/
Logger, Hasher, CodeGenerator, ActivityLogger
CustomFields/ (InputType interface, Registry, ValidationException, Renderer, ValueValidator, ValueSaver, InputTypes/ 12 classes)
Deletion/ (DeletableEntity interface, ConfirmationPhrase, DeletionResult, Entities/OrganisationEntity, ContactEntity, EngagementEntity, VacancyEntity)
Validation/ (CustomFieldValidator, OrganisationValidator, ContactValidator, EngagementValidator, VacancyValidator)

public/assets/css/app.css (single source of truth, ~1000 lines, themes)
public/assets/js/app.js (theme, sidebar scroll, dropdowns)
public/assets/js/custom-fields.js (field builder dynamic behavior)
public/assets/js/deletion-modal.js (phrase matching)

resources/views/ (layouts, partials, per-module)
routes/ (web.php, auth.php, client.php, public.php)
database/migrations/ (0001-0011)
docs/ (ABOUT.md, REMEMBER.md)

text

---

## 🚧 CRITICAL AUDIT — Complete Gap List

This is the honest list of what's missing, half-baked, or risky. **Ordered by priority.**

### Tier 1 — Must Fix Before Any Further Features (~10-12 pastes)

1. **Password reset flow** — routes, controller, token generation, email sending. Currently the "Forgot password?" link goes to a 404.
2. **Email sending infrastructure** — EmailService, template rendering, Mailpit test. Currently PHPMailer is installed but unused.
3. **Rate limiting on auth endpoints** — login, signup, password reset. Currently `failed_logins` per user only.
4. **Audit logger middleware** — auto-logs all POST/PUT/PATCH/DELETE. Currently relies on services calling `ActivityLogger` manually.
5. **Soft-delete consistency audit** — some `findById()` methods don't filter `deleted_at IS NULL`.
6. **Error reference IDs on 500 page** — log correlation for support.
7. **Consent management UI** — `consent_status` exists on candidates schema, no UI.
8. **CSP header** — currently only X-Frame-Options, X-Content-Type-Options.
9. **Update ABOUT.md** to v0.5.0-alpha with current feature set.
10. **Documentation of new modules** — each module needs a short README.

### Tier 2 — Core ATS Foundation (~15-20 pastes)

11. **File uploads + document service** — FileType renders but doesn't persist. Needed for CVs, certificates, IDs.
12. **Duplicate detection + merge** — no email-based dedup check when creating candidates/orgs. Critical for candidate module.
13. **Activity timeline UI** — `activities` table gets logged, no view displays it.
14. **Timezone-correct display** — server time is `Africa/Nairobi`, users have their own timezone.
15. **Pipeline stages admin UI** — 14 defaults seeded, no UI to customize per organisation.
16. **Engagement capacity enforcement** — meter shows real numbers, no warning when exceeded at create time.
17. **Resume parsing integration** — candidates typed manually; industry-standard is auto-parse.
18. **CSV export** — no list exports.
19. **Retention cron + right to erasure** — `retention_until` on candidates, no enforcement. GDPR "right to be forgotten" not implemented.
20. **2FA UI** — schema columns exist, no UI.

### Tier 3 — Complete Core ATS Modules (~50-60 pastes)

21. **Candidates module** — central candidate database (tags, skills, experience, education).
22. **Applications module** — candidate × vacancy link with pipeline stage tracking.
23. **Assessments module** — assessment templates, criteria, scoring. Schema exists, no UI.
24. **Interviews module + scorecards** — schema exists, no UI.
25. **Offers module** — schema exists, no UI.
26. **Reference checks** — schema exists, no UI.
27. **Shortlists + Client Review** — recruiter submits candidates, client reviews. Not built.
28. **Tasks** — schema exists, no UI.
29. **Notes UI** — schema exists, no UI.
30. **Approval workflows Phase 1** — requisition approval, offer approval. Not built. See research below.
31. **Reports + funnel analytics** — schema exists, no UI, no export.
32. **Market observations** — schema exists, no UI.
33. **Talent pool + redeployment** — reuse past candidates. Not built.
34. **Bulk actions on lists** — no bulk select.

### Tier 4 — Business Model Completion (~25-35 pastes)

35. **Client portal** — employer-facing, isolated. Not built.
36. **Candidate portal** — self-service, application status tracking. Not built.
37. **Public job board + career portal** — not built.
38. **Self-registration + payments** — package purchases, Stripe + M-Pesa. Not built.
39. **Multi-channel job posting** — Indeed, LinkedIn integrations. Not built.
40. **Email tracking + templates** — open/click tracking, Gmail/Outlook sync. Not built.

### Tier 5 — Operability & Advanced (~20-25 pastes)

41. **Vendor console** — hidden route for developer ops.
42. **Configurable lookups** — generic `lookups` table + admin UI. Replaces hardcoded enums.
43. **Health check endpoint** — expand `/health` with DB/disk checks.
44. **Email queue + cron** — background sending.
45. **Notification system** — in-app bell, notifications table.
46. **Down migrations** — migrator gains reversibility.
47. **AI candidate matching** — rank candidates by fit.
48. **Design polish** — empty states, loading states, breadcrumb consistency.

---

## Approval Workflow Research Summary

Approval workflows exist on **both sides** — agency internal and client side. Canonical patterns:

**Internal approvers:** Recruiter → Recruitment Manager → Finance (for offers)
**Client approvers:** Hiring Manager → HR → Finance → Decision Maker

**Patterns to support:**
1. Sequential (one at a time)
2. Parallel (all at once)
3. Rules-based routing (if salary > X, require Finance)
4. Ad-hoc approvers
5. Delegate/alternate approver
6. Re-approval on field change
7. Bypass options
8. Escalation + reminders

**Schema (planned):**
- `approval_workflows` (templates per trigger type)
- `approval_workflow_steps` (steps within a workflow)
- `approval_requests` (active requests)
- `approval_actions` (decisions per step)

**Triggers:** vacancy submission, offer issuance, engagement approval, candidate submission to client.

**Build phases:**
- Phase 1: Schema + basic sequential workflow + vacancy integration (6-8 pastes)
- Phase 2: Notifications + SLA reminders + escalation (4-5 pastes)
- Phase 3: Client-side approval (submissions, offers) (4-5 pastes)

---

## Debugging Doctrine (Locked In)

1. Diagnose with evidence — read the log, run console diagnostics, check DB state
2. Rewrite the affected file — full-file rewrite, not patches
3. Verify — run a specific test that proves it works
4. Never patch on top of patch

**Recent diagnostic wins:**
- Sidebar scroll container is `.ra-nav`, not `.ra-sidebar`
- Migrator `exec()` cursor-draining bug
- View path resolution (`rtrim` stripping trailing chars)
- Sequence counter bootstrapping
- Long paste truncation (>250 lines) — use VS Code
- `last_value` is a MySQL reserved word — needs backticks

---

## Known Issues

1. File uploads render but don't persist
2. No password reset
3. No email sending
4. No 2FA UI
5. No rate limiting
6. No CSP header
7. No consent UI
8. `pending_approval` status exists but no workflow
9. Custom field errors shown at top of form (not per-field inline)
10. `findById()` inconsistency on soft-delete filtering
11. Long pastes (>250 lines) truncate silently — use VS Code
12. `php -l` must run in Laragon Terminal, not WSL

---

## Backup History

Location (in WSL): `~/recruitaid-backups/`
- milestone1 through milestone6 tarballs
- `db/` folder with schema dumps

**Restore command:**
```bash
cd /mnt/c/projects/recruitaid
tar -xzf ~/recruitaid-backups/recruitaid-milestone6-engagements-done-*.tar.gz
Two Commands to Verify Health
bash
# 1. Migrations (Laragon Terminal)
cd C:\projects\recruitaid
php bin\migrate.php
# Expected: "Nothing to apply."

# 2. Autoloader (Laragon Terminal)
composer dump-autoload
# Expected: "Generated optimized autoload files containing 320+ classes"
How to Resume
Paste this file as first message, then say:

"Resume from where we left off. Next: Tier 1 — password reset and email infrastructure."
