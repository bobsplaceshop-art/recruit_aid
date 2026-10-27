# Corporate Aid Solutions — Naming Convention

**Status:** Locked
**Effective:** 2026-09-24
**Last revised:** 2026-09-25
**Applies to:** Every module, table, file, class, route, permission, activity event, API endpoint, and configuration key across the entire Corporate Aid Solutions suite.
**Supersedes:** Any prior ad-hoc naming.

---

**See also:**
- `docs/ARCHITECTURE.md` — how the tiers fit together in code
- `docs/SPECIFICATION.md` — the ADRs that produced these rules
- `docs/RENAME-RECORD.md` — historical execution of the rename cycle
- `docs/REMEMBER.md` — current session state

---

## 1. The Three-Tier Product Model

Corporate Aid Solutions is a suite of business software. It has three tiers of naming that must never be confused.

### Tier 1 — The Company

**Corporate Aid Solutions**

The vendor. Appears on the marketing site, invoices, license agreements, and optionally in customer-facing footers. **Never appears in customer URLs** and never appears in the customer's own instance as a runtime dependency.

### Tier 2 — Functional Modules

Horizontal capabilities that work in any industry. Each module owns its own data and can be sold, installed, and upgraded independently.

Format: `{Function} Aid`

| Module | Prefix | Product Name | Status |
|---|---|---|---|
| Recruit | `recruit` | Recruit Aid | Active |
| HR | `hr` | HR Aid | Planned |
| CRM | `crm` | CRM Aid | Planned |
| Payroll | `payroll` | Payroll Aid | Planned |
| Leave | `leave` | Leave Aid | Planned |
| Performance | `performance` | Performance Aid | Planned |
| Task | `task` | Task Aid | Planned |
| Inventory | `inventory` | Inventory Aid | Planned |
| Finance | `finance` | Finance Aid | Planned |
| POS | `pos` | POS Aid | Planned |
| Pharmacy | `pharmacy` | Pharmacy Aid | Planned |
| Medical | `medical` | Medical Aid | Planned |

### Tier 3 — Industry Vertical Solutions

Compositions of Tier 2 modules plus their own industry-specific extensions. Verticals add **their own tables** but do not duplicate the modules. They consume modules through Corporate Connect.

Format: `{Industry} Aid`

| Vertical | Composed of | Plus its own |
|---|---|---|
| Farm Aid | Inventory + Finance + CRM + HR | Crops, livestock, harvests, seasons |
| Clinic Aid / Hospital Aid | CRM + HR + Pharmacy + Medical + Finance | Beds, wards, surgeries |
| Supermarket Aid | POS + Inventory + Finance + CRM | Aisles, shelf layouts, loyalty |
| Retailer Aid | POS + Inventory + CRM + HR | Stores, suppliers, distribution |
| Salon Aid | POS + CRM + Finance | Appointments, services, stylists |
| Restaurant Aid | POS + Inventory + HR + Finance | Tables, menus, orders |
| Accommodation Aid | POS + CRM + HR | Rooms, bookings, housekeeping |
| NGO Aid | CRM + HR + Finance | Donors, programs, beneficiaries |

### Tier 0 — The Integration Layer: Corporate Connect

Corporate Connect is a **customer-local integration layer**. It is not a vendor-operated service. It lives inside the customer's own instance and connects the modules the customer has licensed (Recruit Aid, HR Aid, CRM Aid, ...).

Corporate Connect owns:
- The event outbox (`core_integration_events`)
- Cross-module ID mappings (`core_integration_mappings`)
- Sync history and retry state
- Cross-module permissions

**What Corporate Connect is NOT:**
- It is not a SaaS product operated by Corporate Aid Solutions
- It does not phone home
- It does not require the customer to share data with the vendor
- It does not depend on any Corporate Aid service being online

Corporate Aid Solutions has **zero runtime involvement** in a customer's instance after the license key is issued. The software is theirs.

---

## 2. What Customers See

None of the internal naming above is exposed to end users. A customer running Recruit Aid on their own domain sees:

