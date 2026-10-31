# Recruit Aid — Permissions System

**Status:** Complete
**Last revised:** 2026-10-29

---

## 1. Overview

RBAC with granular permission assignment. Every user has roles; every role has permissions; every permission gates one action.

Fully database-driven — admins create roles, assign permissions, and assign users, all through the UI.

---

## 2. The Four Tables

| Table | Purpose |
|---|---|
| `core_roles` | Role definitions. Only `super_admin` is `is_system=1`. |
| `core_permissions` | All available permissions (45+ seeded). |
| `core_role_permissions` | Pivot: which roles have which permissions. |
| `core_user_roles` | Pivot: which users have which roles. |

---

## 3. Permission Slug Format

```
{module}.{entity}.{action}
```

Examples: `core.users.view`, `recruit.organisations.create`, `recruit.vacancies.delete`

---

## 4. Three Enforcement Layers

**Server-side:** Routes declare `permission:slug` middleware. `PermissionMiddleware` throws `HttpException::forbidden()` on failure.

**Sidebar:** Links wrapped in `$user->can('permission')`.

**Action buttons:** Create/Edit/Delete wrapped in `$user->can('permission')`.

---

## 5. Super Admin Bypass

`User::can()` returns `true` for `super_admin` on any permission. That's why `super_admin` is the only system role.

---

## 6. Admin UI

**Create role:** `/admin/roles-perm/roles/create`

**Assign permissions:** `/admin/roles-perm/permissions?role_id=X` (role selector + checkbox matrix)

**Assign users to role:** Two paths — user edit form, or role edit page's 'Users with this role' table with inline Remove.

**Delete role:** Guarded — blocked if `is_system=1` or users assigned. Uses flash + redirect, not 500.

---

## 7. Adding Permissions for a New Module

1. Insert into `core_permissions` (new `module` value = new group in matrix)
2. Declare routes with `permission:module.entity.action`
3. Wrap sidebar links in `$user->can('...')`
4. Wrap action buttons in `$user->can('...')`

---

## 8. Doctrine

- **#42** — Business-rule rejections use flash + redirect, not exceptions
- **#43** — Relationship management is bidirectional

---

## 9. References

- `docs/NAMING-CONVENTION.md` §6 — slug format
- `app/Platform/Middleware/PermissionMiddleware.php`
- `app/Platform/Models/User.php` — `can()` method
- `app/Platform/Repositories/RoleRepository.php`
- `app/Platform/Repositories/UserRepository.php`
- `app/Recruit/Controllers/Admin/RoleController.php`
- `app/Recruit/Controllers/Admin/AdminUserController.php`


---

## 10. CAS Suit RBAC Extension (Batch 33)

The legacy RBAC system described above remains active. The following
sections document the extension that brings it to CAS Suit enterprise
grade.

**Status:** Design approved. Implementation in batches 33.1 -> 33.4.
**See also:** docs/RBAC-ARCHITECTURE.md for the full design.

---

## 11. The Central Principle

> CAS Suit owns the security foundation. Individual applications own
> their operational permissions. Administrators receive explicitly
> delegated authority. All permission decisions are enforced centrally.

This means:
- Recruit Aid owns recruit.* permissions.
- HR Aid owns hr.* permissions.
- CAS Core owns cas.* and core.* permissions.
- No application may register a permission under a foreign namespace.

---

## 12. The New Tables

| Table | Purpose |
|---|---|
| core_permission_registrations | The registry of what each module claims it owns |
| core_admin_delegations | Explicit, scoped, expiring grants of app admin powers |
| core_permission_policies | Global restrictions that override role grants |
| core_rbac_audit_logs | Append-only record of every RBAC mutation |

Additionally, core_permissions gains sensitivity, registered_version, and is_active.
core_roles gains is_system and template_slug.
core_user_roles gains assigned_by, assigned_at, expires_at.

---

## 13. The AuthorizationService

app/Platform/Authorization/AuthorizationService.php becomes the ONLY code path that answers "can this user do X?". It replaces direct calls to User::can().

User::can() and Auth::can() will delegate to it.

### Precedence (first match wins)

1. User null -> DENY
2. No tenant context -> DENY
3. Permission not registered in core_permissions -> DENY
4. Owning module disabled -> DENY
5. Module not assigned to user -> DENY
6. Permission matches a global policy DENY -> DENY
7. Explicit user denial -> DENY
8. Explicit user grant -> ALLOW
9. Role grant (with valid app assignment) -> ALLOW
10. Super admin bypass (only for sensitivity = 'public') -> ALLOW
11. Default -> DENY

---

## 14. Delegation Model

A global admin holding cas.rbac.delegate_admin can appoint an application admin:

AdminDelegationService::delegate(
    userId: 7,
    moduleKey: 'hr',
    grantedBy: 1,
    expiresAt: '2027-12-31',
    allowed: ['hr.users.manage', 'hr.roles.manage'],
    denied:  ['hr.payroll.approve']
);

**Rule:** You cannot grant a permission you don't hold.
If the admin tries, UnauthorizedDelegationException is thrown.

The delegation is checked on every action via
AdminDelegationService::canDo($userId, $permission) and is
audited in core_rbac_audit_logs.

---

## 15. Sensitive Data

Permissions are classified:

| Class | Meaning | Super admin bypass? |
|---|---|---|
| public | Admin ops, dashboards | Yes |
| sensitive | PII, salary, individual records | No |
| restricted | Payroll approval, GDPR erase | No, + special audit |

**This closes the gap** where a global admin could read payroll or
beneficiary data without explicit permission.

---

## 16. Threat Model

Escalation paths closed:

- App admin cannot create a role with cas.* permissions.
- App admin cannot assign a role to themselves.
- App admin cannot edit roles outside their module.
- Cross-tenant role reference is prevented by FK + WHERE clauses.
- Expired delegations immediately stop working.
- UI hiding is not security; every route has middleware.

---

## 17. Rollout Plan

| Batch | Scope |
|---|---|
| 33.1 | Schema + backfill |
| 33.2 | AuthorizationService + rewiring |
| 33.3 | Delegation + audit |
| 33.4 | Global RBAC UI |

Until 33.2 ships, Auth::can() behaviour is unchanged. All route, middleware and controller code continues working as-is.