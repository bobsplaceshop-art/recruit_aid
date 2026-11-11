<?php
declare(strict_types=1);

/**
 * Seed and reclassify users for the four-axis identity model.
 *
 * Development install bootstrap. Idempotent — safe to re-run.
 *
 * What it does:
 *   1. Audit: print current user, role, and platform state.
 *   2. Reclassify user 1 (was cas_admin, no tenant role):
 *        → tenant-admin@recruitaid.test with tenant-1 "admin" role.
 *        → cas_admin platform role removed (moves to new cas-admin user).
 *   3. Rename user 2 (researcher@recruitaid.test):
 *        → tenant-researcher@recruitaid.test (role unchanged).
 *   4. Soft-delete user 3 (hr@recruitaid.test, no roles, orphaned).
 *   5. Seed cas-admin@recruitaid.test with cas_admin platform role.
 *   6. Seed cas-developer@recruitaid.test with cas_developer platform role
 *      AND a platform_owners row (contractual owner designation).
 *
 * Temp passwords are randomly generated and printed once at the end.
 * They are NOT stored anywhere except as Argon2id hashes in the DB.
 * Save them to a password manager before closing the terminal.
 *
 * Production installs should NOT run this script as-is. Real installs
 * bootstrap their own platform operators via a dedicated onboarding
 * flow (out of scope today).
 *
 * Run: php bin/seed-platform-identities.php
 */

require __DIR__ . '/../vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\Hasher;

App::boot(dirname(__DIR__));
$db = App::instance()->container()->get(Database::class);

function banner(string $msg): void {
    echo "\n" . str_repeat('=', 70) . "\n" . $msg . "\n" . str_repeat('=', 70) . "\n";
}

function randomPassword(int $len = 18): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

// ---------------------------------------------------------------------
// PHASE 1 — AUDIT
// ---------------------------------------------------------------------
banner('PHASE 1 — CURRENT STATE');

$users = $db->select(
    "SELECT u.id, u.tenant_id, u.name, u.email, u.status,
            (SELECT GROUP_CONCAT(r.slug) FROM core_user_roles ur
             JOIN core_roles r ON r.id = ur.role_id
             WHERE ur.user_id = u.id) AS tenant_roles,
            (SELECT GROUP_CONCAT(pr.slug) FROM core_user_platform_roles upr
             JOIN core_platform_roles pr ON pr.id = upr.role_id
             WHERE upr.user_id = u.id) AS platform_roles
     FROM core_users u
     WHERE u.deleted_at IS NULL
     ORDER BY u.id"
);
echo "\nUsers (active):\n";
foreach ($users as $u) {
    printf("  [%d] tenant=%d  %-30s  %-40s  tenant_roles=%s  platform_roles=%s\n",
        Row::int($u, 'id'),
        Row::int($u, 'tenant_id'),
        Row::str($u, 'name'),
        Row::str($u, 'email'),
        Row::nullableStr($u, 'tenant_roles') ?? '(none)',
        Row::nullableStr($u, 'platform_roles') ?? '(none)'
    );
}

$owners = $db->select("SELECT user_id, designation FROM platform_owners");
echo "\nPlatform owners: " . (empty($owners) ? '(none)' : count($owners) . ' designated') . "\n";
foreach ($owners as $o) {
    echo "  user_id=" . Row::int($o, 'user_id') . " designation='" . Row::str($o, 'designation') . "'\n";
}

$tenantCount = $db->scalarInt("SELECT COUNT(*) FROM core_tenants WHERE deleted_at IS NULL");
echo "\nActive tenants: {$tenantCount}\n";

// ---------------------------------------------------------------------
// PHASE 2 — RECLASSIFY EXISTING USERS
// ---------------------------------------------------------------------
banner('PHASE 2 — RECLASSIFY EXISTING USERS');

