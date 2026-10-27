# Rename Record — The Naming Convention Cycle

**Executed:** 2026-09-25
**Scope:** Complete rename of the codebase to the Corporate Aid naming convention
**Status:** Complete, verified, in production
**Author:** Sely + AI pair

---

**See also:**
- `docs/NAMING-CONVENTION.md` — the specification this record implements
- `docs/ARCHITECTURE.md` — the resulting three-tier structure
- `docs/SPECIFICATION.md` — the ADRs that produced these rules
- `docs/REMEMBER.md` — current session state

---

## 1. Purpose of This Document

This is a **historical record**, not a plan. The rename cycle was
executed in a single session on 2026-09-25. It converted the entire
codebase from ad-hoc naming to the Corporate Aid naming convention.

Future developers (and future AI sessions) can read this to understand
what changed, why, and how the codebase arrived at its current shape.

---

## 2. Why the Rename Was Done

### The Problem

Before the rename, the codebase used:
- Flat namespace: `RecruitAid\*`
- Flat directory structure: `app/Controllers/`, `app/Models/`, etc.
- Unprefixed table names: `users`, `organisations`, `engagements`
- Unprefixed permission slugs: `organisations.view`
- Unprefixed counter keys: `organisation_code`
- Single-product focus with no room for HR, CRM, or other modules

### Why This Became a Problem

The product was about to grow. HR Aid, CRM Aid, and other modules were
on the roadmap. Without clear boundaries:
- Table names would collide across modules
- Namespace references would become ambiguous
- Every new module would require "which class is whose?" decisions

### The Decision

Adopt a comprehensive naming convention (see
`docs/NAMING-CONVENTION.md`) covering every layer: database tables,
foreign keys, CHECK constraints, indexes, PHP namespaces, directory
structure, permission slugs, event names, API paths, and config keys.

**The critical insight:** Since no customers existed, no production data
existed, and no external integrations existed, the rename could be done
in place with zero migration risk. Waiting until customers existed would
have made the same operation exponentially more expensive.

**The human context:** The decision was made explicitly to prevent
future rewrites with customers already onboard. Doing it while the
codebase was small (129 PHP files, 23 migrations) cost one session.
Doing it later, with 800+ files and multiple modules, would cost weeks.

---

## 3. What Was Renamed

### Database Tables

All tables received a prefix:

- **Shared tables** → `core_*` prefix
  - `users` → `core_users`
  - `roles` → `core_roles`
  - `permissions` → `core_permissions`
  - `role_permissions` → `core_role_permissions`
  - `user_roles` → `core_user_roles`
  - `system_settings` → `core_system_settings`
  - `audit_logs` → `core_audit_logs`
  - `activities` → `core_activities`
  - `notifications` → `core_notifications`
  - `notification_rules` → `core_notification_rules`
  - `files` → `core_files`
  - `entity_files` → `core_entity_files`
  - `custom_fields` → `core_custom_fields`
  - `custom_field_values` → `core_custom_field_values`
  - `password_resets` → `core_password_resets`
  - `rate_limits` → `core_rate_limits`
  - `migrations` → `core_migrations`
  - `sequence_counters` → `core_sequence_counters`

- **Recruit Aid tables** → `recruit_*` prefix
  - `organisations` → `recruit_client_organisations`
  - `organisation_contacts` → `recruit_client_contacts`
  - `engagements` → `recruit_engagements`
  - `engagement_packages` → `recruit_engagement_packages`
  - `engagement_recruiters` → `recruit_engagement_recruiters`
  - `engagement_assignments` → `recruit_engagement_assignments`
  - `vacancies` → `recruit_vacancies`
  - `vacancy_stages` → `recruit_vacancy_stages`
  - `vacancy_requirements` → `recruit_vacancy_requirements`
  - `pipeline_stages` → `recruit_pipeline_stages`

### Foreign Key Names

Every FK renamed to `fk_{module_short}_{child}_{parent}`:

- `fk_ur_user` → `fk_core_ur_user`
- `fk_rp_role` → `fk_core_rp_role`
- `fk_eng_org` → `fk_rec_eng_org`
- `fk_vac_engagement` → `fk_rec_vac_engagement`
- ... (approximately 32 FK renames)

