# CAS Suit — RBAC Testing

**Purpose:** every security property in `RBAC-ARCHITECTURE.md` §14 is backed
by a named test. This document maps the property to the test that proves it,
so a reviewer can verify coverage without reading test source.

---

## Coverage map

| ID | Property | Test file | Test method | Status |
|---|---|---|---|---|
| PA-01 | A cas_admin platform role grants `cas.dashboard.access` | `PlatformAuthorityTest` | `testPlatformRoleGrantsCasDashboardAccess` | implemented |
| PA-02 | Platform authority is tenant-context-independent | `PlatformAuthorityTest` | `testPlatformAuthorityIsTenantContextIndependent` | implemented |
| PA-03 | Platform role does not grant tenant permissions | `PlatformAuthorityTest` | `testPlatformRoleDoesNotGrantTenantPermissions` | implemented |
| TA-01 | Tenant role grant allows tenant permission | `TenantAuthorityTest` | `testTenantRoleGrantsTenantPermission` | implemented |
| TA-02 | Tenant permission requires matching tenant context | `TenantAuthorityTest` | `testTenantPermissionRequiresMatchingTenantContext` | implemented |
| TA-03 | Explicit denial overrides role grant | `TenantAuthorityTest` | `testExplicitDenialOverridesTenantRoleGrant` | implemented |
| TA-04 | Policy denial overrides explicit grant | `TenantAuthorityTest` | `testPolicyDenialOverridesExplicitGrant` | implemented |
| CT-01 | Foreign-tenant user denied tenant-1 permission | `CrossTenantBoundaryTest` | `testForeignTenantUserDeniedTenantPermission` | implemented |
| CT-02 | Delegations isolated by tenant AND user ownership | `CrossTenantBoundaryTest` | `testDelegationsAreIsolatedByTenantAndUserOwnership` | implemented |
| CT-03 | Cache does not pollute across tenant contexts | `CrossTenantBoundaryTest` | `testCacheDoesNotPolluteAcrossTenantContexts` | implemented |
| RG-01 | Dashboard permission does not grant resource permission | `PlatformSurfaceTest` | `testDashboardPermissionDoesNotGrantResourcePermission` | deferred |
| RG-02 | Every dashboard tile target has permission middleware | `PlatformSurfaceTest` | `testEveryDashboardTileTargetHasPermissionMiddleware` | deferred |
| RG-03 | Analytics queries return aggregates only | `PlatformSurfaceTest` | `testAnalyticsQueriesReturnAggregatesOnly` | deferred |

**CLI-level coverage** (in `tests/authorization_test.php`) mirrors many of these
assertions in the precedence-chain form. Both suites run in CI:

- `composer test` — PHPUnit integration tests (this map).
- `php tests/authorization_test.php` — CLI precedence tests.

Together they cover: evaluation logic, tenant boundary, resource gating.

---

## Running the tests

### PHPUnit (integration)

    cd C:\projects\recruitaid
    composer test

### CLI (precedence chain + isolation suites)

    php tests/authorization_test.php
    php tests/candidate_repository_tenant_isolation_test.php
    php tests/organisation_repository_tenant_isolation_test.php
    php tests/engagement_assignment_tenant_isolation_test.php
    php tests/interview_scorecard_tenant_isolation_test.php
    php tests/live_search_tenant_isolation_test.php
    php tests/vacancy_tenant_isolation_test.php

### Full audit pipeline

    php tools/security-audit/run.php

---

## Adding a new test

1. **Identify the property** — write it as a one-sentence acceptance criterion.
2. **Give it an ID** (e.g., `PA-04`, `TA-05`, `CT-04`) and add it to the table above.
3. **Write a test named after the property**, not after the implementation.
4. **Use real DB fixtures inside a transaction** (pattern in `CrossTenantBoundaryTest`).
5. **Always restore `TenantContext` in a `finally` block.**
6. **Assert one property per test** — do not bundle multiple claims.

---

## When the platform surface lands

Un-skip `PlatformSurfaceTest`:

1. Delete the `markTestSkipped(...)` calls.
2. Implement each test's fixture + assertion as described in the skip message.
3. Update this map: change "deferred" to "implemented".

An un-skipped failing `RG-01` or `RG-02` is a defect against the resource-gating
invariant in `RBAC-ARCHITECTURE.md` §14.3. Do not ship the platform surface
without them passing.