// User 1: was cas_admin with no tenant role. Reclassify as tenant-1 admin.
$user1 = $db->selectOne(
    "SELECT id, email, name FROM core_users WHERE email = 'admin@recruitaid.test' AND deleted_at IS NULL LIMIT 1"
);
if ($user1 !== null) {
    $user1Id = Row::int($user1, 'id');
    echo "\nUser 1 (id={$user1Id}):\n";

    // Rename.
    $db->execute(
        "UPDATE core_users SET name = ?, email = ? WHERE id = ?",
        PDOParams::positional(['Tenant 1 Admin', 'tenant-admin@recruitaid.test', $user1Id])
    );
    echo "  → renamed to 'Tenant 1 Admin' <tenant-admin@recruitaid.test>\n";

    // Remove cas_admin platform role.
    $removed = $db->execute(
        "DELETE FROM core_user_platform_roles WHERE user_id = ?",
        PDOParams::positional([$user1Id])
    );
    echo "  → removed {$removed} platform role assignment(s)\n";

    // Add tenant-1 admin role.
    $tenantAdminRole = $db->selectOne(
        "SELECT id FROM core_roles WHERE tenant_id = 1 AND slug = 'admin' AND module_key = 'recruit' LIMIT 1"
    );
    if ($tenantAdminRole === null) {
        echo "  ⚠ tenant-1 'admin' role not found — user 1 has no tenant role\n";
    } else {
        $roleId = Row::int($tenantAdminRole, 'id');
        $exists = $db->scalarInt(
            "SELECT COUNT(*) FROM core_user_roles WHERE tenant_id = 1 AND user_id = ? AND role_id = ?",
            PDOParams::positional([$user1Id, $roleId])
        );
        if ($exists === 0) {
            $db->execute(
                "INSERT INTO core_user_roles (tenant_id, user_id, role_id, assigned_by) VALUES (1, ?, ?, ?)",
                PDOParams::positional([$user1Id, $roleId, $user1Id])
            );
            echo "  → added tenant-1 'admin' role\n";
        } else {
            echo "  → tenant-1 'admin' role already assigned\n";
        }
    }
} else {
    echo "\nUser 1 (admin@recruitaid.test) not found — skipping (already renamed?)\n";
}

// User 2: rename only.
$user2 = $db->selectOne(
    "SELECT id, email, name FROM core_users WHERE email = 'researcher@recruitaid.test' AND deleted_at IS NULL LIMIT 1"
);
if ($user2 !== null) {
    $user2Id = Row::int($user2, 'id');
    echo "\nUser 2 (id={$user2Id}):\n";
    $db->execute(
        "UPDATE core_users SET name = ?, email = ? WHERE id = ?",
        PDOParams::positional(['Tenant 1 Researcher', 'tenant-researcher@recruitaid.test', $user2Id])
    );
    echo "  → renamed to 'Tenant 1 Researcher' <tenant-researcher@recruitaid.test>\n";
    echo "  → tenant role unchanged\n";
} else {
    echo "\nUser 2 (researcher@recruitaid.test) not found — skipping\n";
}

// User 3: soft-delete.
$user3 = $db->selectOne(
    "SELECT id, email FROM core_users WHERE email = 'hr@recruitaid.test' AND deleted_at IS NULL LIMIT 1"
);
if ($user3 !== null) {
    $user3Id = Row::int($user3, 'id');
    echo "\nUser 3 (id={$user3Id}):\n";
    $db->execute(
        "UPDATE core_users SET status = 'disabled', deleted_at = NOW() WHERE id = ?",
        PDOParams::positional([$user3Id])
    );
    echo "  → soft-deleted (orphaned user with no roles)\n";
} else {
    echo "\nUser 3 (hr@recruitaid.test) not found — skipping (already deleted?)\n";
}

// ---------------------------------------------------------------------
// PHASE 3 — SEED PLATFORM IDENTITIES
// ---------------------------------------------------------------------
banner('PHASE 3 — SEED PLATFORM IDENTITIES');

$newPasswords = [];

// cas-admin@recruitaid.test
$casAdminEmail = 'cas-admin@recruitaid.test';
$existing = $db->selectOne(
    "SELECT id FROM core_users WHERE email = ? AND deleted_at IS NULL LIMIT 1",
    PDOParams::positional([$casAdminEmail])
);
if ($existing === null) {
    $pw = randomPassword();
    $newPasswords[$casAdminEmail] = $pw;
    $hash = Hasher::hash($pw);

    $userId = $db->insert(
        "INSERT INTO core_users (tenant_id, name, email, password_hash, status)
         VALUES (1, 'CAS Admin (Platform Operator)', ?, ?, 'active')",
        PDOParams::positional([$casAdminEmail, $hash])
    );

    $roleRow = $db->selectOne(
        "SELECT id FROM core_platform_roles WHERE slug = 'cas_admin' LIMIT 1"
    );
    if ($roleRow === null) {
        echo "  ⚠ cas_admin platform role not found — aborting\n";
    } else {
        $db->execute(
            "INSERT INTO core_user_platform_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)",
            PDOParams::positional([$userId, Row::int($roleRow, 'id'), $userId])
        );
        echo "\n  + Created {$casAdminEmail} (user_id={$userId}) with cas_admin role\n";
    }
} else {
    echo "\n  {$casAdminEmail} already exists — skipping\n";
}