### CHECK Constraint Names

Every CHECK renamed to `chk_{module_short}_{entity}_{purpose}`:

- `chk_eng_code_format` → `chk_rec_eng_code_format`
- `chk_eng_capacity` → `chk_rec_eng_capacity`
- `chk_vac_code_format` → `chk_rec_vac_code_format`
- ... (8 CHECK constraint renames)

### Sequence Counter Keys

- `organisation_code` → `rec_client_org_code`
- `contact_code` → `rec_client_contact_code`
- `engagement_code` → `rec_engagement_code`
- `vacancy_code` → `rec_vacancy_code`

### Permission Slugs

All slugs received a module prefix:

- `organisations.view` → `recruit.organisations.view`
- `engagements.view` → `recruit.engagements.view`
- `vacancies.view` → `recruit.vacancies.view`
- `users.view` → `core.users.view`
- `settings.manage` → `core.settings.manage`
- `audit.view` → `core.audit.view`
- ... (approximately 45 permission slugs)

### PHP Namespaces

Three-tier namespace structure adopted:

- `RecruitAid\Core\*` → `CorporateAid\Core\*`
- `RecruitAid\Http\*` → `CorporateAid\Core\Http\*`
- `RecruitAid\Support\CustomFields\*` → `CorporateAid\Platform\CustomFields\*`
- `RecruitAid\Support\Deletion\*` → `CorporateAid\Platform\Deletion\*`
- `RecruitAid\Support\*` → `CorporateAid\Platform\Support\*`
- `RecruitAid\Middleware\*` → `CorporateAid\Platform\Middleware\*`
- `RecruitAid\Services\*` → split into `CorporateAid\Platform\Services\*`
  and `CorporateAid\Recruit\Services\*`
- `RecruitAid\Models\*` → split into `CorporateAid\Platform\Models\*`
  and `CorporateAid\Recruit\Models\*`
- `RecruitAid\Repositories\*` → split into
  `CorporateAid\Platform\Repositories\*` and
  `CorporateAid\Recruit\Repositories\*`
- `RecruitAid\Validation\*` → split into
  `CorporateAid\Platform\Validation\*` and
  `CorporateAid\Recruit\Validation\*`
- `RecruitAid\Controllers\*` → `CorporateAid\Recruit\Controllers\*`
- `RecruitAid\Helpers\*` → `CorporateAid\Helpers\*`

### Directory Structure

Files moved to mirror the namespace:

```
app/Controllers/       → app/Recruit/Controllers/
app/Controllers/Admin/ → app/Recruit/Controllers/Admin/
app/Models/            → split: app/Platform/Models/ + app/Recruit/Models/
app/Repositories/      → split: app/Platform/Repositories/ + app/Recruit/Repositories/
app/Services/          → split: app/Platform/Services/ + app/Recruit/Services/
app/Validation/        → split: app/Platform/Validation/ + app/Recruit/Validation/
app/Support/           → split: app/Platform/Support/ + app/Platform/CustomFields/ + app/Platform/Deletion/
app/Middleware/        → app/Platform/Middleware/
app/Http/Kernel.php    → app/Core/Http/Kernel.php
```

### Composer Package

- `recruitaid/ats` → `corporateaid/recruit`

### Database Name

- `recruitaid` → `corporateaid_recruit`

---

## 4. What Was NOT Renamed

- **Column names** — columns don't need prefixes; the table name
  provides context. `core_users.email` is clear as-is.
- **Index and unique key names** — grandfathered. See
  `docs/NAMING-CONVENTION.md` §21.6. Six tables share `idx_user`;
  a global rename would be wrong.
- **Session keys** — internal, not customer-visible. `_flash`, `_old`,
  `_reauth_at` stay as-is.
- **Route URLs for shared surfaces** — `/login`, `/admin/*`,
  `/dashboard`, `/files/*`, `/api/notifications` stay flat. Only
  module routes get prefixes.
- **Asset file paths** — `public/assets/css/app.css`, `public/assets/js/*.js`
  unchanged. CSS classes are a design system concern, not a module concern.
