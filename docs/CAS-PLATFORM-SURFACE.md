# CAS Suit — Platform Surface

**Status:** Shipped. 19 routes, 4 controllers, 6 views.
**Last revised:** 2026-11-09 (post-0067)

**See also:**
- docs/RBAC-ARCHITECTURE.md — the doctrine this surface enforces
- docs/PERMISSIONS.md — the six RBAC tables and the four-axis model
- docs/RBAC-TESTING.md — the tests that prove this surface works

---

## 1. What the Platform Surface Is

The /cas/* route surface is where a platform administrator (cas_admin)
and the contractual developer interact with the platform-level
operations that sit above any single tenant's business data.

It exists to enforce a specific doctrine:

> **The dashboard permission is not a gateway to resources.**
>
> cas.dashboard.access grants the dashboard. It does NOT grant every
> resource displayed on the dashboard. Every resource reached from the
> dashboard must independently satisfy its own permission gate on its
> own route.

This is the "resource-gating invariant" from RBAC-ARCHITECTURE.md §14.3.
It is enforced by two tests (RG-01 and RG-02) and by the deliberate
design of the surface itself: there is no route that returns tenant
business data under cas.dashboard.access alone.

---

## 2. The Three Controllers

The surface is built from three controllers, each with a narrow scope:

| Controller | Purpose |
|---|---|
| CasDashboardController | Aggregated platform metrics (counts and statuses) |
| CasDelegationController | Grant, list, revoke, and cancel support delegations |
| CasContextController | Switch and reset the effective tenant (with step-up re-auth) |

Each controller is a thin CRUD wrapper. Authorization decisions live in
AuthorizationService and SupportDelegationService, not in the controller.

---

## 3. The Authorized Platform Roles

The /cas/* surface distinguishes two authority levels:

**cas_admin** (operational platform administrator)
- Holds all cas.* permissions via core_platform_roles.
- Can access the dashboard, manage delegations, and context-switch
  (with step-up re-auth).
- Can grant tenant_admin delegations (grants within their own tenant).
- Can revoke delegations.
- CANNOT grant cas_developer delegations unless also in platform_owners.
- CANNOT cancel a pending tenant revocation.

**cas_developer** (contractual platform owner)
- A specific user designated in platform_owners.
- Holds cas.* permissions AND the platform-owner designation.
- Can grant cas_developer delegations.
- Can cancel pending tenant revocations.
- Bootstrapped via manual DB insert or signed CLI — never through the
  web UI.

The distinction is enforced by PlatformOwnerService::isOwner() and
checked at three points: SupportDelegationService::assertGrantorAuthorized,
SupportDelegationService::cancelPendingRevocation, and (implicitly) the
audit trail, which records the actor_id for every action.

---

## 4. The Ten Routes

Every /cas/* route has a permission: middleware. There are no exceptions.

| Method | Path | Permission | Controller method |
|---|---|---|---|
| GET | /cas/dashboard | cas.dashboard.access | CasDashboardController::index |
| GET | /cas/delegations | cas.rbac.manage_global | CasDelegationController::index |
| GET | /cas/delegations/grant | cas.rbac.manage_global | CasDelegationController::grantForm |
| POST | /cas/delegations/grant | cas.rbac.manage_global | CasDelegationController::grant |
| POST | /cas/delegations/{id}/revoke | cas.rbac.manage_global | CasDelegationController::revoke |
| POST | /cas/delegations/{id}/cancel | cas.rbac.manage_global | CasDelegationController::cancelRevocation |
| GET | /cas/context/confirm | cas.dashboard.access | CasContextController::showConfirm |
| POST | /cas/context/confirm | cas.dashboard.access | CasContextController::confirm |
| POST | /cas/context/switch | cas.dashboard.access | CasContextController::switch |
| POST | /cas/context/reset | cas.dashboard.access | CasContextController::reset |

### 4.1 Why delegation management uses cas.rbac.manage_global

There is no dedicated cas.delegations.manage permission. Delegation
management is classified under the same authority as RBAC configuration:
a platform admin who can change who has what role can also change who
has support access. This avoids a permission proliferation where every
administrative action gets its own slug.

If a future need arises to separate "delegation admin" from "role admin"
(e.g., a support-tier admin who can issue delegations but cannot touch
platform roles), a cas.delegations.manage permission can be added then.

### 4.2 Why the confirm endpoint uses cas.dashboard.access

The confirm flow (GET /cas/context/confirm and POST /cas/context/confirm)
exists specifically to gate context switching. Anyone with
cas.dashboard.access can see the dashboard and can request a context
switch. The additional security is not in the permission — it's in the
re-authentication step (Session::hasRecentReauth).

### 4.3 The controller method signatures

Every method follows the framework's contract:

    public function methodName(Request $request, array $params = []): Response

The controller is constructed via the container with
    __construct(private Container $container) {}

This is the standard controller pattern. No custom routing, no method
signature overrides.

---

## 5. The Dashboard

GET /cas/dashboard renders resources/views/cas/dashboard.php with:

- Aggregate tenant status counts (active / suspended / archived)
- The list of currently-active tenants (id, name, status)
- The current user's home tenant and effective tenant

**The dashboard queries only aggregate data.** It does not select
per-record PII from any tenant table. This is the "aggregates not
records" requirement from RBAC-ARCHITECTURE.md §14.3, enforced by
RG-03 (which asserts the controller source contains only COUNT()
aggregates and no per-record column selections).

### 5.1 The tenant tiles

The active tenants are rendered as a list. Each row has a "Act as this
tenant" button that POSTs to /cas/context/switch. That button is not a
data-access link — it's a context-change request that will hit the
step-up re-auth gate.

Clicking it does NOT give the cas_admin access to that tenant's
candidates or contacts. It only changes the effective tenant. Whether
the cas_admin can actually access anything in that tenant depends on
the same authorization checks that would run for any user in that
tenant — which for a cas_admin without a delegation means DENY (RG-01).

### 5.2 The reset button

The dashboard has a "Reset to home tenant" button that POSTs to
/cas/context/reset. This is a de-escalation, so it does not require
step-up re-auth.

---

## 6. Support Delegations

A support delegation is an explicit, expiring, auditable grant that
lets a cas_admin (or a tenant principal) access a specific tenant's
data within a specific module, without changing their home tenant.

### 6.1 The grant flow

1. A tenant admin or a platform owner visits /cas/delegations/grant.
2. They fill the form: target user, target tenant, module, scope,
   expiry date, optional reason, optional denied permissions.
3. POST /cas/delegations/grant calls
   SupportDelegationService::grant() with the request fields.
4. The service validates:
   - The grantor is authorized for the grantor type
     (tenant_admin must have an active role in the target tenant;
     cas_developer must be in platform_owners).
   - The grantee is eligible (cas_admin for cas_developer grants;
     cas_admin or a tenant-role user for tenant_admin grants).
   - The expiry is in the future.
   - A reason is provided for cas_developer grants.
   - No cas.* slugs in the permission lists.
5. The service materializes the scope ceiling into
   allowed_permissions_json.
6. The delegation row is inserted into core_support_delegations.
7. A row is written to core_rbac_audit_logs with
   action='delegation.grant'.

### 6.2 The scope ceiling

The scope enum is the primary enforcement ceiling:

**read_only**
- Suffixes: view, list, read, export, search.
- Cannot create, edit, delete, or otherwise write.
- Cannot approve, submit, or advance.

**read_write**
- Suffixes: view, list, read, export, search, create, edit, update,
  send, respond, submit, evaluate, advance, close, assign, unassign,
  upload, link, attach.
- Can perform most operations.
- Cannot delete, purge, restore, or perform destructive manage actions.

**full**
- Automatically permits all non-destructive suffixes.
- Destructive actions (delete, manage, purge, restore) require explicit
  opt-in via allowed_extra_permissions at grant time.
- This is not "unlimited" — it's "unlimited except what can damage data
  irreversibly."

The materialized allowed_permissions_json is derived from the scope,
so at authorization time the permits() method enforces both the ceiling
and the allowlist membership. No JSON-only delegation can exceed its
declared scope.

### 6.3 The explicit denial

denied_permissions_json is a subtractive list. Evaluated BEFORE the
allowlist membership check. A permission in the deny list is denied
even if it would otherwise be allowed by the scope and the allowlist.

This lets a tenant admin grant a cas_admin "read_write, but not
recruit.candidates.export" — a narrow exclusion that would otherwise
require restricting the entire scope.

### 6.4 Asymmetric revocation

The revocation path differs based on the grantor type:

**cas_developer grant**
- The developer can revoke immediately.
- The delegation state goes directly to 'revoked'.
- No cooling-off window.

**tenant_admin grant**
- The tenant admin's revocation request enters 'revocation_pending'
  with a revocation_effective_at timestamp 24 hours in the future.
- During the cooling-off window, the cas_developer can call
  POST /cas/delegations/{id}/cancel to return the delegation to
  'active'.
- If the developer does nothing, a scheduled job (or the next request
  that calls activeFor()) will observe that the effective time has
  passed and the delegation will be excluded by the
  revocation_state='active' filter.

Why asymmetric? The developer's authority is contractual. They own the
delegation mechanism. A tenant admin can request a revocation but
cannot unilaterally disrupt platform-wide support operations. The
developer can always override.

### 6.5 The revocation audit

Every grant, revoke, and cancel writes to core_rbac_audit_logs:

- action='delegation.grant', tenant_id=target tenant,
  target_type='support_delegation', target_id=delegation id,
  actor_id=grantor
- action='delegation.revoke', same fields, actor_id=revoker
- action='delegation.cancel', same fields, actor_id=canceller

The audit rows are append-only. They cannot be modified or deleted
through the web app.

---

## 7. Context Switching

The effective tenant is the tenant whose data is queried by every
tenant-scoped repository. It defaults to the user's home tenant (their
core_users.tenant_id). A platform admin can override it via
/cas/context/switch, subject to step-up re-authentication.

### 7.1 The step-up gate

POST /cas/context/switch first checks:

    Session::hasRecentReauth(security.session.reauth_window_minutes)

The window defaults to 15 minutes (configurable in
AdminSettingsController). If the session's _reauth_at timestamp is
older than the window (or missing), the request redirects to
/cas/context/confirm?tenant_id={id}.

The confirm form asks for the user's password. POST /cas/context/confirm
verifies it via Hasher::verify, calls Session::markReauth() on success,
and then sets the effective tenant. Failures are rate-limited at 3
attempts per 15 minutes per IP (the cas_context_confirm bucket).

If the re-auth is already fresh (within the window), POST
/cas/context/switch sets the effective tenant directly — no re-prompt.

### 7.2 The audit trail

Every effective-tenant change writes to core_rbac_audit_logs:

- action='context.switch', tenant_id=target tenant,
  actor_id=user, before_json={'effective_tenant': prev},
  after_json={'effective_tenant': target}

The audit row is written both by the direct POST /cas/context/switch
path and by the POST /cas/context/confirm success path. Both paths are
covered.

### 7.3 Reset to home tenant

POST /cas/context/reset returns to the home tenant without step-up
re-auth. De-escalation does not require re-authentication. It writes
an audit row with action='context.reset'.

---

## 8. Tenant Management

The /cas/tenants/* routes provide full tenant lifecycle management.
All are gated by `cas.tenants.manage` (a platform permission, seeded
by migration 0068 and granted to cas_admin).

| Method | Path | Purpose |
|---|---|---|
| GET | /cas/tenants | List tenants (filterable by status) |
| GET | /cas/tenants/create | Create form |
| POST | /cas/tenants | Create a tenant |
| GET | /cas/tenants/{id} | Tenant detail + status actions |
| GET | /cas/tenants/{id}/edit | Edit form |
| POST | /cas/tenants/{id} | Update mutable fields |
| POST | /cas/tenants/{id}/suspend | Active -> suspended |
| POST | /cas/tenants/{id}/archive | Active or suspended -> archived (sets deleted_at) |
| POST | /cas/tenants/{id}/restore | Archived -> active (clears deleted_at) |

### 8.1 The `code` column is immutable

A tenant's `code` (UNIQUE) is set at creation and never edited. It's a
tenant identity key, not a display label. If a tenant rebrands, only
`name` and branding change. This matches the convention for
`core_users.email` — identity fields are not casually editable.

If a future need arises to change a tenant's code (e.g., a rename flow
with redirect handling), it would be a separate migration with an
explicit audit path — not the same `update()` call.

### 8.2 Status transitions

The status enum has three values: `active`, `suspended`, `archived`.

- **active -> suspended** via `/suspend`. Users lose access; data
  preserved. Reversible via `/restore` (which transitions back to active).
- **active or suspended -> archived** via `/archive`. Sets
  `deleted_at = NOW()`. The tenant is excluded from default listings
  but remains queryable via `findById()`. Reversible via `/restore`.
- **archived -> active** via `/restore`. Clears `deleted_at`.

There is no hard delete. Archived tenants remain in the database. A
future purge migration or CLI command could implement eventual
deletion for GDPR purposes — out of scope today.

### 8.3 Audit trail

Every tenant mutation writes to `core_rbac_audit_logs` via
`RbacAuditLogger`:

- `tenant.create` — after create
- `tenant.update` — after update (before/after in before_json/after_json)
- `tenant.suspend` — after suspend
- `tenant.archive` — after archive
- `tenant.restore` — after restore

The `tenant_id` field on the audit row is the tenant being modified
(NOT the actor's home tenant). This is different from the context
switch case where `tenant_id` is the effective tenant — the semantics
are "the tenant this action affects."

### 8.4 Dashboard integration

The dashboard at /cas/dashboard shows a "Manage tenants" button that
links to /cas/tenants. The aggregate counts on the dashboard
(active / suspended / archived) are recomputed from
`TenantRepository::countByStatus()` — no caching, always accurate.

---

## 9. What the Platform Surface Does NOT Do

The /cas/* surface is intentionally narrow. It does not:

- List tenant business data (candidates, contacts, etc.). That requires
  a support delegation. The dashboard shows counts, not records.
- Manage tenant users. That's the tenant's own admin UI at
  /recruit/admin/users (tenant-scoped user management).
- Manage platform roles. That's still pre-existing admin settings.

The surface's purpose is: platform administration, delegation
management, and context switching. Everything else stays where it was.

---

## 10. Deferred Work

Two operational capabilities are planned but not implemented:

**cas_analytics_* tables**
- Pre-aggregated metrics refreshed on a schedule.
- So the dashboard doesn't scan core_tenants on every request when the
  tenant count grows.
- Purely a performance concern. The current dashboard is correct; it's
  just not optimized for thousands of tenants.

**platform_owners designation flow**
- The platform_owners table is populated manually today (direct DB
  insert or signed CLI).
- A future signed CLI command would formalize the bootstrap process.
- Not a security gap: the enforcement works without it. This is about
  the initial setup ergonomics.

None of these are security gaps. The authorization contract is
complete; these are operational additions.

---

## 11. References

- docs/RBAC-ARCHITECTURE.md — the four-axis doctrine
- docs/PERMISSIONS.md — the six RBAC tables
- docs/RBAC-TESTING.md — the acceptance-criteria-to-test map
- app/Platform/Controllers/Cas/ — the three controllers
- app/Platform/Authorization/AuthorizationService.php — the central decision
- app/Platform/Authorization/SupportDelegationService.php — delegation lifecycle
- app/Platform/Authorization/PlatformOwnerService.php — developer designation
- app/Platform/Support/RbacAuditLogger.php — audit trail writer
- routes/web.php — the ten /cas/* routes
