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