- **Existing backup files** — preserved untouched as historical records.

---

## 5. Execution Sequence

The rename ran in this order. Each step was verified before the next.

| # | Step | Outcome |
|---|---|---|
| 0 | Pre-rename backup (tarball + DB dump) | Frozen; rollback guaranteed |
| 1 | Write `docs/NAMING-CONVENTION.md` | The specification |
| 2 | Write `docs/RENAME-MANIFEST.md` | The plan |
| 3 | Rewrite migration files in place | 25 files; table names, FK names, CHECK names, counter keys |
| 4 | Rewrite code SQL strings | 242 replacements across 33 files |
| 5 | Rewrite namespaces | ~800 `use` statements updated across ~130 files |
| 6 | Move files to new directory structure | 104 files moved, 25 in place |
| 7 | Update `composer.json` autoload | 4 PSR-4 roots (Core, Platform, Recruit, Helpers) |
| 8 | Fix reference errors | 42 misplaced `use` statements corrected |
| 9 | Update permission slugs | 70 route middleware references updated |
| 10 | Rename database + drop | `recruitaid` → `corporateaid_recruit`; dropped and recreated |
| 11 | Run fresh migrations | 25 migrations applied cleanly |
| 12 | Run seeders | 5 seeders applied (roles, permissions, currencies, modules, admin user) |
| 13 | Fix migration 0008 (counter table) | Table now created by the migration that uses it |
| 14 | Clean stale counter rows | Migration 0026 deleted obsolete keys |
| 15 | Smoke test | All built pages load; all forms save; no exceptions |
| 16 | Milestone 14 backup | New snapshot with new naming |

**Rollback at any step** was possible by restoring the pre-rename
tarball: `~/recruitaid-backups/recruitaid-milestone13-fresh-prerename-*.tar.gz`.

---

## 6. Files Affected

| Category | File count |
|---|---|
| Migration files (`database/migrations/*.sql`) | 25 |
| Controllers | ~15 |
| Models | ~14 |
| Repositories | ~16 |
| Services | ~11 |
| Validators | ~6 |
| Core framework files | ~13 |
| Middleware | ~6 |
| Support utilities | ~20 |
| Custom fields engine | ~17 |
| Deletion framework | ~8 |
| Routes | 2 |
| Views | ~50 (form action URLs updated) |
| Config | 3 |
| `composer.json` | 1 |
| `public/index.php` | 1 |
| **Total** | **~200 files touched** |

---

## 7. Problems Encountered and Fixed

| # | Problem | Fix |
|---|---|---|
| 1 | Migration 0008 referenced `core_sequence_counters` but no migration created it | Added `CREATE TABLE` to migration 0008 |
| 2 | Stale counter rows existed from old migrations | Migration 0026 deletes obsolete keys |
| 3 | Namespace rewrite missed `RecruitAid\Http` and `RecruitAid\Services` mappings | Added missing entries; ran location-aware second pass |
| 4 | 42 `use` statements referenced Platform classes that were actually Recruit classes | Full-codebase sweep; replaced with correct namespaces |
| 5 | Migrations 0009/0010/0011 still inserted old counter keys | Updated to new keys |
| 6 | `Migration 0020` was a no-op (table existed under old name) | Resolved by migration 0021 (rename + recreate) |
| 7 | The `sessions` table existed with no creating migration | Deleted; app uses native PHP sessions |

---

## 8. Verification

Every layer was verified:

### Grep verification

```bash
# No old table names in migrations
grep -rn '`users`\|`organisations`\|`engagements`' database/migrations/   # 0 hits

# No old namespaces
grep -rn 'RecruitAid\' app/                                              # 0 hits

# No old permission slugs
grep -rE "'(organisations|engagements|vacancies|users)\.[a-z]+'" app/ routes/  # 0 hits

# No old counter keys
grep -rn "'contact_code'\|'organisation_code'" app/                      # 0 hits (except form field names, which are user-supplied labels)
```

### Lint verification

- `php -l` on every file in `app/`, `routes/`, `config/`, `public/`,
  `bin/` — all clean.
- `composer dump-autoload` — 346 classes, no errors.