| Layer | What they see |
|---|---|
| Domain | Their own (`careers.example.co.ke`) |
| Logo | Their own (via branding module) |
| Product label | Their own ("Careers Portal" etc.) |
| URLs | `their-domain.com/recruit/vacancies` |
| Login footer | Optional "Powered by Corporate Aid Solutions" |

**"CorporateAid" never appears in a customer URL path.** The module prefix (`/recruit/`, `/hr/`, `/crm/`) appears so modules can coexist on one domain.

---

## 3. Database Table Naming

### Rule

```
{prefix}_{entity}
```

Where `{prefix}` is either `core` (shared platform capability) or one of the module prefixes from Section 1.

### Shared Tables (prefix: `core_`)

Owned by the platform. Any module may read them through their repositories. These are the platform's stable foundation.

```
core_users                  -- identity
core_roles                  -- RBAC
core_permissions            -- RBAC
core_role_permissions       -- RBAC
core_user_roles             -- RBAC
core_user_permissions       -- direct user → permission grants
core_system_settings        -- configuration
core_audit_logs             -- security audit trail
core_activities             -- business timeline
core_notifications          -- in-app notifications
core_notification_rules     -- notification routing
core_files                  -- uploaded file metadata
core_entity_files           -- file-to-entity links
core_custom_fields          -- custom field definitions
core_custom_field_values    -- custom field values
core_password_resets        -- password reset tokens
core_rate_limits            -- rate limiting counters
core_migrations             -- migration history
core_sequence_counters      -- monotonic ID sequences
core_modules                -- module registry (recruit, hr, crm, ...)
core_module_licenses        -- license keys for this install
core_module_state           -- which modules are enabled locally
core_integrations           -- external system connections (Server Manager)
core_integration_logs       -- integration attempt history
core_scheduled_tasks        -- cron registry
core_scheduled_task_runs    -- cron execution history
core_health_checks          -- periodic health snapshots
core_updates_available      -- updates fetched, not yet applied
core_updates_history        -- applied/skipped updates
core_update_manifest        -- cached update manifest
core_currencies             -- currency reference data
core_notes                  -- polymorphic notes (any entity)
core_feature_flags          -- feature toggle infrastructure
core_webhooks               -- outbound webhook endpoints
core_webhook_deliveries     -- webhook delivery log
core_email_templates        -- configurable email templates
core_data_requests          -- GDPR/DSAR request tracking
core_consent_log            -- consent given/withdrawn
core_login_history          -- login attempts, devices
core_workflow_definitions   -- workflow engine (tables only, engine deferred)
core_workflow_states        -- workflow states
core_workflow_transitions   -- allowed state transitions
```

### Module-Owned Tables

Each module owns its tables exclusively. Prefix matches the module prefix.

**Recruit Aid (`recruit_`):**

```
recruit_client_organisations
recruit_client_contacts
recruit_engagements
recruit_engagement_packages
recruit_engagement_recruiters
recruit_engagement_assignments
recruit_vacancies
recruit_vacancy_stages
recruit_vacancy_requirements
recruit_pipeline_stages
recruit_candidates                (planned)
recruit_applications              (planned)
recruit_application_stage_history (planned)
recruit_interviews                (planned)
recruit_offers                    (planned)
recruit_assessments               (planned)
recruit_reference_checks          (planned)
recruit_tasks                     (planned)
```

**HR Aid (`hr_`):** *(future)*

**CRM Aid (`crm_`):** *(future)*

### Ownership Rule (Non-Negotiable)

> **A module never reads or writes another module's tables directly.**
> All cross-module communication flows through Corporate Connect via the event outbox and ID mapping tables.

Example: `recruit` code must never JOIN `hr_employees`. When a candidate is hired, `recruit` emits a `recruit.candidate.hired` event. Corporate Connect routes it to HR. HR creates the employee and stores the mapping in `core_integration_mappings`.

### Module-Specific Organisations

Each module has its own concept of "organisation":

- **Recruit Aid**: `recruit_client_organisations` — the agency's customers (companies hiring).
- **HR Aid** (future): `hr_departments` — the customer's own internal structure.
- **CRM Aid** (future): `crm_accounts` — the customer's B2B accounts.