// cas-developer@recruitaid.test
$casDevEmail = 'cas-developer@recruitaid.test';
$existing = $db->selectOne(
    "SELECT id FROM core_users WHERE email = ? AND deleted_at IS NULL LIMIT 1",
    PDOParams::positional([$casDevEmail])
);
if ($existing === null) {
    $pw = randomPassword();
    $newPasswords[$casDevEmail] = $pw;
    $hash = Hasher::hash($pw);

    $userId = $db->insert(
        "INSERT INTO core_users (tenant_id, name, email, password_hash, status)
         VALUES (1, 'CAS Developer (Platform Owner)', ?, ?, 'active')",
        PDOParams::positional([$casDevEmail, $hash])
    );

    $roleRow = $db->selectOne(
        "SELECT id FROM core_platform_roles WHERE slug = 'cas_developer' LIMIT 1"
    );
    if ($roleRow === null) {
        echo "  ⚠ cas_developer platform role not found\n";
    } else {
        $db->execute(
            "INSERT INTO core_user_platform_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)",
            PDOParams::positional([$userId, Row::int($roleRow, 'id'), $userId])
        );

        $db->execute(
            "INSERT INTO platform_owners (user_id, designation, contract_reference) VALUES (?, 'Founder / Lead Developer', 'INTERNAL-2026-001')",
            PDOParams::positional([$userId])
        );
        echo "\n  + Created {$casDevEmail} (user_id={$userId}) with cas_developer role + platform_owners row\n";
    }
} else {
    echo "\n  {$casDevEmail} already exists — skipping\n";
}

// ---------------------------------------------------------------------
// PHASE 4 — FINAL STATE
// ---------------------------------------------------------------------
banner('PHASE 4 — FINAL STATE');

$final = $db->select(
    "SELECT u.id, u.tenant_id, u.name, u.email, u.status,
            (SELECT GROUP_CONCAT(r.slug) FROM core_user_roles ur
             JOIN core_roles r ON r.id = ur.role_id
             WHERE ur.user_id = u.id) AS tenant_roles,
            (SELECT GROUP_CONCAT(pr.slug) FROM core_user_platform_roles upr
             JOIN core_platform_roles pr ON pr.id = upr.role_id
             WHERE upr.user_id = u.id) AS platform_roles
     FROM core_users u
     WHERE u.deleted_at IS NULL
     ORDER BY u.id"
);
echo "\nActive users:\n";
foreach ($final as $u) {
    printf("  [%d] tenant=%d  %-30s  %-40s  tenant=%s  platform=%s\n",
        Row::int($u, 'id'),
        Row::int($u, 'tenant_id'),
        Row::str($u, 'name'),
        Row::str($u, 'email'),
        Row::nullableStr($u, 'tenant_roles') ?? '-',
        Row::nullableStr($u, 'platform_roles') ?? '-'
    );
}

$owners = $db->select("SELECT user_id, designation FROM platform_owners");
echo "\nPlatform owners:\n";
foreach ($owners as $o) {
    echo "  user_id=" . Row::int($o, 'user_id') . "  " . Row::str($o, 'designation') . "\n";
}

// ---------------------------------------------------------------------
// PHASE 5 — TEMP PASSWORDS (only if new users were created)
// ---------------------------------------------------------------------
if (!empty($newPasswords)) {
    banner('TEMPORARY PASSWORDS (SAVE NOW — shown only once)');
    foreach ($newPasswords as $email => $pw) {
        echo "\n  Email:    {$email}\n";
        echo "  Password: {$pw}\n";
    }
    echo "\n";
    echo "  These passwords are stored ONLY as Argon2id hashes in the DB.\n";
    echo "  Copy them to a password manager before closing this terminal.\n";
    echo "  There is no recovery path short of resetting the hash in the DB.\n";
} else {
    banner('NO NEW PASSWORDS GENERATED');
    echo "\n  All platform identities already existed. Nothing to display.\n";
}

echo "\nDone.\n";