### Runtime verification

- Framework boots: `CorporateAid\Core\App::boot()` returns an instance
- Container resolves all three tiers: Core, Platform, Recruit
- Every built page loads
- Every form saves
- Audit logging fires
- Notifications work
- Re-auth flow works
- Admin user logs in
- All seeders populated correctly

### Schema verification

- 53 tables, all prefixed
- All foreign keys resolve
- All CHECK constraints applied
- All sequence counters seeded with new keys
- All permission slugs prefixed

---

## 9. Post-Rename State

### What Changed

| Layer | Before | After |
|---|---|---|
| Namespace root | `RecruitAid\*` | `CorporateAid\Core\*`, `CorporateAid\Platform\*`, `CorporateAid\Recruit\*`, `CorporateAid\Helpers\*` |
| Table prefix | none | `core_*` or `recruit_*` |
| Directory structure | flat | three-tier (Core / Platform / Recruit) |
| Permission slugs | unprefixed | `core.*` / `recruit.*` |
| Counter keys | unprefixed | `rec_*` short form |
| Database | `recruitaid` | `corporateaid_recruit` |
| Composer package | `recruitaid/ats` | `corporateaid/recruit` |

### What Stayed the Same

- Column names
- Session keys
- CSS classes
- Asset file paths
- Auth / admin / dashboard / files / notifications route URLs

### Known Gaps

- Index and unique key names grandfathered (Section 21.6 of the
  convention doc)
- The route file split into `routes/recruit.php` etc. is planned but
  not done — currently all routes are in `web.php` and `auth.php`
- Three "public" placeholder routes for admin features not yet built
  (`/admin/audit`, `/admin/branding`, `/admin/email-templates`) return
  404s

---

## 10. Rollback and Recovery

### Pre-rename snapshot

```
~/recruitaid-backups/recruitaid-milestone13-fresh-prerename-20260924-212503.tar.gz
```

### Post-rename snapshot

```
~/recruitaid-backups/recruitaid-milestone14-naming-complete-20260925-081155.tar.gz
```

### Database dumps

- Pre-rename: `~/recruitaid-backups/db/recruitaid-schema-prerename.sql`
- Post-rename: `~/recruitaid-backups/db/corporateaid-recruit-schema-milestone14.sql`

### Restore command

```bash
cd /mnt/c/projects/recruitaid
tar -xzf ~/recruitaid-backups/recruitaid-milestone14-naming-complete-*.tar.gz
# then restore the DB from the schema dump
```

---

## 11. Lessons Learned

These lessons were added to the doctrine list in `docs/REMEMBER.md`:

1. **Migrations and seeders drift separately.** Both need the same
   rename treatment in the same pass. We missed the seeders on the
   first pass.

2. **A namespace rewrite must be location-aware, not pattern-based.**
   A blanket `RecruitAid\Repositories` → `CorporateAid\Platform\Repositories`
   mapping was wrong for Recruit-owned classes. The fix was to derive
   the target namespace from the file's actual directory.

3. **`use` statements referencing classes in other tiers need a
   second pass.** A single-pass rewrite misses cross-module references
   (e.g. Platform services using Recruit repositories).

4. **Regex escape sequences are fragile.** Use string-based
   replacement with manual boundary checks, not `re.escape()` inside
   complex patterns.

5. **Always test the fresh-install path.** Migrations that pass on an
   existing DB can fail on an empty one (migration 0008 proved this).

6. **The database itself is disposable during pre-launch.** A drop-and-
   rebuild is faster and cleaner than incremental migrations. Once
   customers exist, this option is gone.

7. **Doctrine rules survive renaming.** The 12+ rules we accumulated
   (full-file rewrites, no hand edits, command context, etc.) applied
   throughout the rename cycle without modification. That's the sign
   of well-designed rules.

---

## 12. References

- `docs/NAMING-CONVENTION.md` — the specification
- `docs/ARCHITECTURE.md` — the resulting architecture
- `docs/SPECIFICATION.md` — the ADRs
- `docs/CHANGELOG-middleware.md` — the onion middleware migration
- `docs/REMEMBER.md` — current session state