These are separate concepts with separate tables. **Never merge them.**

---

## 4. Foreign Key Naming

### Rule

```
fk_{child_module_short}_{child_entity_short}_{parent_entity_short}
```

Short forms for FK names only (to keep identifiers manageable):

| Module | Short form |
|---|---|
| core | `core` |
| recruit | `rec` |
| hr | `hr` |
| crm | `crm` |
| payroll | `pay` |
| farm | `farm` |
| clinic | `clinic` |

### Examples

- `fk_rec_eng_org` — `recruit_engagements.organisation_id` → `recruit_client_organisations.id`
- `fk_rec_ea_engagement` — `recruit_engagement_assignments.engagement_id` → `recruit_engagements.id`
- `fk_core_files_uploader` — `core_files.uploaded_by` → `core_users.id`
- `fk_core_ur_user` — `core_user_roles.user_id` → `core_users.id`

FK names appear in error messages and `SHOW CREATE TABLE`. Prefixing makes ownership obvious at a glance.

---

## 5. Sequence Counter Naming

### Rule

```
{module_short}_{entity}_code
```

Short forms permitted when the resulting name is clear.

### Examples

| Old | New |
|---|---|
| `organisation_code` | `rec_client_org_code` |
| `contact_code` | `rec_client_contact_code` |
| `engagement_code` | `rec_engagement_code` |
| `vacancy_code` | `rec_vacancy_code` |

**Preserving existing values:** counter values do not reset. Existing codes like `ENG-000001` remain on existing records. Only the counter key name changes.

---

## 6. Permission Slugs

### Rule

```
{module}.{entity}.{action}
```

Full module name (not abbreviated) for readability and searchability.

### Examples

```
recruit.organisations.view
recruit.organisations.create
recruit.organisations.edit
recruit.organisations.delete
recruit.engagements.view
recruit.vacancies.view
recruit.candidates.view

core.users.view
core.users.create
core.settings.manage
core.audit.view
```

**Rule for new modules:** every permission a module introduces must use that module's prefix. Cross-module permissions (e.g. "view HR employees from within Recruit Aid") live in `core.integrations.*`.

---

## 7. Route URL Naming

### Rule

Module-prefixed URLs prevent collisions when modules coexist on one domain.

```
/{module}/{resource}
```

### Recruit Aid

```
/recruit/organisations
/recruit/contacts
/recruit/engagements
/recruit/vacancies
/recruit/candidates
/recruit/applications
/recruit/interviews
/recruit/offers
```

### Future modules

```
/hr/employees
/hr/departments
/crm/deals
/crm/accounts
```

### Exceptions

- **Public job board** (Recruit Aid): `/jobs`, `/jobs/{slug}` — flat, because it's the public-facing site and shouldn't expose module structure.
- **Authentication** (`/login`, `/logout`, `/password/*`): shared across modules, stays flat.
- **Admin dashboard** (`/admin/*`): shared platform features stay flat.
- **Shared APIs** (`/api/notifications`, `/files/*`): stay flat.

---

## 8. Activity Action Strings

### Rule

```
{module}.{entity}.{verb}
```

Same shape as permission slugs and event names for consistency.

### Examples

| Old | New |
|---|---|
| `organisation.created` | `recruit.client_organisation.created` |
| `organisation.updated` | `recruit.client_organisation.updated` |
| `engagement.created` | `recruit.engagement.created` |
| `vacancy.created` | `recruit.vacancy.created` |
| `settings.changed` | `core.settings.changed` |
| `user.created` | `core.user.created` |
| `rate_limit.hit` | `core.rate_limit.hit` |

**Historical rows stay literal.** Existing activity records keep their old action strings. New activity logging uses the new format. The display layer handles both during a transition window (which, in a pre-launch project, is trivially short).

---

## 9. Event Names (Corporate Connect)

### Rule

```
{module}.{entity}.{verb}
```

Same format as activity actions for consistency. This is what appears in `core_integration_events.event_type` and in webhook subscriptions.

### Examples

