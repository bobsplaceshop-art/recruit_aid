# Recruit Aid — Architecture Extension Plan (for HR Aid + Payroll)

**Version:** 1.0 (draft, awaiting approval)
**Date:** 2026-10-30
**Status:** Scoping document. No foundation code written yet.
**Author:** Sely + AI pair
**Related docs:** `ARCHITECTURE.md`, `NAMING-CONVENTION.md`, `ABOUT.md`

---

## 1. Purpose

Recruit Aid is the first module of CAS Suit. The plan to add HR Aid
and Payroll Aid as sibling modules (not as separate apps) requires
foundational work that must be done **now**, while Recruit Aid is
still small enough to refactor. This document scopes that work.

What this document is:
- A concrete, evidence-based plan.
- The sequence of architectural batches that must precede HR Aid.
- The approval gate for decisions that become permanent.

What it is not:
- HR Aid feature design (deferred until foundation is done).
- Payroll scope (deferred until HR is stable).
- A rewrite plan.

---

## 2. Current State — Honest Inventory

### What exists today

- **43 core_ tables** in `0001`, `0024`, `0025` migrations
- **10 recruit_ tables** across `0004`, `0009`, `0010`, `0011`,
  `0028`, `0030`, `0031`, `0032`, `0036`, `0037`
- **3 interfaces** (contracts): `InputType`, `DeletableEntity`,
  `LiveSearchable`
- **3 custom framework layers**: Core, Platform, Recruit
- **1 module** registered in `core_modules` (seeded as `recruit`)

### What is missing for a second module

1. **`organisation_id` scoping**: Only 5 migrations have
   `organisation_id` (`0001`, `0005`, `0009`, `0010`, `0011`). The
   rest — including `0028_create_candidates`,
   `0030_create_applications`, `0031_create_interviews`,
   `0032_create_offers`, `0036_create_vacancy_recruiters`,
   `0037_create_vacancy_hiring_managers` — have none.
   **Impact:** Every new table must be added with `organisation_id`,
   or we will retrofit it across 40+ tables later.

2. **`Person` abstraction**: No `core_persons` table exists. The
   `Candidate` model is the person today. When HR Aid arrives, the
   same human becomes an employee — without a shared `Person`,
   you get two identities for one human.

3. **Module registry populated?**: `core_modules` was seeded with
   just `recruit`. No `core_module_manifests` table exists.

4. **Event outbox schema**: `0024_create_platform_foundation.sql`
   is supposed to create integration tables. We need to read that
   migration before claiming what's there.

5. **No test directory**. PHPStan Level 9 passes but there are
   zero runtime tests. Every change is verified manually.

### Files that already have good structure

- `app/Core/` — stable framework
- `app/Platform/` — shared services, models, repos, middleware
- `app/Recruit/` — module (the only module today)
- `resources/views/` — organised by concern
- `database/migrations/` — 37 migrations, ordered, with names

---

## 3. Target State — What Success Looks Like

### Core tables (shared across all modules)

| Table | Purpose |
|---|---|
| `core_persons` | Canonical human identity. One row per person, ever. |
| `core_person_emails` | One person, many emails (personal, work, verified). |
| `core_person_phones` | One person, many phones. |
| `core_organisations` | **Tenant** organisations (this Recruit Aid install). NOT to be confused with `recruit_client_organisations` (client orgs = customers). |

### Schema additions to existing tables

| Table | New column | Purpose |
|---|---|---|
| All `recruit_*` tables | `organisation_id` | Tenant scope (backfilled to 1 for existing data) |
| `recruit_candidates` | `person_id` | Links candidate to the canonical person |

### New platform tables

| Table | Purpose |
|---|---|
| `core_module_manifests` | What each module declares: migrations, permissions, events, dependencies |
| `core_module_events` | Cross-module event outbox (in-process dispatch + optional async worker) |

### New contracts (interfaces)

- `CorporateAid\Platform\Shared\Contracts\PersonService`
- `CorporateAid\Platform\Shared\Contracts\HireEvent`
- `CorporateAid\Platform\Modules\Contracts\ModuleContract`

### Naming decision needed

The existing `recruit_client_organisations` is the **client**
organisation (companies the agency places candidates at). A
**tenant** organisation is the install owner. These must not be
merged.

