# CAS Suit — RBAC Architecture

**Status:** Shipped. Four-axis model live. Doctrine §14 is the current reference.
**Last revised:** 2026-11-09 (post-0067)

**See also:**
- `docs/PERMISSIONS.md` — practical guide to the six RBAC tables
- `docs/RBAC-TESTING.md` — acceptance-criteria-to-test map
- `docs/CAS-PLATFORM-SURFACE.md` — the /cas/* platform surface and support delegations

---

## 1. The One-Sentence Principle

> CAS Suit owns the security foundation. Individual applications own
> their operational permissions. Administrators receive explicitly
> delegated authority. All permission decisions are enforced centrally.

---

## 2. The Three Layers of Permission

| Layer | Question it answers | Who controls it |
|---|---|---|
| Ownership | Which application defines this permission? | Namespace prefix |
| Administration | Which administrator can assign it? | Admin's own scope |
| Enforcement | Can this user perform this action? | Central AuthorizationService |

---

## 3. Permission Namespace Ownership

Every permission slug follows: `{namespace}.{resource}.{action}`

| Namespace | Owner | Registrar |
|---|---|---|
| `cas.*` | CAS Core | Migration (protected) |
| `core.*` | CAS Core | Migration (protected) |
| `recruit.*` | Recruit Aid | `Module.php` |
| `hr.*` | HR Aid | `Module.php` |

**Rule:** An application may register permissions only under its own namespace. `cas.*` and `core.*` are protected.

---

## 4. The Four Independent Dimensions

Every authorization decision combines four **independent** dimensions:

| Dimension | What it governs | Storage |
|---|---|---|
| **Home tenant** | Login identity; whose data the user natively owns | `core_users.tenant_id NOT NULL` |
| **Platform authority** | `cas.*` operations across the whole suite | `core_user_platform_roles` |
| **Tenant authority** | `recruit.*`, `core.*` operations inside a tenant | `core_user_roles` + `core_user_permissions` + `core_permission_policies` |
| **Developer cross-tenant access** | Explicit, audited access to any tenant's data | `core_support_delegations` + `platform_owners` + activation event |

Each dimension is evaluated independently. **No dimension grants another.**

- Platform authority does not grant tenant authority.
- Tenant authority does not grant platform authority.
- Developer cross-tenant access is an activation event, not a role.

### 4.1 Platform authority: cas_admin vs. cas_developer

- **cas_admin** — operational platform administrator. Holds `cas.*`
  permissions. Can grant `tenant_admin` delegations, request
  revocations, context-switch (with step-up re-auth).
- **cas_developer** — the contractual platform owner, designated in
  `platform_owners`. The only party who can grant `cas_developer`
  delegations or cancel a pending tenant revocation. Ownership is
  bootstrapped via DB/CLI, never assigned through the web app.

### 4.2 Administrative scopes

- **Global CAS admin** — holds `cas.*` permissions via platform role.
- **Application admin (delegated)** — holds a support delegation record
  in `core_support_delegations`. Scoped to one tenant and one module,
  expiry-bounded, auditable, non-escalating.
- **Regular user** — holds roles scoped to specific applications.

---

## 5. Permission Evaluation (Enforcement)

The central `AuthorizationService` answers all "can this user do X?".

### 5.1 The actual precedence chain

The current implementation of `AuthorizationService::allows()`:

1. **Rule 1** — `user === null` → DENY.
2. **Rule 2** — permission unknown or inactive → DENY.
3. **Rule 3** — `cas.*` short-circuit: evaluate via
   `hasPlatformPermission(user.id, permission)`. Tenant identity is
   **never checked** for platform permissions.
4. **Rule 4** — no active tenant context (`tenantId <= 0`) → DENY.
5. **Rule 4a** — `resolveApplication()` returns `invalid` → DENY
   (fail closed on unknown ownership).
6. **Rule 4b** — owning module disabled → DENY.
7. **Rule 5** — policy deny for this tenant → DENY.
8. **Rule 6** — application assignment mandatory for app-scoped
   permissions → DENY if not assigned.
9. **Branch: identity vs. cross-tenant delegation.**
   - **Identity path** (user's home tenant === active tenant):
     - **Rule 7** — explicit user denial → DENY.
     - **Rule 8** — explicit user grant → ALLOW.
     - **Rule 9** — role grant via `permissionSlugs` → ALLOW.
   - **Cross-tenant path** (home tenant ≠ active tenant):
     - `activeFor(user, activeTenant, module)` must return an active
       support delegation.
     - `permits(delegation, permission)` must return true.
     - This path is **delegation-only** — no home-tenant grants, roles,
       or explicit denials leak into the delegated tenant's decision.
10. **Rule 11** — default → DENY.

The earlier "Rule 10 (super admin bypass)" from the pre-migration
doctrine is **removed**. The super_admin role was deleted by migration
0061; its function is split between the `cas.*` platform path (Rule 3)
and the platform owner distinction (`platform_owners`).

---

## 6. Delegation Model

Two delegation kinds coexist:

### 6.1 Admin delegations (`core_admin_delegations`)
Explicit grants of app-admin powers within a tenant. Columns include
`allowed_permissions_json` and `denied_permissions_json`. Consulted by
`AuthorizationService::getActiveDelegations()` — this is a general
admin authority model, distinct from support delegations.

### 6.2 Support delegations (`core_support_delegations`)
Explicit grants of cross-tenant business-data access. See
`docs/CAS-PLATFORM-SURFACE.md` for the full lifecycle.

- **Grantor types:** `tenant_admin` (grants within their own tenant) or
  `cas_developer` (grants cross-tenant support).
- **Scope ceiling:** `read_only`, `read_write`, or `full`. Materialized
  into `allowed_permissions_json` at grant time.
- **Explicit denial** (`denied_permissions_json`) evaluated before
  allowlist membership.
- **`cas.*` cannot be granted** via delegation — rejected at grant time.
- **Asymmetric revocation:**
  - `cas_developer` grants revoke immediately.
  - `tenant_admin` grants enter `revocation_pending` with a 24-hour
    cooling-off window, during which only the developer can cancel.

---

## 7. Role Model

- **Tenant-scoped roles:** `core_roles` has `tenant_id NOT NULL`. A
  role belongs to exactly one tenant. Composite FKs enforce that a
  `core_user_roles` assignment and its role share the same tenant.
- **Platform-scoped roles:** `core_platform_roles` (cas_admin,
  cas_developer). No tenant_id. Assigned via
  `core_user_platform_roles`.
- **No inheritance** in v1.

---

## 8. Permission Registration

Apps register via `getPermissionDefinitions()`. Registration does not
auto-activate; a platform admin must activate new permissions before
they become assignable.

---

## 9. Audit

All RBAC mutations write to `core_rbac_audit_logs`. Append-only.

### 9.1 The table

Created by migration `0046b_fix_rbac_schema`. Columns:

| Column | Purpose |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | INT NOT NULL — the tenant the action affects |
| `actor_id` | INT NULL — the user who performed the action |
| `action` | VARCHAR(80) NOT NULL — event name |
| `target_type` | VARCHAR(40) NULL — what was acted on |
| `target_id` | INT NULL — the target row id |
| `module_key` | VARCHAR(40) NULL — module the action applies to |
| `before_json` | JSON NULL — state before |
| `after_json` | JSON NULL — state after |
| `ip_address` | VARCHAR(45) NULL |
| `user_agent` | VARCHAR(255) NULL |
| `created_at` | DATETIME |

### 9.2 The logger

`app/Platform/Support/RbacAuditLogger.php` — writes to the table.
Append-only is application-level (no DB triggers): the logger never
issues UPDATE or DELETE. Failures are swallowed with a warning —
matching `AuditLogger` — so a broken audit insert cannot break a
user-facing action.

### 9.3 What gets audited

- `context.switch` — effective tenant change (both `switch()` and
  `confirm()` paths in `CasContextController`)
- `context.reset` — return to home tenant
- `delegation.grant` — support delegation created
- `delegation.revoke` — support delegation revoked (asymmetric path)
- `delegation.cancel` — pending revocation cancelled

---

## 10. Threat Model

Privilege escalation paths closed:

- **Namespace enforcement:** apps cannot register `cas.*` or `core.*`.
- **Self-grant rejection:** app admin cannot assign a role to themselves.
- **Module ownership:** app admin cannot edit roles outside their module.
- **Cross-tenant role reference:** prevented by composite FKs.
- **Delegation scope enforcement:** read-only delegations cannot write.
- **`cas.*` cannot be delegated:** rejected at grant time.
- **Super admin bypass removed:** every access requires an actual grant
  path, not a role name.
- **Step-up re-auth on context switch:** prevents session-hijack tenant
  hopping.
- **Platform owner designation:** only the contractual developer can
  grant `cas_developer` delegations.
- **UI hiding is not security:** every route has a permission middleware.

---

## 11. Current State

All four implementation batches from the original 33.1–33.4 roadmap are
shipped:

| Batch | Scope | Status |
|---|---|---|
| 33.1 | Schema + backfill (migrations 0038–0067) | ✅ done |
| 33.2 | AuthorizationService + rewiring | ✅ done |
| 33.3 | Delegation + audit | ✅ done |
| 33.4 | Platform surface UI (`/cas/*`) | ✅ done |

**Verified state:** 31/31 PHPUnit tests, 24/24 CLI authorization
assertions, 0 skips.

---

## 12. Non-Goals

- Break-glass access (deferred)
- Role inheritance (deferred)
- Field-level enforcement (v2)

---

## 13. Breaking Changes from Pre-Migration

- **`super_admin` role is deleted** (migration 0061). Any integration
  checking that role slug must migrate to `cas.*` permission checks.
- **`core_roles`, `core_user_roles`, `core_user_permissions`,
  `core_notification_rules`, `core_notifications`** all gained
  `tenant_id NOT NULL` with composite FKs. Cross-tenant role
  assignment is no longer possible.
- **`AuthorizationService` Rule 10 (super_admin bypass) is removed.**
- **`User::isSuperAdmin()` is removed.** Call sites replaced with
  `can('core.settings.manage')` or `hasRole('admin')`.

---

## 14. Doctrine: Confirmation

This section states the invariants the four-axis model enforces. Every
invariant is backed by a test — see `docs/RBAC-TESTING.md`.

### 14.1 The four dimensions
See §4.

### 14.2 The precedence chain
See §5.1.

### 14.3 The resource-gating invariant

> **Platform permissions are scope-limited, not gateway permissions.**
>
> `cas.dashboard.access` grants the dashboard. It does **not** grant
> every resource displayed on that dashboard. Every resource reached
> from the dashboard must independently satisfy its own permission gate
> on its own route.
>
> Corollary: a `cas_admin` with no tenant role may see aggregated counts
> on the dashboard and be denied every per-record view behind a tile.
> That is the intended behaviour.

This invariant is enforced by `PlatformSurfaceTest::RG-01`
(runtime assertion) and `RG-02` (route-declaration guard).

### 14.4 Test-coverage map
See `docs/RBAC-TESTING.md`.

### 14.5 Deferred work

All originally-deferred security work is now done:

- ✅ Step-up re-auth on context switch (`CasContextController`)
- ✅ Audit trail for RBAC mutations (`RbacAuditLogger`)
- ✅ `platform_owners` contractual-developer designation

The remaining work under this doctrine is **operational**, not
security:

- `/cas/tenants` CRUD — tenant lifecycle management UI.
- `cas_analytics_*` tables — pre-aggregated metrics for performance at
  scale.
- **Designation flow for `platform_owners`** — the table is written
  manually today. A future signed CLI command or admin-only direct-DB
  operation would formalize it. Not a security gap; the enforcement
  works.
