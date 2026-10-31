<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Authorization\AuthorizationService;

$app = App::boot(dirname(__DIR__));
$db = $app->container()->get(Database::class);
$authz = AuthorizationService::instance();

$pass = 0;
$fail = 0;
$skip = 0;
$failures = [];

function ok(string $name, bool $cond, string $msg = ''): void {
    global $pass, $fail, $failures;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else       { $fail++; $failures[] = $name; echo "  FAIL  $name" . ($msg ? " -- $msg" : '') . "\n"; }
}

function skipped(string $name, string $why): void {
    global $skip;
    $skip++;
    echo "  SKIP  $name -- $why\n";
}

/**
 * Load a user with roleSlugs and permissionSlugs hydrated.
 */
function loadUser(Database $db, int $id): User {
    $row = $db->selectOne("SELECT * FROM core_users WHERE id = ?", PDOParams::positional([$id]));
    if ($row === null) {
        throw new RuntimeException("User {$id} not found");
    }
    $u = User::fromRow($row);

    $roles = $db->select(
        "SELECT r.slug FROM core_user_roles ur JOIN core_roles r ON r.id = ur.role_id WHERE ur.user_id = ?",
        PDOParams::positional([$id])
    );
    $u->roleSlugs = array_map(fn($r) => Row::str($r, 'slug'), $roles);

    $perms = $db->select(
        "SELECT p.slug FROM core_user_roles ur
         JOIN core_role_permissions rp ON rp.role_id = ur.role_id
         JOIN core_permissions p ON p.id = rp.permission_id
         WHERE ur.user_id = ?",
        PDOParams::positional([$id])
    );
    $u->permissionSlugs = array_map(fn($p) => Row::str($p, 'slug'), $perms);

    return $u;
}

echo "Creating fixtures...\n";

$tenantId = 1;

$db->execute("DELETE FROM core_users WHERE email LIKE 'rbac-test-%'");

$superId = $db->insert(
    "INSERT INTO core_users (tenant_id, name, email, password_hash, status) VALUES (?, ?, ?, ?, 'active')",
    PDOParams::positional([$tenantId, 'Test SuperAdmin', 'rbac-test-super@example.test', '$2y$10$dummy'])
);

$recruiterAssignedId = $db->insert(
    "INSERT INTO core_users (tenant_id, name, email, password_hash, status) VALUES (?, ?, ?, ?, 'active')",
    PDOParams::positional([$tenantId, 'Test Recruiter', 'rbac-test-recruiter@example.test', '$2y$10$dummy'])
);

$unassignedId = $db->insert(
    "INSERT INTO core_users (tenant_id, name, email, password_hash, status) VALUES (?, ?, ?, ?, 'active')",
    PDOParams::positional([$tenantId, 'Test Unassigned', 'rbac-test-unassigned@example.test', '$2y$10$dummy'])
);

$superRoleRow = $db->selectOne("SELECT id FROM core_roles WHERE slug = 'super_admin'");
if ($superRoleRow === null) {
    fwrite(STDERR, "FATAL: super_admin role not found\n");
    exit(2);
}
$superRoleId = Row::int($superRoleRow, 'id');
$db->execute("INSERT INTO core_user_roles (user_id, role_id) VALUES (?, ?)",
    PDOParams::positional([$superId, $superRoleId]));

$db->execute("INSERT INTO core_user_applications (user_id, module_key, is_default) VALUES (?, 'recruit', 1)",
    PDOParams::positional([$recruiterAssignedId]));

// Create a role that grants recruit.candidates.view for Rule 7 (denial overrides role).
$viewPermRow = $db->selectOne("SELECT id FROM core_permissions WHERE slug = 'recruit.candidates.view'");
if ($viewPermRow === null) {
    fwrite(STDERR, "FATAL: recruit.candidates.view permission not found\n");
    exit(2);
}
$viewPermId = Row::int($viewPermRow, 'id');

// Ephemeral role scoped to recruit that grants the permission.
$db->execute(
    "INSERT INTO core_roles (name, slug, description, scope, module_key, is_system)
     VALUES ('RBAC Test Viewer', 'rbac_test_viewer', 'ephemeral', 'app', 'recruit', 0)"
);
$testRoleRow = $db->selectOne("SELECT id FROM core_roles WHERE slug = 'rbac_test_viewer'");
$testRoleId = Row::int($testRoleRow, 'id');
$db->execute("INSERT INTO core_role_permissions (role_id, permission_id) VALUES (?, ?)",
    PDOParams::positional([$testRoleId, $viewPermId]));

