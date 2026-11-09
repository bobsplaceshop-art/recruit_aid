# Recruit Aid — Permissions System

**Status:** Shipped. Six tables. Four-axis model.
**Last revised:** 2026-11-09 (post-0067)

**See also:**
- docs/RBAC-ARCHITECTURE.md — the design and doctrine (four-axis model)
- docs/RBAC-TESTING.md — what's proven by what test
- docs/CAS-PLATFORM-SURFACE.md — the /cas/* platform surface

---

## 1. Overview

RBAC with granular permission assignment across four independent
dimensions:

- **Tenant authority** — a user's roles within their home tenant.
- **Platform authority** — cas.* permissions granted via platform
  roles, independent of any tenant.
- **Developer cross-tenant access** — explicit, expiring, audited
  support delegations to another tenant's data.
- **Contractual platform owner** — the developer designation in
  platform_owners, distinct from a regular cas_admin.

Every user has a home tenant. No user is tenantless. Permission
decisions are made centrally by AuthorizationService::allows().

---

## 2. The Six RBAC Tables

| Table | Purpose | Scoped by |
|---|---|---|
| core_roles | Tenant-scoped role definitions | tenant_id |
| core_permissions | The permission catalogue (45+ seeded) | global |
| core_role_permissions | Pivot: which roles have which permissions | via core_roles |
| core_user_roles | Pivot: which users have which roles | tenant_id |
| core_platform_roles | Platform-scoped roles (cas_admin, cas_developer) | global |
| core_platform_role_permissions | Pivot: platform roles -> cas.* permissions | global |
| core_user_platform_roles | Assignments of platform roles to users | global |

**Plus the delegations and audit tables:**

| Table | Purpose |
|---|---|
| core_admin_delegations | Explicit grants of app-admin powers (existing, underused) |
| core_support_delegations | Explicit cross-tenant support grants (active) |
| core_permission_policies | Per-tenant denies that override role grants |
| core_rbac_audit_logs | Append-only audit of every RBAC mutation |
| platform_owners | Contractual developer designation (bootstrapped, not UI-written) |

---

## 3. Permission Slug Format

    {namespace}.{entity}.{action}

Examples:
- core.users.view — tenant-scoped permission granted via tenant roles
- recruit.candidates.create — tenant-scoped permission
- cas.dashboard.access — platform-scoped permission, granted via
  core_user_platform_roles only

**Namespace ownership:**
- cas.* and core.* — CAS Core owns these. No application may
  register them.
- recruit.* — Recruit Aid owns these.
- hr.* — HR Aid owns these.

---

## 4. Three Enforcement Layers

**Server-side:** Routes declare permission:slug middleware.
PermissionMiddleware throws HttpException::forbidden() (rendered as
the branded 403 page) on failure.

**Sidebar:** Links wrapped in $user->can('permission').

**Action buttons:** Create/Edit/Delete wrapped in
$user->can('permission').

**UI hiding is not security.** Every route has a permission middleware.
The sidebar hiding is UX, not enforcement.

---

## 5. No Super Admin Bypass

The previous doctrine had a "super_admin bypass" in Rule 10 of the
authorization chain. **That role was deleted by migration 0061.** The
bypass was removed.

Instead:
- Platform permissions (cas.*) are granted via core_platform_roles.
  They are evaluated independently of tenant roles.
- A cas_admin who is not in platform_owners cannot grant
  cas_developer delegations.
- Every access requires an actual grant path. There is no "super user"
  who can do everything by role name.

See docs/RBAC-ARCHITECTURE.md §4.1 for the cas_admin / cas_developer
distinction.

---

## 6. Admin UI

The tenant admin UI at /recruit/admin/roles-perm/* manages the tenant's
roles, permissions, and users.

- **Create role:** /recruit/admin/roles-perm/roles/create
- **Assign permissions:** /recruit/admin/roles-perm/permissions?role_id=X
  (role selector + checkbox matrix)
- **Assign users to role:** user edit form, or role edit page's
  "Users with this role" table with inline Remove
- **Delete role:** guarded — blocked if is_system=1 or users assigned.
  Uses flash + redirect, not 500.

The platform admin UI at /cas/* manages delegations, tenant context,
and platform-level operations. See docs/CAS-PLATFORM-SURFACE.md.

---

## 7. Adding Permissions for a New Module

1. **Register the permission** in the module's getPermissionDefinitions()
   or via a migration insert into core_permissions (new module value
   = new group in the matrix).
2. **Declare routes** with permission:{module}.{entity}.{action}.
3. **Wrap sidebar links** in $user->can('...').
4. **Wrap action buttons** in $user->can('...').
5. **Add a test** — see docs/RBAC-TESTING.md for the acceptance-criteria
   pattern.

---

## 8. Doctrine

- **#42** — Business-rule rejections use flash + redirect, not exceptions
- **#43** — Relationship management is bidirectional

---

## 9. Debugging a Denial

When $user->can('some.permission') returns false unexpectedly:

1. **Does the permission exist?**
   SELECT * FROM core_permissions WHERE slug = '...'
2. **Is it active?** is_active = 1
3. **Is the module enabled?** SELECT is_enabled FROM core_module_state WHERE module_key = '...'
4. **Is the user assigned to the module?**
   SELECT * FROM core_user_applications WHERE user_id = ? AND module_key = '...'
5. **Is there a policy denial?**
   SELECT * FROM core_permission_policies WHERE tenant_id = ? AND permission_slug = '...'
6. **Does the user have a role granting it?**
   SELECT r.slug FROM core_user_roles ur JOIN core_roles r ON ...
     JOIN core_role_permissions rp ON ...
     WHERE ur.user_id = ?
7. **Is there an explicit denial?**
   SELECT * FROM core_user_permissions WHERE user_id = ? AND denied = 1

For cas.* permissions, the check is different — the permission is
granted via core_user_platform_roles, not tenant roles. See
AuthorizationService::hasPlatformPermission().

---

## 10. The AuthorizationService

app/Platform/Authorization/AuthorizationService.php is the ONLY code
path that answers "can this user do X?". User::can() and Auth::can()
delegate to it.

See docs/RBAC-ARCHITECTURE.md §5.1 for the full precedence chain.

---

## 11. Support Delegations

Support delegations (core_support_delegations) allow a cas_admin or
tenant principal to act on a specific tenant's data within a specific
module, without changing their home tenant. Full lifecycle in
docs/CAS-PLATFORM-SURFACE.md.

Key properties:
- **Scope ceiling:** read_only / read_write / full. Destructive
  actions (delete, manage) require explicit opt-in even under full.
- **cas.\* not grantable:** rejected at grant time.
- **Asymmetric revocation:** developer revokes immediately; tenant
  admin revocations go through a 24-hour cooling-off window during
  which the developer can cancel.
- **Audited:** every grant, revoke, and cancel writes to
  core_rbac_audit_logs.

---

## 12. Sensitive Data

Permissions carry a sensitivity classification. It affects the legacy
super_admin bypass (now removed) but does not gate access on its own —
the actual enforcement is the permission check.

| Class | Meaning |
|---|---|
| public | Admin ops, dashboards |
| sensitive | PII, salary, individual records |
| restricted | Payroll approval, GDPR erase |

**Sensitivity is metadata for policy design, not an enforcement
mechanism.** Enforcement comes from the permission grant path. A user
with recruit.candidates.view can view candidates whether the
permission is marked sensitive or public.

---

## 13. Threat Model

Escalation paths closed:

- **App admin cannot create a role with cas.\* permissions** —
  namespace enforcement in the role editor.
- **App admin cannot assign a role to themselves** — self-grant
  rejection.
- **App admin cannot edit roles outside their tenant** — tenant-scoped
  RoleRepository.
- **Cross-tenant role reference is prevented** — composite FKs on
  core_user_roles (user-tenant and role-tenant must match).
- **Expired delegations immediately stop working** — activeFor()
  filters by expires_at > NOW().
- **cas.\* cannot be delegated** — rejected at grant time.
- **Platform owner is contractual** — platform_owners is not
  UI-writable.
- **Step-up re-auth on context switch** — prevents session-hijack
  tenant hopping.
- **Every RBAC mutation is audited** — append-only log.
- **UI hiding is not security** — every route has middleware.

---

## 14. References

- docs/RBAC-ARCHITECTURE.md — the design and doctrine
- docs/RBAC-TESTING.md — acceptance-criteria-to-test map
- docs/CAS-PLATFORM-SURFACE.md — the /cas/* platform surface
- app/Platform/Authorization/AuthorizationService.php — the central decision
- app/Platform/Authorization/SupportDelegationService.php — delegation lifecycle
- app/Platform/Authorization/PlatformOwnerService.php — developer designation
- app/Platform/Support/RbacAuditLogger.php — audit trail writer
- app/Platform/Middleware/PermissionMiddleware.php — route-level enforcement
- app/Platform/Models/User.php — can() delegates to AuthorizationService