**Proposed:** New `core_organisations` for tenants. Rename
`recruit_client_organisations` → keep as-is for now (renaming
later, if needed, is a separate migration).

---

## 4. The 7-Batch Foundation Plan

Each batch is small, self-contained, and does **not** touch HR
features. Order is deliberate: schema first, then data, then code,
then contracts.

### Batch 25 — `core_persons` foundation (schema + repository + model)

**Goal:** Create the canonical `Person` table and its data access
layer. No integration with `Candidate` yet.

**Files:**
- `database/migrations/0038_create_persons.sql`
- `app/Platform/Shared/Models/Person.php`
- `app/Platform/Shared/Repositories/PersonRepository.php`
- `app/Core/App.php` — bind `PersonRepository`

**Schema:**

    CREATE TABLE core_persons (
        id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code           VARCHAR(20) NOT NULL,
        first_name     VARCHAR(100) NOT NULL,
        last_name      VARCHAR(100) NOT NULL,
        preferred_name VARCHAR(100) NULL,
        gender         ENUM('male','female','other','prefer_not_to_say') NULL,
        date_of_birth  DATE NULL,
        nationality    CHAR(2) NULL,
        created_by     INT UNSIGNED NULL,
        created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at     DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_person_code (code),
        KEY idx_person_name (last_name, first_name),
        CONSTRAINT fk_person_creator FOREIGN KEY (created_by) REFERENCES core_users(id) ON DELETE SET NULL
    );

**Verification:** `php -l`, PHPStan, migration runs.

**Deliverable:** Empty `core_persons` table. `Person` model,
`PersonRepository` with `create()`, `findById()`, `findByCode()`,
`nextCode()`. No code reads/writes it yet. Zero user-facing change.

---

### Batch 26 — `organisation_id` tenant scoping (all `recruit_*` tables)

**Goal:** Every `recruit_*` table has `organisation_id`, and every
repository filters by it.

**Files:**
- `database/migrations/0039_add_organisation_id_to_recruit_tables.sql`
- Every `app/Recruit/Repositories/*.php` — add `organisation_id` filter
- `app/Core/TenantContext.php` (new) — resolves the current tenant
- `app/Core/App.php` — bind `TenantContext`

**Schema:**

    ALTER TABLE recruit_client_contacts      ADD COLUMN organisation_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE recruit_client_organisations ADD COLUMN organisation_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE recruit_engagements          ADD COLUMN organisation_id INT UNSIGNED NULL AFTER id;
    -- ... same for all recruit_* tables
    UPDATE recruit_* SET organisation_id = 1 WHERE organisation_id IS NULL;
    -- Follow-up migration makes the column NOT NULL after backfill

**Verification:** Every `recruit_*` table has the column, all rows
have 1, repository queries filter by `TenantContext::id()`.

**Deliverable:** Tenant scoping in place. UI is unchanged (one
tenant today, so everything still shows the same data). New code
paths must use `TenantContext`.

---

### Batch 27 — Link `recruit_candidates` to `core_persons`

**Goal:** Every candidate has a `person_id`. Existing candidates
backfilled idempotently.

**Files:**
- `database/migrations/0040_add_person_id_to_candidates.sql`
- `bin/backfill-candidate-persons.php` (idempotent script, not a migration)
- `app/Recruit/Models/Candidate.php` — `personId` property
- `app/Recruit/Repositories/CandidateRepository.php` — read `person_id`

**Schema:**

    ALTER TABLE recruit_candidates ADD COLUMN person_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE recruit_candidates ADD CONSTRAINT fk_rec_cand_person
      FOREIGN KEY (person_id) REFERENCES core_persons(id) ON DELETE SET NULL;

**Backfill script** (idempotent, run manually):

    php bin/backfill-candidate-persons.php
    # For each candidate without a person_id:
    #   1. Create a core_persons row (first_name, last_name from candidate)
    #   2. Link candidate.person_id = new person.id
    # Safe to re-run: only processes candidates with person_id IS NULL

**Deliverable:** Every candidate has a `person_id`. Existing queries
unchanged (candidate still has its own first_name/last_name — see
Decision B below).

---

### Batch 28 — `PersonService` and the write path