// --- Regression C fixture: unknown module ownership ---
$db->execute(
    "INSERT IGNORE INTO core_permissions (slug, module, description, scope, module_key, sensitivity, is_active)
     VALUES ('unknownapp.thing.view', 'unknownapp.thing', 'test', 'app', 'unknownapp.thing', 'public', 1)"
);

$superUser      = loadUser($db, $superId);
$recruiterUser  = loadUser($db, $recruiterAssignedId);
$unassignedUser = loadUser($db, $unassignedId);

// Restore any prior module state so we can re-set it inside a try/finally.
$origState = $db->selectOne("SELECT is_enabled FROM core_module_state WHERE module_key = 'recruit'");
$origEnabled = $origState !== null ? Row::int($origState, 'is_enabled') : null;

echo "\nRunning precedence tests...\n\n";

ok('Rule 1: null user denied',
    $authz->allows(null, 'cas.dashboard.access') === false);

$noTenant = clone $superUser;
$noTenant->tenantId = 0;
ok('Rule 2: no tenant denied',
    $authz->allows($noTenant, 'cas.dashboard.access') === false);

ok('Rule 3: unknown permission denied',
    $authz->allows($superUser, 'nonexistent.permission.slug') === false);

// Rule 4: disabled module -> DENY (with try/finally restore)
try {
    $db->execute("INSERT INTO core_module_state (module_key, is_enabled)
                  VALUES ('recruit', 0)
                  ON DUPLICATE KEY UPDATE is_enabled = 0");
    $authz->clearCache();
    ok('Rule 4: disabled recruit denies recruit.* for super_admin',
        $authz->allows($superUser, 'recruit.candidates.view') === false);
} finally {
    if ($origEnabled === null) {
        $db->execute("DELETE FROM core_module_state WHERE module_key = 'recruit'");
    } else {
        $db->execute("UPDATE core_module_state SET is_enabled = ? WHERE module_key = 'recruit'",
            PDOParams::positional([$origEnabled]));
    }
    $authz->clearCache();
}

// Rule 5: policy deny overrides role grant and explicit grant
$createdRoleAssignment5 = false;
$createdExplicitGrant5  = false;
$createdPolicy5         = false;
$createdPolicyId5       = 0;
try {
    // Pre-condition: assigned recruiter with role grant is allowed
    $roleExists5 = $db->scalarInt(
        "SELECT COUNT(*) FROM core_user_roles WHERE user_id = ? AND role_id = ?",
        PDOParams::positional([$recruiterAssignedId, $testRoleId])
    );
    if ($roleExists5 === 0) {
        $db->execute("INSERT INTO core_user_roles (user_id, role_id) VALUES (?, ?)",
            PDOParams::positional([$recruiterAssignedId, $testRoleId]));
        $createdRoleAssignment5 = true;
    }
    $authz->clearCache();
    $recruiterWithRole = loadUser($db, $recruiterAssignedId);

    ok('Rule 5 precondition: role grant allows before policy',
        $authz->allows($recruiterWithRole, 'recruit.candidates.view') === true);

    // Insert deny policy
    $createdPolicyId5 = (int) $db->insert(
        "INSERT INTO core_permission_policies (tenant_id, permission_slug, effect, reason)
         VALUES (?, 'recruit.candidates.view', 'deny', 'rbac-test-fixture')",
        PDOParams::positional([$tenantId])
    );
    $createdPolicy5 = true;
    $authz->clearCache();

    ok('Rule 5a: policy deny overrides role grant',
        $authz->allows($recruiterWithRole, 'recruit.candidates.view') === false);

    // Add explicit grant — policy should still deny
    $grantExists5 = $db->scalarInt(
        "SELECT COUNT(*) FROM core_user_permissions WHERE user_id = ? AND permission_id = ? AND denied = 0",
        PDOParams::positional([$recruiterAssignedId, $viewPermId])
    );
    if ($grantExists5 === 0) {
        $db->execute(
            "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
             VALUES (?, ?, ?, 0)",
            PDOParams::positional([$recruiterAssignedId, $viewPermId, $superId])
        );
        $createdExplicitGrant5 = true;
    }
    $authz->clearCache();

    ok('Rule 5b: policy deny overrides explicit grant',
        $authz->allows($recruiterWithRole, 'recruit.candidates.view') === false);
} finally {
    if ($createdExplicitGrant5) {
        $db->execute(
            "DELETE FROM core_user_permissions WHERE user_id = ? AND permission_id = ? AND denied = 0",
            PDOParams::positional([$recruiterAssignedId, $viewPermId])
        );
    }
    if ($createdRoleAssignment5) {
        $db->execute("DELETE FROM core_user_roles WHERE user_id = ? AND role_id = ?",
            PDOParams::positional([$recruiterAssignedId, $testRoleId]));
    }
    if ($createdPolicy5 && isset($createdPolicyId5) && $createdPolicyId5 > 0) {
        $db->execute("DELETE FROM core_permission_policies WHERE id = ?",
            PDOParams::positional([$createdPolicyId5]));
    }
    $authz->clearCache();
}

// Rule 6: unassigned user, no grant -> DENY
ok('Rule 6: unassigned user denied',
    $authz->allows($unassignedUser, 'recruit.candidates.view') === false);

// Rule 7: explicit denial overrides existing role grant
$db->execute("INSERT INTO core_user_roles (user_id, role_id) VALUES (?, ?)",
    PDOParams::positional([$recruiterAssignedId, $testRoleId]));
$authz->clearCache();
$recruiterWithRole = loadUser($db, $recruiterAssignedId);

// Sanity: role grant alone should allow (this is Rule 9 tested in-line).
ok('Rule 9: role grant alone allows',
    $authz->allows($recruiterWithRole, 'recruit.candidates.view') === true);

// Now insert explicit denial — must override the role grant.
$db->execute(
    "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
     VALUES (?, ?, ?, 1)",
    PDOParams::positional([$recruiterAssignedId, $viewPermId, $superId])
);
$authz->clearCache();
ok('Rule 7: explicit denial overrides existing role grant',
    $authz->allows($recruiterWithRole, 'recruit.candidates.view') === false);

// Remove denial for subsequent tests
$db->execute("DELETE FROM core_user_permissions WHERE user_id = ? AND denied = 1",
    PDOParams::positional([$recruiterAssignedId]));
$authz->clearCache();

// Rule 8: assigned user with explicit grant, no role grant -> ALLOW
$db->execute("DELETE FROM core_user_roles WHERE user_id = ? AND role_id = ?",
    PDOParams::positional([$recruiterAssignedId, $testRoleId]));
$authz->clearCache();
$recruiterBare = loadUser($db, $recruiterAssignedId);

ok('Rule 8 precondition: assigned user without role grant is denied',
    $authz->allows($recruiterBare, 'recruit.candidates.view') === false);

$db->execute(
    "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
     VALUES (?, ?, ?, 0)",
    PDOParams::positional([$recruiterAssignedId, $viewPermId, $superId])
);
$authz->clearCache();
ok('Rule 8: explicit grant allows assigned user',
    $authz->allows($recruiterBare, 'recruit.candidates.view') === true);

$db->execute("DELETE FROM core_user_permissions WHERE user_id = ? AND denied = 0",
    PDOParams::positional([$recruiterAssignedId]));
$authz->clearCache();

// Rule 10 / 10b / 10c
ok('Rule 10: super_admin bypass on public',
    $authz->allows($superUser, 'cas.dashboard.access') === true);

ok('Rule 10b: super_admin bypass blocked on sensitive',
    $authz->allows($superUser, 'cas.rbac.delegate_admin') === false);

// Rule 10c: super_admin bypass blocked on restricted; explicit grant + app assignment bypasses
$payrollPermRow = $db->selectOne("SELECT id FROM core_permissions WHERE slug = 'hr.payroll.approve'");
if ($payrollPermRow === null) {
    skipped('Rule 10c: super_admin bypass blocked on restricted', 'hr.payroll.approve not activated');
} else {
    $payrollPermId = Row::int($payrollPermRow, 'id');

    $createdHrAssignment = false;
    $createdPayrollGrant = false;
    try {
        ok('Rule 10c: super_admin bypass blocked on restricted',
            $authz->allows($superUser, 'hr.payroll.approve') === false);

        $hasHrApp = $db->scalarInt(
            "SELECT COUNT(*) FROM core_user_applications WHERE user_id = ? AND module_key = 'hr'",
            PDOParams::positional([$superId])
        );
        if ($hasHrApp === 0) {
            $db->execute(
                "INSERT INTO core_user_applications (user_id, module_key, is_default) VALUES (?, 'hr', 0)",
                PDOParams::positional([$superId])
            );
            $createdHrAssignment = true;
        }

        $grantExists = $db->scalarInt(
            "SELECT COUNT(*) FROM core_user_permissions WHERE user_id = ? AND permission_id = ? AND denied = 0",
            PDOParams::positional([$superId, $payrollPermId])
        );
        if ($grantExists === 0) {
            $db->execute(
                "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
                 VALUES (?, ?, ?, 0)",
                PDOParams::positional([$superId, $payrollPermId, $superId])
            );
            $createdPayrollGrant = true;
        }

        $authz->clearCache();

        ok('Rule 10c-corollary: explicit grant + app assignment allows restricted',
            $authz->allows($superUser, 'hr.payroll.approve') === true);
    } finally {
        if ($createdPayrollGrant) {
            $db->execute(
                "DELETE FROM core_user_permissions WHERE user_id = ? AND permission_id = ? AND denied = 0",
                PDOParams::positional([$superId, $payrollPermId])
            );
        }
        if ($createdHrAssignment) {
            $db->execute(
                "DELETE FROM core_user_applications WHERE user_id = ? AND module_key = 'hr'",
                PDOParams::positional([$superId])
            );
        }
        $authz->clearCache();
    }
}

// Rule 11: default deny — user with no grants, no roles, on a global permission
ok('Rule 11: default deny',
    $authz->allows($recruiterBare, 'cas.users.manage') === false);

// --- Regression A: explicit grant cannot bypass app assignment ---
$db->execute(
    "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
     VALUES (?, ?, ?, 0)",
    PDOParams::positional([$unassignedId, $viewPermId, $superId])
);
$authz->clearCache();
ok('Regression A: explicit grant cannot bypass application assignment',
    $authz->allows($unassignedUser, 'recruit.candidates.view') === false);
$db->execute("DELETE FROM core_user_permissions WHERE user_id = ? AND denied = 0",
    PDOParams::positional([$unassignedId]));
$authz->clearCache();

// --- Regression B: disabled application denies even for assigned user ---
try {
    $db->execute("INSERT INTO core_module_state (module_key, is_enabled)
                  VALUES ('recruit', 0)
                  ON DUPLICATE KEY UPDATE is_enabled = 0");
    $authz->clearCache();
    // Grant to make sure it's not the grant path that saves them
    $db->execute(
        "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
         VALUES (?, ?, ?, 0)",
        PDOParams::positional([$recruiterAssignedId, $viewPermId, $superId])
    );
    $authz->clearCache();
    ok('Regression B: disabled application denies even with explicit grant',
        $authz->allows($recruiterBare, 'recruit.candidates.view') === false);
} finally {
    if ($origEnabled === null) {
        $db->execute("DELETE FROM core_module_state WHERE module_key = 'recruit'");
    } else {
        $db->execute("UPDATE core_module_state SET is_enabled = ? WHERE module_key = 'recruit'",
            PDOParams::positional([$origEnabled]));
    }
    $db->execute("DELETE FROM core_user_permissions WHERE user_id = ? AND denied = 0",
        PDOParams::positional([$recruiterAssignedId]));
    $authz->clearCache();
}

// --- Regression C: unknown module ownership fails closed ---
$db->execute(
    "INSERT INTO core_user_permissions (user_id, permission_id, granted_by, denied)
     SELECT ?, id, ?, 0 FROM core_permissions WHERE slug = 'unknownapp.thing.view'",
    PDOParams::positional([$recruiterAssignedId, $superId])
);
$authz->clearCache();
ok('Regression C: unknown ownership fails closed',
    $authz->allows($recruiterBare, 'unknownapp.thing.view') === false);
$db->execute("DELETE FROM core_user_permissions WHERE user_id = ? AND denied = 0",
    PDOParams::positional([$recruiterAssignedId]));
$authz->clearCache();

// --- Regression D: global permissions do not require app assignment ---
ok('Regression D: cas.* works without app assignment',
    $authz->allows($superUser, 'cas.dashboard.access') === true);

echo "\nCleaning up fixtures...\n";
$db->execute("DELETE FROM core_role_permissions WHERE role_id = ?", PDOParams::positional([$testRoleId]));
$db->execute("DELETE FROM core_user_roles WHERE user_id IN (?, ?, ?)",
    PDOParams::positional([$superId, $recruiterAssignedId, $unassignedId]));
$db->execute("DELETE FROM core_user_applications WHERE user_id IN (?, ?, ?)",
    PDOParams::positional([$superId, $recruiterAssignedId, $unassignedId]));
$db->execute("DELETE FROM core_user_permissions WHERE user_id IN (?, ?, ?)",
    PDOParams::positional([$superId, $recruiterAssignedId, $unassignedId]));
$db->execute("DELETE FROM core_roles WHERE id = ?", PDOParams::positional([$testRoleId]));
$db->execute("DELETE FROM core_permissions WHERE slug = 'unknownapp.thing.view'");
$db->execute("DELETE FROM core_users WHERE id IN (?, ?, ?)",
    PDOParams::positional([$superId, $recruiterAssignedId, $unassignedId]));

echo "\n============================================\n";
echo "  PASS: $pass\n";
echo "  FAIL: $fail\n";
echo "  SKIP: $skip\n";
if ($fail > 0) {
    echo "  Failed tests:\n";
    foreach ($failures as $f) { echo "    - $f\n"; }
}
echo "============================================\n";

exit($fail > 0 ? 1 : 0);