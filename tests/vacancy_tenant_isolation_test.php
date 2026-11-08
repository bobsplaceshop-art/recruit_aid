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
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyRecruiterRepository;
use CorporateAid\Recruit\Repositories\VacancyHiringManagerRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;

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

function createOrganisation(
    PDO $pdo,
    int $tenantId,
    int $userId,
    string $suffix
): int {
    return insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantId,
            'VAC-ORG-' . $suffix,
            'Vacancy Test Organisation ' . $suffix,
            'vac-org-' . strtolower($suffix) . '@example.test',
            $userId,
        ]
    );
}

function createEngagement(
    PDO $pdo,
    int $tenantId,
    int $organisationId,
    int $userId,
    string $suffix
): int {
    return insert(
        $pdo,
        "INSERT INTO recruit_engagements
            (tenant_id, code, client_organisation_id, name,
             service_type, status, agreed_active_role_capacity, created_by)
         VALUES (?, ?, ?, ?, 'focused_search', 'active', 5, ?)",
        [
            $tenantId,
            'ENG-' . str_pad(
                (string) (
                    (int) substr($suffix, 0, -1)
                    + ord(substr($suffix, -1)) - ord('A')
                ),
                6,
                '0',
                STR_PAD_LEFT
            ),
            $organisationId,
            'Vacancy Test Engagement ' . $suffix,
            $userId,
        ]
    );
}

function createContact(
    PDO $pdo,
    int $tenantId,
    int $organisationId,
    int $userId,
    int $number,
    string $suffix
): int {
    return insert(
        $pdo,
        "INSERT INTO recruit_client_contacts
            (tenant_id, client_organisation_id, code, first_name,
             last_name, email, is_hiring_manager, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)",
        [
            $tenantId,
            $organisationId,
            sprintf('CNT-%06d', $number),
            'Vacancy',
            'Contact ' . $suffix,
            'vac-contact-' . strtolower($suffix) . '@example.test',
            $userId,
        ]
    );
}