**Goal:** `CandidateService::create()` writes a Person first, then
links the candidate.

**Files:**
- `app/Platform/Shared/Contracts/PersonService.php` (interface)
- `app/Platform/Shared/Services/PersonService.php` (implementation)
- `app/Recruit/Services/CandidateService.php` — call
  `PersonService::create()` before `CandidateRepository::create()`
- `app/Core/App.php` — bind `PersonService`

**Deliverable:** New candidates create both a `core_persons` row and
a `recruit_candidates` row in one transaction. Existing code paths
still work. User-facing: no change.

---

### Batch 29 — Module registry infrastructure

**Goal:** Modules can declare their manifests, migrations,
permissions, events. This is the socket HR Aid will plug into.

**Files:**
- `database/migrations/0041_create_module_manifests.sql`
- `app/Platform/Modules/Contracts/ModuleContract.php` (interface)
- `app/Platform/Modules/ModuleRegistrar.php`
- `app/Modules/Recruit/Module.php` — first module implementing
  `ModuleContract`
- `app/Core/App.php` — call `ModuleRegistrar::boot()`

**Deliverable:** `app/Modules/` directory exists, `Recruit` module's
manifest is registered, `core_module_manifests` has one row. HR Aid
in a future batch creates `app/Modules/HR/Module.php` and is
automatically discovered.

---

### Batch 30 — Cross-module event outbox

**Goal:** Modules can emit and subscribe to events without coupling.

**Files:**
- `database/migrations/0042_create_module_events.sql`
- `app/Platform/Modules/EventBus.php`
- `app/Platform/Modules/Events/PersonHiredEvent.php` (the first event)
- `bin/process-events.php` (cron worker skeleton)
- `app/Core/App.php` — bind `EventBus`

**Schema:**

    CREATE TABLE core_module_events (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type    VARCHAR(100) NOT NULL,
        payload       JSON NOT NULL,
        status        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
        attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_error    TEXT NULL,
        scheduled_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at  DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_event_status (status, scheduled_at)
    );

**Deliverable:** `EventBus::dispatch('recruit.person.hired',
$payload)` writes an outbox row. No handler exists yet. Cron worker
drains the outbox every minute. HR Aid (future) subscribes by adding
a handler.

---

### Batch 31 — HR Aid schema proof (departments + employees)

**Goal:** Prove the socket works. One HR table, one contract, one
integration path.

**Files:**
- `database/migrations/0043_create_hr_employees.sql`
- `app/Modules/HR/Module.php` (implements `ModuleContract`)
- `app/Modules/HR/Repositories/EmployeeRepository.php`
- `app/Modules/HR/Services/EmployeeService.php`
- Handler: `app/Modules/HR/Handlers/PersonHiredHandler.php` —
  subscribes to `recruit.person.hired` and creates a draft employee
  record
- `app/Platform/Modules/ModuleRegistrar.php` — register HR module

**Deliverable:** When a candidate is hired in Recruit Aid, an
`hr_employees` row is created (draft status) automatically. The
cross-module flow is **proven** end-to-end. HR Aid can now grow.

---

## 5. Schema Decisions (Need Approval)

### Decision A — Naming: `core_persons` or `core_people`?

**Recommendation:** `core_persons`. Matches `core_users` naming
(plural of the concept, not the class). "Persons" is grammatically
preferred over "People" when referring to individual identity
records.

### Decision B — Does `recruit_candidates` drop
first_name/last_name/email after backfill?

**Options:**

1. **Drop** the columns from `recruit_candidates`, read from
   `core_persons` always.
   - Pro: single source of truth. Rename person → candidate name
     changes everywhere.
   - Con: every query touching `recruit_candidates.first_name` must
     change.

2. **Keep** them as a cached denormalized copy, sync on
   `PersonService::update()`.
   - Pro: zero query changes. Existing code paths keep working.
   - Con: two copies of the same data. Drift risk if sync fails.

**Recommendation:** **Keep** for v1. The cost of dropping is high,
the cost of drift is mitigated by a single write path
(`PersonService`) and an integrity check script. We can revisit if
a real drift bug appears.

### Decision C — New `core_organisations` vs reuse
`recruit_client_organisations`?

