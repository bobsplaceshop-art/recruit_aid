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
use CorporateAid\Core\Database;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Repositories\CandidateRepository;

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
    $statement->execute($values);

    return (int) $pdo->lastInsertId();
}

function scalar(PDO $pdo, string $sql, array $values = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    return $statement->fetchColumn();
}

function expectException(
    callable $callback,
    string $exceptionClass,
    string $message
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        check(
            $exception instanceof $exceptionClass,
            $message . ' (correct exception type)'
        );
        return;
    }

    throw new RuntimeException($message . ' (no exception thrown)');
}

$app = null;
$pdo = null;
$tenantContext = null;
$transactionStarted = false;
$exitCode = 0;

try {
    $app = App::boot(dirname(__DIR__));

    $container = $app->container();
    $db = $container->get(Database::class);
    $pdo = $db->pdo();

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tenantContext = $container->get(TenantContext::class);
    $repository = $container->get(CandidateRepository::class);

    check(true, 'Application and CandidateRepository booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);
    $marker = 'CRT' . $suffix;

    // Tenant 1 is the existing default tenant.
    $tenantOne = 1;

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'CRT-' . $suffix,
            'Candidate Repository Test ' . $suffix,
        ]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    $userOneId = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantOne,
            'Candidate Test User One',
            'candidate-test-one-' . $suffix . '@example.test',
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
            'Candidate Test User Two',
            'candidate-test-two-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    check(true, 'Temporary users created in both tenants.');

    $candidateOneCode = 'CRT-C1-' . $suffix;
    $candidateTwoCode = 'CRT-C2-' . $suffix;

    $candidateOneEmail = 'candidate-one-' . $suffix . '@example.test';
    $candidateTwoEmail = 'candidate-two-' . $suffix . '@example.test';

    $candidateOneLinkedin = 'https://www.linkedin.com/in/crt-one-' . $suffix;
    $candidateTwoLinkedin = 'https://www.linkedin.com/in/crt-two-' . $suffix;

    $candidateOneId = insert(
        $pdo,
        "INSERT INTO recruit_candidates
            (code, first_name, last_name, email, linkedin_url, status, tenant_id)
         VALUES (?, ?, ?, ?, ?, 'active', ?)",
        [
            $candidateOneCode,
            $marker . 'One',
            'Isolation',
            $candidateOneEmail,
            $candidateOneLinkedin,
            $tenantOne,
        ]
    );

    $candidateTwoId = insert(
        $pdo,
        "INSERT INTO recruit_candidates
            (code, first_name, last_name, email, linkedin_url, status, tenant_id)
         VALUES (?, ?, ?, ?, ?, 'active', ?)",
        [
            $candidateTwoCode,
            $marker . 'Two',
            'Isolation',
            $candidateTwoEmail,
            $candidateTwoLinkedin,
            $tenantTwo,
        ]
    );

    check(true, 'Candidate fixtures created in both tenants.');

    // Seed child records for tenant two.
    insert(
        $pdo,
        "INSERT INTO recruit_candidate_skills
            (candidate_id, skill_name, tenant_id)
         VALUES (?, ?, ?)",
        [$candidateTwoId, 'Tenant Two Skill', $tenantTwo]
    );

    insert(
        $pdo,
        "INSERT INTO recruit_candidate_experience
            (candidate_id, job_title, company, tenant_id)
         VALUES (?, ?, ?, ?)",
        [$candidateTwoId, 'Tenant Two Role', 'Tenant Two Company', $tenantTwo]
    );

    insert(
        $pdo,
        "INSERT INTO recruit_candidate_education
            (candidate_id, institution, tenant_id)
         VALUES (?, ?, ?)",
        [$candidateTwoId, 'Tenant Two Institution', $tenantTwo]
    );

    insert(
        $pdo,
        "INSERT INTO recruit_candidate_tags
            (candidate_id, tag, tenant_id)
         VALUES (?, ?, ?)",
        [$candidateTwoId, 'tenant-two-tag', $tenantTwo]
    );

    check(true, 'Tenant two child-record fixtures created.');

    // ---------------------------------------------------------
    // TENANT ONE: candidate lookup isolation
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    check(
        $repository->findById($candidateOneId) !== null,
        'Tenant one can find its own candidate by ID.'
    );

    check(
        $repository->findById($candidateTwoId) === null,
        'Tenant one cannot find tenant two candidate by ID.'
    );

    check(
        $repository->findByCode($candidateOneCode) !== null,
        'Tenant one can find its own candidate by code.'
    );

    check(
        $repository->findByCode($candidateTwoCode) === null,
        'Tenant one cannot find tenant two candidate by code.'
    );

    check(
        $repository->findByEmail($candidateTwoEmail) === null,
        'Tenant one cannot find tenant two candidate by email.'
    );

    check(
        $repository->findByLinkedin($candidateTwoLinkedin) === null,
        'Tenant one cannot find tenant two candidate by LinkedIn URL.'
    );

    $page = $repository->paginate([
        'search' => $marker,
    ]);

    check(
        count($page['rows']) === 1
        && $page['rows'][0]->id === $candidateOneId,
        'Tenant one pagination returns only its matching candidate.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: cross-tenant mutation protection
    // ---------------------------------------------------------

    expectException(
        static fn() => $repository->update(
            $candidateTwoId,
            ['first_name' => 'Unauthorized Change']
        ),
        RuntimeException::class,
        'Cross-tenant candidate update is rejected.'
    );

    $repository->softDelete($candidateTwoId);

    check(
        scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_candidates WHERE id = ?",
            [$candidateTwoId]
        ) === false
        || scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_candidates WHERE id = ?",
            [$candidateTwoId]
        ) === null,
        'Cross-tenant soft-delete does not change the candidate.'
    );

    $pdo->prepare(
        "UPDATE recruit_candidates SET deleted_at = '2026-01-01 00:00:00'
         WHERE id = ? AND tenant_id = ?"
    )->execute([$candidateTwoId, $tenantTwo]);

    $repository->restore($candidateTwoId);

    check(
        scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_candidates WHERE id = ?",
            [$candidateTwoId]
        ) === '2026-01-01 00:00:00',
        'Cross-tenant restore does not change the candidate.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: child-record isolation
    // ---------------------------------------------------------

    check(
        $repository->skillsFor($candidateTwoId) === [],
        'Tenant one cannot read tenant two candidate skills.'
    );

    check(
        $repository->experienceFor($candidateTwoId) === [],
        'Tenant one cannot read tenant two candidate experience.'
    );

    check(
        $repository->educationFor($candidateTwoId) === [],
        'Tenant one cannot read tenant two candidate education.'
    );

    check(
        $repository->tagsFor($candidateTwoId) === [],
        'Tenant one cannot read tenant two candidate tags.'
    );

    expectException(
        static fn() => $repository->syncSkills(
            $candidateTwoId,
            [['skill_name' => 'Unauthorized Skill']]
        ),
        RuntimeException::class,
        'Cross-tenant skill synchronisation is rejected.'
    );

    expectException(
        static fn() => $repository->syncExperience(
            $candidateTwoId,
            [[
                'job_title' => 'Unauthorized Role',
                'company' => 'Unauthorized Company',
            ]]
        ),
        RuntimeException::class,
        'Cross-tenant experience synchronisation is rejected.'
    );

    expectException(
        static fn() => $repository->syncEducation(
            $candidateTwoId,
            [['institution' => 'Unauthorized Institution']]
        ),
        RuntimeException::class,
        'Cross-tenant education synchronisation is rejected.'
    );

    expectException(
        static fn() => $repository->syncTags(
            $candidateTwoId,
            ['unauthorized-tag']
        ),
        RuntimeException::class,
        'Cross-tenant tag synchronisation is rejected.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_skills
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateTwoId, $tenantTwo]
        ) === 1,
        'Rejected cross-tenant synchronisation leaves tenant two skills unchanged.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_experience
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateTwoId, $tenantTwo]
        ) === 1,
        'Rejected cross-tenant synchronisation leaves tenant two experience unchanged.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_education
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateTwoId, $tenantTwo]
        ) === 1,
        'Rejected cross-tenant synchronisation leaves tenant two education unchanged.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_tags
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateTwoId, $tenantTwo]
        ) === 1,
        'Rejected cross-tenant synchronisation leaves tenant two tags unchanged.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: child-record creation and tenant assignment
    // ---------------------------------------------------------

    $repository->syncSkills(
        $candidateOneId,
        [['skill_name' => 'Tenant One Skill']]
    );

    $repository->syncExperience(
        $candidateOneId,
        [[
            'job_title' => 'Tenant One Role',
            'company' => 'Tenant One Company',
        ]]
    );

    $repository->syncEducation(
        $candidateOneId,
        [['institution' => 'Tenant One Institution']]
    );

    $repository->syncTags(
        $candidateOneId,
        ['tenant-one-tag']
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_skills
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateOneId, $tenantOne]
        ) === 1,
        'Skill synchronisation writes the active tenant ID.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_experience
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateOneId, $tenantOne]
        ) === 1,
        'Experience synchronisation writes the active tenant ID.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_education
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateOneId, $tenantOne]
        ) === 1,
        'Education synchronisation writes the active tenant ID.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_candidate_tags
             WHERE candidate_id = ? AND tenant_id = ?",
            [$candidateOneId, $tenantOne]
        ) === 1,
        'Tag synchronisation writes the active tenant ID.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: recruiter and creator ownership validation
    // ---------------------------------------------------------

    expectException(
        static fn() => $repository->create([
            'code' => 'CRT-INVALID-R-' . $suffix,
            'first_name' => 'Invalid',
            'last_name' => 'Recruiter',
            'email' => 'invalid-recruiter-' . $suffix . '@example.test',
            'recruiter_id' => $userTwoId,
        ]),
        InvalidArgumentException::class,
        'Candidate creation rejects a recruiter from another tenant.'
    );

    expectException(
        static fn() => $repository->create([
            'code' => 'CRT-INVALID-C-' . $suffix,
            'first_name' => 'Invalid',
            'last_name' => 'Creator',
            'email' => 'invalid-creator-' . $suffix . '@example.test',
            'created_by' => $userTwoId,
        ]),
        InvalidArgumentException::class,
        'Candidate creation rejects a creator from another tenant.'
    );

    $createdCandidateId = $repository->create([
        'code' => 'CRT-VALID-' . $suffix,
        'first_name' => 'Valid',
        'last_name' => 'Candidate',
        'email' => 'valid-candidate-' . $suffix . '@example.test',
        'recruiter_id' => $userOneId,
        'created_by' => $userOneId,
    ]);

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_candidates WHERE id = ?",
            [$createdCandidateId]
        ) === $tenantOne,
        'Candidate creation assigns the active tenant ID.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT recruiter_id FROM recruit_candidates WHERE id = ?",
            [$createdCandidateId]
        ) === $userOneId,
        'Candidate creation accepts a recruiter from the same tenant.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT created_by FROM recruit_candidates WHERE id = ?",
            [$createdCandidateId]
        ) === $userOneId,
        'Candidate creation accepts a creator from the same tenant.'
    );

    // ---------------------------------------------------------
    // TENANT TWO: own candidate and child records remain visible
    // ---------------------------------------------------------

    $tenantContext->set($tenantTwo);

    check(
        $repository->findById($candidateTwoId) !== null,
        'Tenant two can find its own candidate.'
    );

    check(
        $repository->findById($candidateOneId) === null,
        'Tenant two cannot find tenant one candidate.'
    );

    check(
        count($repository->skillsFor($candidateTwoId)) === 1,
        'Tenant two can read its own candidate skills.'
    );

    check(
        count($repository->experienceFor($candidateTwoId)) === 1,
        'Tenant two can read its own candidate experience.'
    );

    check(
        count($repository->educationFor($candidateTwoId)) === 1,
        'Tenant two can read its own candidate education.'
    );

    check(
        count($repository->tagsFor($candidateTwoId)) === 1,
        'Tenant two can read its own candidate tags.'
    );

    check(
        $repository->findById($createdCandidateId) === null,
        'Tenant two cannot find the candidate created by tenant one.'
    );

    echo "\nAll CandidateRepository tenant-isolation checks passed.\n";

} catch (Throwable $exception) {
    echo "\n[FAIL] " . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    $exitCode = 1;

} finally {
    if ($tenantContext !== null) {
        $tenantContext->clear();
    }

    if ($transactionStarted && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "[CLEANUP] Test transaction rolled back.\n";
    }
}

exit($exitCode);