function createVacancy(
    VacancyRepository $vacancies,
    int $organisationId,
    int $userId,
    string $title,
    ?int $engagementId = null,
    ?int $recruiterId = null,
    ?int $contactId = null
): int {
    return $vacancies->create([
        'code' => $vacancies->nextCode(),
        'client_organisation_id' => $organisationId,
        'engagement_id' => $engagementId,
        'title' => $title,
        'status' => 'draft',
        'recruiter_id' => $recruiterId,
        'hiring_manager_contact_id' => $contactId,
        'created_by' => $userId,
    ]);
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
    $vacancies = $container->get(VacancyRepository::class);
    $recruiters = $container->get(VacancyRecruiterRepository::class);
    $hiringManagers = $container->get(VacancyHiringManagerRepository::class);
    $stages = $container->get(VacancyStageRepository::class);

    check(true, 'Application and vacancy repositories booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999997);
    $tenantOne = 1;

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'VAC-' . $suffix,
            'Vacancy Test Tenant ' . $suffix,
        ]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    insert(
        $pdo,
        "INSERT INTO core_sequence_counters (`name`, `last_value`)
         VALUES (?, 0)",
        ['rec_vacancy_code_t' . $tenantTwo]
    );

    $userOne = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantOne,
            'Vacancy Test User One',
            'vac-user-one-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    $userTwo = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantTwo,
            'Vacancy Test User Two',
            'vac-user-two-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    check(true, 'Active users created in both tenants.');

    $organisationOne = createOrganisation(
        $pdo,
        $tenantOne,
        $userOne,
        $suffix . 'A'
    );

    $organisationOneOther = createOrganisation(
        $pdo,
        $tenantOne,
        $userOne,
        $suffix . 'B'
    );

    $organisationTwo = createOrganisation(
        $pdo,
        $tenantTwo,
        $userTwo,
        $suffix . 'C'
    );

    $engagementOne = createEngagement(
        $pdo,
        $tenantOne,
        $organisationOne,
        $userOne,
        $suffix . 'A'
    );

    $engagementOneOther = createEngagement(
        $pdo,
        $tenantOne,
        $organisationOneOther,
        $userOne,
        $suffix . 'B'
    );

    $engagementTwo = createEngagement(
        $pdo,
        $tenantTwo,
        $organisationTwo,
        $userTwo,
        $suffix . 'C'
    );

    $contactOne = createContact(
        $pdo,
        $tenantOne,
        $organisationOne,
        $userOne,
        (int) substr($suffix, 0, 6),
        $suffix . 'A'
    );

    $contactOneOther = createContact(
        $pdo,
        $tenantOne,
        $organisationOneOther,
        $userOne,
        ((int) substr($suffix, 0, 6) + 1) % 1000000,
        $suffix . 'B'
    );

    $contactTwo = createContact(
        $pdo,
        $tenantTwo,
        $organisationTwo,
        $userTwo,
        ((int) substr($suffix, 0, 6) + 2) % 1000000,
        $suffix . 'C'
    );

    check(
        true,
        'Tenant-owned organisations, engagements and contacts created.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: CREATE A VALID VACANCY
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    $vacancyOne = createVacancy(
        $vacancies,
        $organisationOne,
        $userOne,
        'Tenant One Vacancy',
        $engagementOne,
        $userOne,
        $contactOne
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_vacancies WHERE id = ?",
            [$vacancyOne]
        ) === $tenantOne,
        'Vacancy creation explicitly assigns the active tenant.'
    );

    check(
        $vacancies->findById($vacancyOne) !== null,
        'Tenant one can retrieve its own vacancy.'
    );

    // ---------------------------------------------------------
    // CREATE VALID CHILD ASSIGNMENTS
    // ---------------------------------------------------------

    $recruiters->sync($vacancyOne, [$userOne], $userOne);
    $hiringManagers->sync($vacancyOne, [$contactOne], $userOne);

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_vacancy_recruiters
             WHERE vacancy_id = ? AND user_id = ?",
            [$vacancyOne, $userOne]
        ) === $tenantOne,
        'Recruiter assignment stores the active tenant.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_vacancy_hiring_managers
             WHERE vacancy_id = ? AND contact_id = ?",
            [$vacancyOne, $contactOne]
        ) === $tenantOne,
        'Hiring-manager assignment stores the active tenant.'
    );

    check(
        $recruiters->userIsAssigned($vacancyOne, $userOne),
        'Assigned tenant one recruiter is recognised.'
    );

    check(
        $hiringManagers->contactIdsFor($vacancyOne) === [$contactOne],
        'Tenant one can retrieve its assigned hiring manager.'
    );

    // ---------------------------------------------------------
    // CROSS-TENANT VACANCY CREATION VALIDATION
    // ---------------------------------------------------------

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationTwo,
            $userOne,
            'Foreign Organisation Vacancy'
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects an organisation from another tenant.'
    );

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationOne,
            $userOne,
            'Foreign Engagement Vacancy',
            $engagementTwo
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects an engagement from another tenant.'
    );

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationOne,
            $userOne,
            'Wrong Organisation Engagement Vacancy',
            $engagementOneOther
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects an engagement belonging to another organisation.'
    );

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationOne,
            $userOne,
            'Foreign Recruiter Vacancy',
            null,
            $userTwo
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects a recruiter from another tenant.'
    );

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationOne,
            $userOne,
            'Foreign Contact Vacancy',
            null,
            null,
            $contactTwo
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects a hiring manager from another tenant.'
    );

    expectException(
        static fn() => createVacancy(
            $vacancies,
            $organisationOne,
            $userTwo,
            'Foreign Creator Vacancy'
        ),
        InvalidArgumentException::class,
        'Vacancy creation rejects a creator from another tenant.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_vacancies
             WHERE tenant_id = ? AND title IN (
                'Foreign Organisation Vacancy',
                'Foreign Engagement Vacancy',
                'Wrong Organisation Engagement Vacancy',
                'Foreign Recruiter Vacancy',
                'Foreign Contact Vacancy',
                'Foreign Creator Vacancy'
             )",
            [$tenantOne]
        ) === 0,
        'Rejected vacancy creations leave no vacancy records behind.'
    );

    // ---------------------------------------------------------
    // UPDATE VALIDATION AND PRESERVATION
    // ---------------------------------------------------------

    expectException(
        static fn() => $vacancies->update(
            $vacancyOne,
            ['engagement_id' => $engagementTwo]
        ),
        InvalidArgumentException::class,
        'Vacancy update rejects an engagement from another tenant.'
    );

    expectException(
        static fn() => $vacancies->update(
            $vacancyOne,
            ['engagement_id' => $engagementOneOther]
        ),
        InvalidArgumentException::class,
        'Vacancy update rejects an engagement from another organisation.'
    );

    expectException(
        static fn() => $vacancies->update(
            $vacancyOne,
            ['recruiter_id' => $userTwo]
        ),
        InvalidArgumentException::class,
        'Vacancy update rejects a recruiter from another tenant.'
    );

    expectException(
        static fn() => $vacancies->update(
            $vacancyOne,
            ['hiring_manager_contact_id' => $contactTwo]
        ),
        InvalidArgumentException::class,
        'Vacancy update rejects a contact from another tenant.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT engagement_id FROM recruit_vacancies WHERE id = ?",
            [$vacancyOne]
        ) === $engagementOne,
        'Rejected updates preserve the original engagement.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT recruiter_id FROM recruit_vacancies WHERE id = ?",
            [$vacancyOne]
        ) === $userOne,
        'Rejected updates preserve the original recruiter.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT hiring_manager_contact_id FROM recruit_vacancies WHERE id = ?",
            [$vacancyOne]
        ) === $contactOne,
        'Rejected updates preserve the original hiring manager.'
    );

    // ---------------------------------------------------------
    // RECRUITER ASSIGNMENT VALIDATION
    // ---------------------------------------------------------

    expectException(
        static fn() => $recruiters->sync(
            $vacancyOne,
            [$userTwo],
            $userTwo
        ),
        InvalidArgumentException::class,
        'Recruiter assignment rejects a foreign-tenant recruiter.'
    );

    check(
        $recruiters->userIdsFor($vacancyOne) === [$userOne],
        'Rejected recruiter replacement preserves the original assignment.'
    );

    expectException(
        static fn() => $recruiters->sync(
            $vacancyOne,
            [$userOne],
            $userTwo
        ),
        InvalidArgumentException::class,
        'Recruiter assignment rejects a foreign-tenant lead recruiter.'
    );

    check(
        $recruiters->userIdsFor($vacancyOne) === [$userOne],
        'Rejected lead recruiter replacement does not erase assignments.'
    );

    // ---------------------------------------------------------
    // HIRING-MANAGER ASSIGNMENT VALIDATION
    // ---------------------------------------------------------

    expectException(
        static fn() => $hiringManagers->sync(
            $vacancyOne,
            [$contactTwo],
            $userOne
        ),
        InvalidArgumentException::class,
        'Hiring-manager assignment rejects a contact from another tenant.'
    );

    expectException(
        static fn() => $hiringManagers->sync(
            $vacancyOne,
            [$contactOneOther],
            $userOne
        ),
        InvalidArgumentException::class,
        'Hiring-manager assignment rejects a contact from another organisation.'
    );

    expectException(
        static fn() => $hiringManagers->sync(
            $vacancyOne,
            [$contactOne],
            $userTwo
        ),
        InvalidArgumentException::class,
        'Hiring-manager assignment rejects an assigner from another tenant.'
    );

    check(
        $hiringManagers->contactIdsFor($vacancyOne) === [$contactOne],
        'Rejected hiring-manager replacements preserve the original assignment.'
    );

    // ---------------------------------------------------------
    // REQUIREMENTS TENANT ISOLATION
    // ---------------------------------------------------------

    $vacancies->syncRequirements($vacancyOne, [
        'responsibility' => ['Manage the recruitment process'],
        'requirement' => ['Relevant professional experience'],
        'skill' => ['Communication'],
    ]);

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_vacancy_requirements
             WHERE vacancy_id = ? AND tenant_id = ?",
            [$vacancyOne, $tenantOne]
        ) === 3,
        'Vacancy requirements are stored with the active tenant.'
    );

    check(
        count($vacancies->requirementsFor($vacancyOne)) === 3,
        'Tenant one can retrieve its vacancy requirements.'
    );

    // ---------------------------------------------------------
    // PIPELINE STAGE CLONING
    // ---------------------------------------------------------

    $stageSlug = 'tenant_test_' . $suffix;

    insert(
        $pdo,
        "INSERT INTO recruit_pipeline_stages
            (tenant_id, client_organisation_id, key_slug, label,
             sort_order, is_terminal, is_default)
         VALUES (?, NULL, ?, ?, 900, 0, 1)",
        [
            $tenantOne,
            $stageSlug,
            'Tenant One Global Test Stage',
        ]
    );

    insert(
        $pdo,
        "INSERT INTO recruit_pipeline_stages
            (tenant_id, client_organisation_id, key_slug, label,
             sort_order, is_terminal, is_default)
         VALUES (?, ?, ?, ?, 900, 0, 1)",
        [
            $tenantOne,
            $organisationOne,
            $stageSlug,
            'Tenant One Organisation Override',
        ]
    );

    insert(
        $pdo,
        "INSERT INTO recruit_pipeline_stages
            (tenant_id, client_organisation_id, key_slug, label,
             sort_order, is_terminal, is_default)
         VALUES (?, NULL, ?, ?, 900, 0, 1)",
        [
            $tenantTwo,
            $stageSlug,
            'Tenant Two Global Test Stage',
        ]
    );

    $stages->cloneForOrganisation($vacancyOne, $organisationOne);

    $clonedStage = scalar(
        $pdo,
        "SELECT label FROM recruit_vacancy_stages
         WHERE vacancy_id = ? AND key_slug = ? AND tenant_id = ?",
        [$vacancyOne, $stageSlug, $tenantOne]
    );

    check(
        $clonedStage === 'Tenant One Organisation Override',
        'Organisation-specific pipeline stage overrides the tenant global default.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_vacancy_stages
             WHERE vacancy_id = ? AND tenant_id = ?",
            [$vacancyOne, $tenantOne]
        ) > 0,
        'Cloned vacancy stages carry the active tenant ID.'
    );

    $clonedStageId = (int) scalar(
        $pdo,
        "SELECT id FROM recruit_vacancy_stages
         WHERE vacancy_id = ? AND key_slug = ? AND tenant_id = ?",
        [$vacancyOne, $stageSlug, $tenantOne]
    );

    check(
        $stages->findById($clonedStageId) !== null,
        'Tenant one can retrieve its own cloned vacancy stage.'
    );

    // ---------------------------------------------------------
    // TENANT TWO: CREATE ITS OWN VACANCY
    // ---------------------------------------------------------

    $tenantContext->set($tenantTwo);

    $vacancyTwo = createVacancy(
        $vacancies,
        $organisationTwo,
        $userTwo,
        'Tenant Two Vacancy',
        $engagementTwo,
        $userTwo,
        $contactTwo
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_vacancies WHERE id = ?",
            [$vacancyTwo]
        ) === $tenantTwo,
        'Tenant two vacancy receives its own tenant ID.'
    );

    check(
        $vacancies->findById($vacancyTwo) !== null,
        'Tenant two can retrieve its own vacancy.'
    );

    check(
        $vacancies->findById($vacancyOne) === null,
        'Tenant two cannot retrieve tenant one vacancy.'
    );

    check(
        $vacancies->requirementsFor($vacancyOne) === [],
        'Tenant two cannot retrieve tenant one vacancy requirements.'
    );

    expectException(
        static fn() => $stages->forVacancy($vacancyOne),
        InvalidArgumentException::class,
        'Tenant two cannot retrieve tenant one vacancy stages.'
    );

    expectException(
        static fn() => $stages->cloneForOrganisation(
            $vacancyOne,
            $organisationOne
        ),
        InvalidArgumentException::class,
        'Tenant two cannot clone stages onto a tenant one vacancy.'
    );

    expectException(
        static fn() => $hiringManagers->sync(
            $vacancyOne,
            [$contactTwo],
            $userTwo
        ),
        InvalidArgumentException::class,
        'Tenant two cannot assign contacts to a tenant one vacancy.'
    );

    // ---------------------------------------------------------
    // SOFT-DELETED VACANCY PROTECTION
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    $vacancies->softDelete($vacancyOne);

    check(
        $vacancies->findById($vacancyOne)?->isDeleted() === true,
        'Soft-deleted vacancy remains identifiable through include-deleted lookup.'
    );

    expectException(
        static fn() => $vacancies->update(
            $vacancyOne,
            ['title' => 'Attempted update after deletion']
        ),
        RuntimeException::class,
        'Soft-deleted vacancy cannot be updated.'
    );

    expectException(
        static fn() => $vacancies->syncRequirements(
            $vacancyOne,
            ['requirement' => ['Must not be saved']]
        ),
        InvalidArgumentException::class,
        'Requirements cannot be synchronised for a soft-deleted vacancy.'
    );

    expectException(
        static fn() => $recruiters->sync(
            $vacancyOne,
            [$userOne],
            $userOne
        ),
        InvalidArgumentException::class,
        'Recruiter assignments cannot be changed for a soft-deleted vacancy.'
    );

    expectException(
        static fn() => $hiringManagers->sync(
            $vacancyOne,
            [$contactOne],
            $userOne
        ),
        InvalidArgumentException::class,
        'Hiring-manager assignments cannot be changed for a soft-deleted vacancy.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_vacancy_requirements
             WHERE vacancy_id = ? AND tenant_id = ?",
            [$vacancyOne, $tenantOne]
        ) === 3,
        'Rejected soft-deleted vacancy requirement update preserves existing rows.'
    );

    echo "\nAll VacancyRepository and vacancy assignment tenant-isolation checks passed.\n";

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