**Recommendation:** **New `core_organisations` for tenants.** They
are fundamentally different:
- `recruit_client_organisations` = the agency's *customers* (companies hiring)
- `core_organisations` = the *tenant* (the agency's own install)

If a future customer installs Recruit Aid + HR Aid, they have 1
tenant and N client orgs. Conflating the two would break the
multi-tenant model.

### Decision D — Build the module registry in batch 29, or defer?

**Recommendation:** **Build it.** Without it, "plug in HR Aid" means
editing `App.php` for every new module. With it, HR Aid is a
manifest + one registration. The registry is ~150 lines and forces
boundary discipline from now.

### Decision E — `organisation_id` on `core_users`?

**Recommendation:** **Yes.** Users belong to a tenant. Without it,
multi-tenant login would be ambiguous.

---

## 6. What We're NOT Touching in the Foundation

- The custom framework (no Laravel migration — that would be a
  multi-month rewrite with zero customer value)
- The existing recruiter, vacancy, interview, offer features
- The `recruit_client_organisations` table (stays for now; renaming
  is a separate future migration)
- The UI (foundation batches are backend-only; no user-facing change
  until batch 31 when HR Aid's first employee auto-creates)

---

## 7. Post-Foundation Estimates (For Context Only)

These are **not** part of the foundation plan. They exist here so
you know what comes after.

### HR Aid features (after batch 31)

| Feature | Est. batches |
|---|---|
| Employee CRUD, employment history | 3–4 |
| Departments + org chart | 2–3 |
| Leave management | 3–4 |
| Attendance + timesheets | 3–4 |
| Performance reviews | 2–3 |
| Document management | 2 |
| **Total HR features** | **~15–20 batches (3–5 months)** |

### Payroll features (after HR)

| Feature | Est. batches |
|---|---|
| Salary structures + payslips | 5–6 |
| Kenyan statutory (PAYE, NHIF, NSSF, SHIF) | 8–12 |
| Payroll runs + bank file export | 4–5 |
| Reports + tax year rollover | 3–4 |
| **Total Payroll** | **~20–30 batches (3–6 months)** |

**Strong recommendation:** Do not start Payroll until HR is stable
and you have a payroll-savvy advisor (accountant or tax consultant).
Payroll errors are legal and financial liabilities. The Kenyan
statutory tables change annually.

---

## 8. Risks

1. **`organisation_id` backfill on a live DB.** Mitigated by
   nullable → backfill → NOT NULL in a separate migration, and by
   running the backfill in batches of 1000 rows.

2. **`person_id` on candidates.** Mitigated by the idempotent
   backfill script and by **keeping** the candidate's
   first_name/last_name (Decision B).

3. **The Person abstraction can feel over-engineered for one
   module.** It becomes clearly necessary the moment HR Aid exists
   (converted candidates become employees, duplicate identities
   would otherwise be unmaintainable).

4. **No test infrastructure.** Every batch is verified by
   `php -l` + PHPStan + manual runtime test. Adding PHPUnit is a
   separate 2-batch effort and can follow the foundation.

5. **You're solo.** The foundation is 23–30 hours across 7 batches.
   At our current cadence (~4 hours per session, 2 batches per
   session), that's 3–4 sessions. Feasible.

---

## 9. Approval Gates

Before we write batch 25, I need your approval on:

- [ ] **A:** `core_persons` naming
- [ ] **B:** Keep candidate first_name/last_name/email (don't drop)
- [ ] **C:** New `core_organisations` for tenants
- [ ] **D:** Build module registry in batch 29
- [ ] **E:** Add `organisation_id` to `core_users`

Once A–E are approved, we start **batch 25** (the `core_persons`
schema + repository + model). No feature code. No candidate
integration yet. The first commit is small enough to review in 10
minutes.

---

## 10. Explicitly Deferred

The following are not in scope for the foundation, and we will not
touch them:

- Field-level permission policy engine (§8–§10 of the CAS Suit
  master architecture prompt) — deferred until HR Aid exists and
  there's a real cross-module flow to govern
- Full module licensing/entitlement system — deferred
- Public API / Corporate Connect — deferred
- Reporting layer across modules — deferred
- 2FA, consent management UI, email templates admin — Tier 2,
  still on the Recruit Aid backlog

---

End of document.