```
recruit.candidate.created
recruit.candidate.hired
recruit.vacancy.created
recruit.offer.accepted

hr.employee.created
hr.employee.terminated

crm.deal.won
```

---

## 10. API Path Naming

### Rule

```
/api/v{version}/{module}/{resource}
```

Version always present. Module always present. This is what future-proofs breaking changes — v2 can coexist with v1 without path juggling.

### Examples

```
/api/v1/recruit/candidates
/api/v1/recruit/applications
/api/v1/hr/employees
/api/v1/crm/deals
```

---

## 11. PHP Namespace

### Rule

```
CorporateAid\{Root}\{Layer}\{Class}
```

Where `{Root}` is one of:

| Root | Purpose | Directory |
|---|---|---|
| `Core` | Framework — no product knowledge | `app/Core/` |
| `Platform` | Shared services, utilities, middleware | `app/Platform/` |
| `Recruit` | Recruit Aid module | `app/Recruit/` |
| `Helpers` | Global helper functions | `app/Helpers/` |

Future modules add their own root:

- `CorporateAid\HR\*` → `app/HR/`
- `CorporateAid\CRM\*` → `app/CRM/`

### Examples

```
CorporateAid\Core
CorporateAid\Core\Http
CorporateAid\Core\Exceptions

CorporateAid\Platform\Models
CorporateAid\Platform\Repositories
CorporateAid\Platform\Services
CorporateAid\Platform\Support
CorporateAid\Platform\Middleware
CorporateAid\Platform\Validation
CorporateAid\Platform\CustomFields
CorporateAid\Platform\CustomFields\InputTypes
CorporateAid\Platform\Deletion

CorporateAid\Recruit\Controllers
CorporateAid\Recruit\Controllers\Admin
CorporateAid\Recruit\Models
CorporateAid\Recruit\Repositories
CorporateAid\Recruit\Services
CorporateAid\Recruit\Validation
CorporateAid\Recruit\Deletion\Entities

CorporateAid\Helpers
```

### Dependency Rule

```
Core       → no dependencies on Platform or Recruit
Platform   → may depend on Core
Recruit    → may depend on Core and Platform
Helpers    → may depend on Core (loaded globally)
```

**Never** the reverse. Platform must never `use` from Recruit. Core must never `use` from either.

---

## 12. Directory Structure

The file system mirrors the namespace.

```
app/
    Core/                       -- CorporateAid\Core\*
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
            Kernel.php
        Exceptions/
            HttpException.php

    Platform/                   -- CorporateAid\Platform\*
        Support/
            Logger.php
            Hasher.php
            CodeGenerator.php
            RateLimiter.php
            EmailService.php
            ActivityLogger.php
            AuditLogger.php
            AuditContext.php
            PasswordPolicy.php
        Services/
            SystemSettingService.php
            NotificationService.php
            NotificationRuleService.php
            DashboardService.php
            FileService.php
            CustomFieldService.php
        Models/
            User.php
            Notification.php
            File.php
            EntityFile.php
            CustomField.php
            CustomFieldValue.php
        Repositories/
            UserRepository.php
            NotificationRepository.php
            FileRepository.php
            EntityFileRepository.php
            CustomFieldRepository.php
            CustomFieldValueRepository.php
            PasswordResetRepository.php
            RateLimitRepository.php
        Validation/
            FileValidator.php
            CustomFieldValidator.php
        Middleware/
            AuthMiddleware.php
            GuestMiddleware.php
            CsrfMiddleware.php
            PermissionMiddleware.php
            RateLimitMiddleware.php
            AuditLoggerMiddleware.php
        CustomFields/
            InputType.php
            InputTypeRegistry.php
            CustomFieldRenderer.php
            CustomFieldValueSaver.php
            CustomFieldValueValidator.php
            ValidationException.php
            InputTypes/
                (12 input type classes)
        Deletion/
            DeletableEntity.php
            ConfirmationPhrase.php
            DeletionResult.php
            DeletionService.php

    Recruit/                    -- CorporateAid\Recruit\*
        Controllers/
        Controllers/Admin/
        Models/
        Repositories/
        Services/
        Validation/
        Deletion/Entities/

    Helpers/                    -- CorporateAid\Helpers\*
        functions.php

    HR/                         -- future (CorporateAid\HR\*)
    CRM/                        -- future (CorporateAid\CRM\*)
```

