<?php
declare(strict_types=1);

ob_start();

register_shutdown_function(static function (): void {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});

require dirname(__DIR__) . '/vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\LiveSearch\LiveSearchRegistry;
use CorporateAid\Platform\LiveSearch\LiveSearchService;
use CorporateAid\Platform\Repositories\UserRepository;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }

    echo "[PASS] {$message}\n";
}

function insert(PDO $pdo, string $sql, array $values): int
{
    $statement = $pdo->prepare($sql);

    if (!$statement->execute($values)) {
        $error = $statement->errorInfo();

        throw new RuntimeException(
            'Database insert failed: ' . ($error[2] ?? 'Unknown database error')
        );
    }

    return (int) $pdo->lastInsertId();
}

function contactCode(int $number): string
{
    return 'CNT-' . str_pad(
        (string) $number,
        6,
        '0',
        STR_PAD_LEFT
    );
}

$app = null;
$pdo = null;
$transactionStarted = false;
$exitCode = 0;

try {
    $app = App::boot(dirname(__DIR__));

    $container = $app->container();
    $db = $container->get(Database::class);
    $pdo = $db->pdo();

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tenantContext = $container->get(TenantContext::class);
    $auth = $container->get(Auth::class);
    $users = $container->get(UserRepository::class);
    $search = $container->get(LiveSearchService::class);

    check(true, 'Application and LiveSearch services booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);
    $marker = 'TST' . $suffix;
    $codeBase = random_int(100000, 999996);

    $tenantOne = 1;

    // Generate four contact codes that do not already exist.
    // Contact codes have a global unique index across all tenants.
    do {
        $contactCodeBase = random_int(100000, 999996);

        $contactCodes = [
            contactCode($contactCodeBase),
            contactCode($contactCodeBase + 1),
            contactCode($contactCodeBase + 2),
            contactCode($contactCodeBase + 3),
        ];

        $codeCheck = $pdo->prepare(
            "SELECT COUNT(*)
             FROM recruit_client_contacts
             WHERE code IN (?, ?, ?, ?)"
        );

        $codeCheck->execute($contactCodes);
        $existingContactCodes = (int) $codeCheck->fetchColumn();

    } while ($existingContactCodes > 0);

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        ['TST-' . $suffix, 'Temporary Isolation Test ' . $suffix]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    $userOneId = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantOne,
            'Isolation Test One',
            'isolation-one-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    $userTwoId = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantTwo,
            'Isolation Test Two',
            'isolation-two-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    $roleStatement = $pdo->prepare(
        "SELECT id FROM core_roles WHERE slug = 'recruiter' LIMIT 1"
    );
    $roleStatement->execute();
    $recruiterRoleId = (int) $roleStatement->fetchColumn();

    check($recruiterRoleId > 0, 'Existing Recruiter role found.');

    foreach ([$userOneId, $userTwoId] as $userId) {
        $pdo->prepare(
            "INSERT INTO core_user_roles (user_id, role_id)
             VALUES (?, ?)"
        )->execute([$userId, $recruiterRoleId]);

        $pdo->prepare(
            "INSERT INTO core_user_applications
                (user_id, module_key, is_default)
             VALUES (?, 'recruit', 1)"
        )->execute([$userId]);
    }

    check(true, 'Temporary users assigned the existing Recruiter role.');

    $orgIds = [];

    foreach ([$tenantOne, $tenantTwo] as $tenantId) {
        $orgIds[$tenantId] = insert(
            $pdo,
            "INSERT INTO recruit_client_organisations
                (code, name, status, tenant_id)
             VALUES (?, ?, 'active', ?)",
            [
                'ORG-' . $suffix . '-' . $tenantId,
                $marker . '-ORG-TENANT-' . $tenantId,
                $tenantId,
            ]
        );
    }

    foreach ([$tenantOne, $tenantTwo] as $tenantId) {
        insert(
            $pdo,
            "INSERT INTO recruit_candidates
                (code, first_name, last_name, email, status, tenant_id)
             VALUES (?, ?, ?, ?, 'active', ?)",
            [
                'CAN-' . $suffix . '-' . $tenantId,
                $marker . 'Candidate' . $tenantId,
                'Isolation',
                'candidate-' . $suffix . '-' . $tenantId . '@example.test',
                $tenantId,
            ]
        );
    }

    $contactIds = [];

    foreach ([$tenantOne, $tenantTwo] as $tenantId) {
        $otherTenantId = $tenantId === $tenantOne
            ? $tenantTwo
            : $tenantOne;

        $contactOffset = $tenantId === $tenantOne ? 0 : 2;

        $validContactId = insert(
            $pdo,
            "INSERT INTO recruit_client_contacts
                (client_organisation_id, code, first_name, last_name, tenant_id)
             VALUES (?, ?, ?, ?, ?)",
            [
                $orgIds[$tenantId],
                $contactCodes[$contactOffset],
                $marker . 'Contact' . $tenantId,
                'Valid',
                $tenantId,
            ]
        );

        $crossContactId = insert(
            $pdo,
            "INSERT INTO recruit_client_contacts
                (client_organisation_id, code, first_name, last_name, tenant_id)
             VALUES (?, ?, ?, ?, ?)",
            [
                $orgIds[$otherTenantId],
                $contactCodes[$contactOffset + 1],
                $marker . 'Contact' . $tenantId,
                'CrossTenant',
                $tenantId,
            ]
        );

        $contactIds[$tenantId] = [
            'valid' => $validContactId,
            'cross' => $crossContactId,
        ];

        // Deliberately assign a role belonging to the other tenant.
        $pdo->prepare(
            "INSERT INTO recruit_contact_roles
                (contact_id, role_slug, tenant_id)
             VALUES (?, ?, ?)"
        )->execute([
            $validContactId,
            'cross-tenant-test-role',
            $otherTenantId,
        ]);

        insert(
            $pdo,
            "INSERT INTO recruit_engagements
                (code, client_organisation_id, name, service_type, status, tenant_id)
             VALUES (?, ?, ?, '3_month', 'active', ?)",
            [
                'ENG-' . str_pad(
                    (string) (
                        $codeBase + ($tenantId === $tenantOne ? 0 : 1)
                    ),
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                $orgIds[$tenantId],
                $marker . 'Engagement' . $tenantId,
                $tenantId,
            ]
        );

        insert(
            $pdo,
            "INSERT INTO recruit_engagements
                (code, client_organisation_id, name, service_type, status, tenant_id)
             VALUES (?, ?, ?, '3_month', 'active', ?)",
            [
                'ENG-' . str_pad(
                    (string) (
                        $codeBase + ($tenantId === $tenantOne ? 2 : 3)
                    ),
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                $orgIds[$otherTenantId],
                $marker . 'Engagement' . $tenantId . 'CrossTenant',
                $tenantId,
            ]
        );

        insert(
            $pdo,
            "INSERT INTO recruit_vacancies
                (code, client_organisation_id, title, status, tenant_id)
             VALUES (?, ?, ?, 'open', ?)",
            [
                'VAC-' . str_pad(
                    (string) (
                        $codeBase + ($tenantId === $tenantOne ? 0 : 1)
                    ),
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                $orgIds[$tenantId],
                $marker . 'Vacancy' . $tenantId,
                $tenantId,
            ]
        );

        insert(
            $pdo,
            "INSERT INTO recruit_vacancies
                (code, client_organisation_id, title, status, tenant_id)
             VALUES (?, ?, ?, 'open', ?)",
            [
                'VAC-' . str_pad(
                    (string) (
                        $codeBase + ($tenantId === $tenantOne ? 2 : 3)
                    ),
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                $orgIds[$otherTenantId],
                $marker . 'Vacancy' . $tenantId . 'CrossTenant',
                $tenantId,
            ]
        );
    }

    check(true, 'Temporary test fixtures created for both tenants.');

    $testCases = [
        'candidates' => 'Candidate',
        'organisations' => 'ORG',
        'contacts' => 'Contact',
        'engagements' => 'Engagement',
        'vacancies' => 'Vacancy',
    ];

    $userIds = [
        $tenantOne => $userOneId,
        $tenantTwo => $userTwoId,
    ];

    foreach ($userIds as $tenantId => $userId) {
        $tenantId = (int) $tenantId;

        $tenantContext->set($tenantId);

        $user = $users->findById((int) $userId);

        check(
            $user !== null,
            'Test user loaded for tenant ' . $tenantId . '.'
        );

        $users->loadRoles($user);
        $users->loadPermissions($user);

        $auth->login($user);

        check(
            $tenantContext->id() === $tenantId,
            'Authenticated session uses tenant ' . $tenantId . '.'
        );

        foreach ($testCases as $slug => $label) {
            $repository = LiveSearchRegistry::resolve($slug);

            check(
                $repository !== null,
                'LiveSearch repository resolved: ' . $slug . '.'
            );

            $config = $repository->liveSearchConfig();

            $result = $search->run($config, $marker, 1, 100);

            check(
                (int) $result['total'] === 1,
                $slug . ' returns exactly one matching record for tenant '
                    . $tenantId . '.'
            );

            $html = implode(
                "\n",
                array_map(
                    static fn(array $row): string =>
                        (string) ($row['html'] ?? ''),
                    $result['rows']
                )
            );

            check(
                str_contains($html, $marker),
                $slug . ' renders the expected tenant-owned record.'
            );

            if ($slug === 'contacts') {
                check(
                    !str_contains($html, 'cross-tenant-test-role'),
                    'Contact role join excludes the mismatched tenant role.'
                );
            }
        }
    }

    echo "\nAll tenant-isolation checks passed.\n";
} catch (Throwable $e) {
    echo "\n[FAIL] " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    $exitCode = 1;
} finally {
    if ($app !== null) {
        try {
            $app->container()->get(TenantContext::class)->clear();
        } catch (Throwable) {
            // Preserve the original test result.
        }
    }

    if (
        $transactionStarted
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
        echo "[CLEANUP] Test transaction rolled back.\n";
    }
}

if ($exitCode !== 0) {
    exit(1);
}
