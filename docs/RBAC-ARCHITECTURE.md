# CAS Suit — RBAC Architecture

**Status:** Design approved. Implementation: batches 33.1 → 33.4
**Last revised:** 2026-10-31

---

## 1. The One-Sentence Principle

> CAS Suit owns the security foundation. Individual applications own
> their operational permissions. Administrators receive explicitly
> delegated authority. All permission decisions are enforced centrally.

---

## 2. The Three Layers of Permission

Every permission has three distinct attributes.

| Layer | Question it answers | Who controls it |
|---|---|---|
| Ownership | Which application defines this permission? | Namespace prefix |
| Administration | Which administrator can assign it? | Admin's own scope |
| Enforcement | Can this user perform this action? | Central AuthorizationService |

---

## 3. Permission Namespace Ownership

Every permission slug follows: `{namespace}.{resource}.{action}`

The prefix determines ownership.

| Namespace | Owner | Registrar |
|---|---|---|
| `cas.*` | CAS Core | Migration (protected) |
| `core.*` | CAS Core | Migration (protected) |
| `recruit.*` | Recruit Aid | `Module.php` |
| `hr.*` | HR Aid | `Module.php` |

**Rule:** An application may register permissions only under its own namespace. `cas.*` and `core.*` are protected.

---

## 4. Administrative Scopes

### 4.1 Global CAS Admin
Holds `cas.*` permissions via Super Admin role.

### 4.2 Application Admin (Delegated)
Holds a delegation record in `core_admin_delegations`. Scoped, expiry-bounded, auditable, non-escalating.

### 4.3 Regular User
Holds roles scoped to specific applications.

---

## 5. Permission Evaluation (Enforcement)

The central `AuthorizationService` answers all "can this user do X?".

### 5.2 Precedence (first match wins)

1. User null -> DENY
2. No tenant -> DENY
3. Permission not found -> DENY
4. Module disabled -> DENY
5. Module not assigned -> DENY
6. Policy deny -> DENY
7. Explicit user deny -> DENY
8. Explicit user grant -> ALLOW
9. Role grant (with app assignment) -> ALLOW
10. Super admin bypass (public only) -> ALLOW
11. Default -> DENY

---

## 6. Delegation Model

App admins hold explicit delegation records with allowed/denied lists. Cannot grant permissions they don't hold themselves.

---

## 7. Role Model

Roles are scoped (global / app). System roles are protected. No inheritance in v1.

---

## 8. Permission Registration

Apps register via `getPermissionDefinitions()`. Registration does not auto-activate.

---

## 9. Audit

All RBAC mutations write to `core_rbac_audit_logs`. Append-only.

---

## 10. Threat Model

Privilege escalation paths are closed via namespace checks, self-grant rejection, sensitivity classes, and delegation scope enforcement.

---

## 11. Migration Roadmap

Batch 33.1 (Schema), 33.2 (Service), 33.3 (Delegation), 33.4 (UI).

---

## 12. Non-Goals

- Break-glass access (deferred)
- Role inheritance (deferred)
- Field-level enforcement (v2)

---

## 13. Compatibility

Backward compatible with existing RBAC.

---

## 14. Doctrine: The Four Independent Dimensions

**Last revised:** 2026-11-08 (post-0061)

Section 4.1, Section 5.2 Rule 10, and Section 13 are **superseded** by this
section. Super Admin no longer exists. Rule 10 (super admin bypass) is
removed. The model is no longer "backward compatible" with a role named
`super_admin`; it replaces that role with a platform-role mechanism.

### 14.1 The four dimensions

Every authorization decision combines four **independent** dimensions:

| Dimension | What it governs | Storage |
|---|---|---|
| **Home tenant** | Login identity; whose data the user natively owns | `core_users.tenant_id NOT NULL` |
| **Platform authority** | `cas.*` operations across the whole suite | `core_user_platform_roles` |
| **Tenant authority** | `recruit.*`, `core.*` operations inside a tenant | `core_user_roles` + `core_user_permissions` + `core_permission_policies` |
| **Developer cross-tenant access** | Explicit, audited access to any tenant's data | Future: `platform_owners` + activation event (not yet implemented) |

Each dimension is evaluated independently. **No dimension grants another.**

- Platform authority does not grant tenant authority.
- Tenant authority does not grant platform authority.
- Developer cross-tenant access is an activation event, not a role.

### 14.2 The precedence chain (current code)

The actual implementation in `AuthorizationService::allows()`, in order:

1. **Rule 1:** `user === null` → DENY.
2. **Rule 2:** permission unknown or inactive → DENY.
3. **Platform short-circuit:** `cas.*` → `hasPlatformPermission(user.id, permission)` → decision returned; **tenant identity is never checked.**
4. **Rule 2 (tenant identity):** for non-`cas.*` permissions, `user.tenantId === active_tenant_id` required → DENY otherwise.
5. **Rule 4:** module resolution + enabled → DENY if disabled/unknown.
6. **Rule 5:** policy denial → DENY.
7. **Rule 6:** app not assigned → DENY.
8. **Rule 7:** explicit user denial → DENY.
9. **Rule 8:** explicit user grant → ALLOW.
10. **Rule 9:** role grant (via `permissionSlugs`) → ALLOW.
11. **Rule 11:** default → DENY.

The earlier §5.2 numbering is retained in the code comments but is no
longer authoritative; the chain above is what runs.

### 14.3 The resource-gating invariant

> **Platform permissions are scope-limited, not gateway permissions.**
>
> A permission such as `cas.dashboard.access` grants the dashboard. It does
> **not** grant every resource displayed on that dashboard. Every resource
> reached from the dashboard must independently satisfy its own permission
> gate on its own route.
>
> Corollary: `cas_admin` with no tenant role may see aggregated counts on
> the dashboard and be denied every per-record view behind a tile. That is
> the intended behaviour, not a gap.

This is the invariant that `PlatformSurfaceTest::testDashboardPermissionDoesNotGrantResourcePermission`
and `testEveryDashboardTileTargetHasPermissionMiddleware` enforce. Those
tests are skipped until the platform surface is built; when the surface
lands, the tests un-skip and become the guard.

### 14.4 Test-coverage map

Every property in this section has a test. See `docs/RBAC-TESTING.md` for
the criterion-to-test mapping.

### 14.5 Deferred work

The following are designed but not yet implemented:

- **Support delegation** (`core_support_delegations`) with asymmetric revocation.
- **Developer UI** (`/cas/*` route surface): dashboard, tenant assignment,
  support delegation management, developer context activation.
- **Analytics layer** (`cas_analytics_*` tables): aggregates only, no PII.
- **TenantContext effective-vs-home split** so a cas_admin can operate in
  a tenant they are not a member of, without their home tenant bleeding into
  `findById()` lookups.

These are the preconditions for `PlatformSurfaceTest` to un-skip.