**Rules:**

- Framework code lives in `Core/`
- Shared utilities, services, models, and middleware live in `Platform/`
- Module-specific code lives in `{Module}/`
- Global helper functions live in `Helpers/`
- New modules get their own top-level directory

---

## 13. Composer Package Name

```
corporateaid/recruit
corporateaid/hr           (future)
corporateaid/crm          (future)
corporateaid/core         (shared framework package — extracted later)
```

---

## 14. Config Keys

Format: `{module}.{key}` in config files when a key is module-specific.

Shared config keys stay flat:

```
// config/app.php
'app' => ['name' => 'Recruit Aid', 'timezone' => 'Africa/Nairobi'],

// future — module-specific
'recruit' => ['max_upload_mb' => 20],
'hr' => ['probation_days' => 90],
```

### Database Name (Deployment-Specific)

Each install gets its own database, named by the operator. There is no vendor-mandated naming scheme — the customer chooses.

**Convention for our own environments and examples:**
`corporateaid_{module}` — e.g. `corporateaid_recruit`, `corporateaid_hr`, `corporateaid_crm`.

**Current dev database:** `corporateaid_recruit`

**Rule:** the database name is per-install, not hardcoded. It lives in `config/local.php` (web app) and `config/cli.php` (CLI). The code never assumes a specific database name.

---

## 15. Session Keys

**Unchanged.** Session keys are private implementation details. They don't appear in URLs, logs, or any customer-visible surface. The convention does not apply.

If a future need arises, module-specific session keys may be prefixed `{module}.{key}`, but this is not required.

---

## 16. Migration File Naming

### Rule

```
{sequence}_{description}.sql
```

Sequence is zero-padded to 4 digits. Description is snake_case, brief, imperative.

Examples:

```
0001_create_core_tables.sql
0016_add_notification_digest_tracking.sql
0017_create_engagement_assignments.sql
```

**Migrations are immutable once shipped.** File names and content do not change after they've been applied to a shared environment (staging, production).

**Exception:** during the naming convention cycle (2026-09-24/25), migrations were rewritten in place because they had only ever been applied to local dev. Post-cycle, they are immutable.

**Post-cycle rule:** any change requires a new migration file. Never edit a shipped migration. If a migration was wrong, write a new one that corrects it.

---

## 17. What This Convention Does NOT Cover

- **Session keys** — internal, no convention
- **Cache keys** — no caching yet; convention will be established when the first cache is introduced
- **CSS class names** — design system has its own conventions
- **JavaScript module names** — minimal JS; will establish convention when it grows
- **Log message formats** — internal, no convention
- **Test file names** — will establish when automated tests are added
- **Third-party integration names** — will follow the external system's conventions where sensible
- **Column names** — columns don't need prefixes; the table name provides context
- **Index and unique key names** — grandfathered; see Section 21.6

---

## 18. Enforcement

Every new module, table, route, permission, event, and class added to the project must conform to this convention. Code review (or pre-commit check) that deviates must justify the deviation in writing.

**When in doubt, ask.** The cost of a name change later is exponentially higher than the cost of asking now.

---

## 19. Rename Execution — Historical Record

The naming convention was executed in a single session on **2026-09-25**. This section records what happened for future reference.

### What was renamed

| Layer | Before | After |
|---|---|---|
| Database tables | `users`, `organisations`, etc. | `core_users`, `recruit_client_organisations`, etc. |
| Foreign key names | `fk_ur_user`, `fk_eng_org`, etc. | `fk_core_ur_user`, `fk_rec_eng_org`, etc. |
| CHECK constraint names | `chk_eng_code_format`, etc. | `chk_rec_eng_code_format`, etc. |
| Sequence counter keys | `organisation_code`, etc. | `rec_client_org_code`, etc. |
| Permission slugs | `organisations.view`, etc. | `recruit.organisations.view`, etc. |
| PHP namespaces | `RecruitAid\*` | `CorporateAid\Core\*`, `CorporateAid\Platform\*`, `CorporateAid\Recruit\*`, `CorporateAid\Helpers\*` |
| Directory structure | `app/Controllers/`, `app/Models/`, etc. | `app/Core/`, `app/Platform/`, `app/Recruit/`, `app/Helpers/` |
| Composer package | `recruitaid/ats` | `corporateaid/recruit` |
| Database name | `recruitaid` | `corporateaid_recruit` |

