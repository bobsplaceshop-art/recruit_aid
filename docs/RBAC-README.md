# CAS Suit — RBAC Developer README

**Audience:** engineers adding modules or permissions.
**See also:** docs/RBAC-ARCHITECTURE.md for the full design.

---

## Quick Reference

| I want to... | Do this |
|---|---|
| Add a permission to my module | Implement getPermissionDefinitions() in app/Modules/{Key}/Module.php |
| Check permission in a controller | $auth->can('recruit.candidates.view') |
| Gate a route | Add 'permission:recruit.candidates.view' to the route spec |
| Gate a sidebar link | <?php if ($user->can('...')): ?> around the <a> |
| Appoint an app admin | AdminDelegationService::delegate(...) (batch 33.3) |
| Debug "why is this denied?" | See section 5 below |

---

## 1. Adding a Permission

### Step 1: Declare it in your Module

// app/Modules/Recruit/Module.php
public function getPermissionDefinitions(): array
{
    return [
        [
            'slug' => 'recruit.candidates.archive',
            'name' => 'Archive Candidates',
            'description' => 'Move a candidate to archived state.',
            'sensitivity' => 'public',
        ],
    ];
}

The namespace (recruit.*) must match getKey().

### Step 2: Apply the migration

On next app boot, ModuleRegistrar writes the definition to core_permission_registrations. A global admin must activate it via the registry UI before it becomes assignable.

### Step 3: Gate it

// In your controller:
if (!$auth->can('recruit.candidates.archive')) {
    throw HttpException::forbidden();
}

// In your route:
$router->post('/recruit/candidates/{code}/archive',
    [CandidateController::class, 'archive'],
    ['auth', 'app_access:recruit', 'permission:recruit.candidates.archive']);

---

## 2. Sensitivity Classes

| Class | Use for | Super admin can bypass? |
|---|---|---|
| public | Config, self-service, dashboards | Yes |
| sensitive | PII, salary, individual records | No |
| restricted | Payroll approval, GDPR erase | No + special audit |

**Rule of thumb:** if leaking it would embarrass a client or violate a regulation, it's sensitive or restricted.

---

## 3. Delegating an App Admin

AdminDelegationService::delegate(
    userId: 7,
    moduleKey: 'hr',
    grantedBy: 1,
    expiresAt: '2027-12-31 23:59:59',
    allowed: ['hr.users.manage', 'hr.roles.manage'],
    denied:  ['hr.payroll.approve']
);

**You cannot grant a permission you don't hold.** The service throws UnauthorizedDelegationException otherwise.

---

## 4. Namespace Rules

| Your module key | May register |
|---|---|
| recruit | recruit.* only |
| hr | hr.* only |
| anything | **never** cas.* or core.* |

Registering under a foreign namespace is a hard error.

---

## 5. Debugging a Denial

1. Does the permission exist? SELECT * FROM core_permissions WHERE slug = '...'
2. Is it active? is_active = 1
3. Is the module enabled for the user? Check core_user_applications
4. Does the user have a role granting it? Check core_user_roles -> core_role_permissions
5. Is there an explicit denial? Check core_user_permissions.denied
6. Is there a policy deny? Check core_permission_policies

---

## 6. Common Mistakes

**Mistake:** Hardcoding $user->id === 1 for admin checks.
**Fix:** Use $auth->can('...') — the ID is not the permission.

**Mistake:** Gating only the UI.
**Fix:** Every destructive route must have 'permission:...' middleware. UI hiding is not security.

**Mistake:** Using a cas.* slug in a module.
**Fix:** If it's platform-wide, it's not your permission.

---

## 7. Rollout Status

| Batch | Status |
|---|---|
| 33.1 — Schema | In progress |
| 33.2 — AuthorizationService | Pending |
| 33.3 — Delegation + Audit | Pending |
| 33.4 — Global UI | Pending |