### What was NOT renamed

- Column names (columns don't need prefixes; the table name provides context)
- Index and unique key names (grandfathered — see Section 21.6)
- Session keys (internal, not customer-visible)
- Route URLs for auth, admin, dashboard, files, notifications (`/login`, `/admin/*`, etc. stay flat)

### Verification

Every renamed layer was verified with `grep` + `php -l` + smoke tests. The full execution record is preserved in `docs/RENAME-RECORD.md`.

### Rollback

The pre-rename snapshot is preserved at:
`~/recruitaid-backups/recruitaid-milestone13-fresh-prerename-*.tar.gz`

Post-rename milestone:
`~/recruitaid-backups/recruitaid-milestone14-naming-complete-*.tar.gz`

---

## 20. References

- `docs/ARCHITECTURE.md` — how the layers fit together in code
- `docs/SPECIFICATION.md` — the ADRs that produced these rules
- `docs/RENAME-RECORD.md` — historical execution of the rename cycle
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/REMEMBER.md` — current session state

---

## 21. Short Forms Reference

Foreign key names, CHECK constraint names, and any internal identifier that needs to be brief use a standardized short-form vocabulary. This table is the authoritative source. **Any new module must extend this table before introducing new short forms.**

### 21.1 Module Short Forms

| Module | Short form |
|---|---|
| Core (shared) | `core` |
| Recruit Aid | `rec` |
| HR Aid | `hr` |
| CRM Aid | `crm` |
| Payroll Aid | `pay` |
| Leave Aid | `leave` |
| Performance Aid | `perf` |
| Task Aid | `task` |
| Inventory Aid | `inv` |
| Finance Aid | `fin` |
| POS Aid | `pos` |
| Pharmacy Aid | `pharm` |
| Medical Aid | `med` |
| Farm Aid | `farm` |
| Clinic Aid | `clinic` |
| Supermarket Aid | `smkt` |
| Retailer Aid | `retail` |
| Salon Aid | `salon` |
| Restaurant Aid | `resto` |
| Accommodation Aid | `accom` |
| NGO Aid | `ngo` |

### 21.2 Entity Short Forms (Recruit Aid)

| Entity | Table | Short form |
|---|---|---|
| Client Organisation | `recruit_client_organisations` | `org` |
| Client Contact | `recruit_client_contacts` | `contact` |
| Engagement | `recruit_engagements` | `eng` |
| Engagement Package | `recruit_engagement_packages` | `engpkg` |
| Engagement Recruiter | `recruit_engagement_recruiters` | `engrec` |
| Engagement Assignment | `recruit_engagement_assignments` | `ea` |
| Vacancy | `recruit_vacancies` | `vac` |
| Vacancy Stage | `recruit_vacancy_stages` | `vs` |
| Vacancy Requirement | `recruit_vacancy_requirements` | `vr` |
| Pipeline Stage | `recruit_pipeline_stages` | `ps` |
| Candidate (future) | `recruit_candidates` | `cand` |
| Application (future) | `recruit_applications` | `app` |
| Interview (future) | `recruit_interviews` | `intv` |
| Offer (future) | `recruit_offers` | `offer` |
| Assessment (future) | `recruit_assessments` | `assess` |

### 21.3 Entity Short Forms (Core)

| Entity | Table | Short form |
|---|---|---|
| User | `core_users` | `user` |
| Role | `core_roles` | `role` |
| Permission | `core_permissions` | `perm` |
| User Role | `core_user_roles` | `ur` |
| Role Permission | `core_role_permissions` | `rp` |
| User Permission | `core_user_permissions` | `up` |
| Activity | `core_activities` | `act` |
| Audit Log | `core_audit_logs` | `al` |
| Audit Log (legacy) | `core_audit_logs_legacy` | `all` |
| Notification | `core_notifications` | `notif` |
| Notification Rule | `core_notification_rules` | `nr` |
| File | `core_files` | `file` |
| Entity File | `core_entity_files` | `ef` |
| Custom Field | `core_custom_fields` | `cf` |
| Custom Field Value | `core_custom_field_values` | `cfv` |
| Module | `core_modules` | `module` |
| Module License | `core_module_licenses` | `ml` |
| Module State | `core_module_state` | `ms` |
| Integration | `core_integrations` | `integration` |
| Integration Log | `core_integration_logs` | `il` |
| Scheduled Task | `core_scheduled_tasks` | `st` |
| Scheduled Task Run | `core_scheduled_task_runs` | `str` |
| Update (available) | `core_updates_available` | `ua` |
| Update (history) | `core_updates_history` | `uh` |
| Currency | `core_currencies` | `currency` |
| Note | `core_notes` | `note` |
| Feature Flag | `core_feature_flags` | `flag` |
| Webhook | `core_webhooks` | `wh` |
| Webhook Delivery | `core_webhook_deliveries` | `wd` |
| Email Template | `core_email_templates` | `et` |
| Data Request | `core_data_requests` | `dr` |
| Consent Log | `core_consent_log` | `cl` |
| Login History | `core_login_history` | `lh` |
| Sequence Counter | `core_sequence_counters` | `seq` |
| System Setting | `core_system_settings` | `setting` |
| Password Reset | `core_password_resets` | `pr` |
| Rate Limit | `core_rate_limits` | `rl` |
| Migration | `core_migrations` | `mig` |
| Workflow Definition | `core_workflow_definitions` | `wf` |
| Workflow State | `core_workflow_states` | `ws` |
| Workflow Transition | `core_workflow_transitions` | `wt` |

### 21.4 Naming Patterns

**Foreign Key:**

```
fk_{child_module_short}_{child_entity_short}_{parent_entity_short}
```

**CHECK Constraint:**

```
chk_{module_short}_{entity_short}_{purpose}
```

**Index:**

```
idx_{module_short}_{entity_short}_{purpose}
```

**Unique Key:**

```
uniq_{module_short}_{entity_short}_{purpose}
```

### 21.5 Adding a New Short Form

Before introducing a new short form:

1. Check this document — the entity may already have a short form.
2. If new, propose it here. Follow these rules:
   - 2-6 characters
   - Lowercase letters only (no underscores)
   - Abbreviation should be unambiguous within its module
   - If two entities in the same module would collide, disambiguate
3. Update this document as part of the same commit/change that introduces the short form.

**Never invent a short form inline.** The table above is the source of truth.

### 21.6 Index and Unique Key Naming — Applied to New Indexes Only

**Decision (2026-09-24):** Existing index and unique key names are **grandfathered** and are NOT renamed during the rename cycle. New indexes and unique keys introduced from this point forward **must** follow the convention:

```
idx_{module_short}_{entity_short}_{purpose}
uniq_{module_short}_{entity_short}_{purpose}
```

**Reasoning:**

- Multiple tables in the current schema share the same index name (six tables have `idx_user`, three have `idx_status`). A global rename would incorrectly assign ownership.
- A correct rename requires a `(table, index_name)` tuple map, which is error-prone to extract from SQL files with automated tooling.
- Index names are developer-facing only. They appear in `EXPLAIN`, `SHOW CREATE TABLE`, and slow-query logs. They never appear in application error messages, user-visible output, or API responses.
- The cost of a proper rename exceeds the benefit.

**How this plays out:**

- Old index names remain as-is: `idx_email`, `idx_status`, `idx_user`, etc.
- When an index is modified (columns added, uniqueness changed) or a new table is introduced, the new index name uses the convention.
- Over time, index names migrate to the convention naturally.

**Rule for new tables:** every index and unique key on a new table MUST follow the convention. This is not optional for new code.

**Rule for existing tables:** index renames happen opportunistically when a table is refactored. They do not happen as a standalone pass.