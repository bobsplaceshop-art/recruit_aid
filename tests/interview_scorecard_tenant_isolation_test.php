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
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;
use CorporateAid\Recruit\Services\InterviewService;

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

/**
 * Creates the minimum valid hiring records required by an application.
 *
 * @return array{organisation_id:int,candidate_id:int,vacancy_id:int,application_id:int}
 */
function createHiringFixture(
    PDO $pdo,
    int $tenantId,
    int $userId,
    string $suffix,
    int $vacancyNumber
): array {
    $organisationId = insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantId,
            'INT-ORG-' . $suffix,
            'Interview Test Organisation ' . $suffix,
            'int-org-' . $suffix . '@example.test',
            $userId,
        ]
    );

    $candidateId = insert(
        $pdo,
        "INSERT INTO recruit_candidates
            (tenant_id, code, first_name, last_name, email, created_by)
         VALUES (?, ?, ?, ?, ?, ?)",
        [
            $tenantId,
            'INT-CAND-' . $suffix,
            'Interview',
            'Candidate ' . $suffix,
            'int-candidate-' . $suffix . '@example.test',
            $userId,
        ]
    );

    $vacancyId = insert(
        $pdo,
        "INSERT INTO recruit_vacancies
            (tenant_id, code, client_organisation_id, title, status, created_by)
         VALUES (?, ?, ?, ?, 'open', ?)",
        [
            $tenantId,
            sprintf('VAC-%06d', $vacancyNumber),
            $organisationId,
            'Interview Test Vacancy ' . $suffix,
            $userId,
        ]
    );

    $applicationId = insert(
        $pdo,
        "INSERT INTO recruit_applications
            (tenant_id, code, candidate_id, vacancy_id, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantId,
            'INT-APP-' . $suffix,
            $candidateId,
            $vacancyId,
            $userId,
        ]
    );

    return [
        'organisation_id' => $organisationId,
        'candidate_id' => $candidateId,
        'vacancy_id' => $vacancyId,
        'application_id' => $applicationId,
    ];
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
    $interviews = $container->get(InterviewRepository::class);
    $scorecards = $container->get(ScorecardRepository::class);
    $interviewService = $container->get(InterviewService::class);

    check(true, 'Application and interview/scorecard repositories booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);
    $tenantOne = 1;

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'INT-' . $suffix,
            'Interview Test Tenant ' . $suffix,
        ]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    $userOne = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantOne,
            'Interview Test User One',
            'int-user-one-' . $suffix . '@example.test',
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
            'Interview Test User Two',
            'int-user-two-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    check(true, 'Active test users created in both tenants.');

    $fixtureOne = createHiringFixture(
        $pdo,
        $tenantOne,
        $userOne,
        $suffix . '-ONE',
        (int) substr($suffix, 0, 6)
    );

    $fixtureTwo = createHiringFixture(
        $pdo,
        $tenantTwo,
        $userTwo,
        $suffix . '-TWO',
        ((int) substr($suffix, 0, 6) + 1) % 1000000
    );

    check(true, 'Valid organisation, candidate, vacancy and application fixtures created.');

    // ---------------------------------------------------------
    // CREATE INTERVIEWS THROUGH THE REPOSITORY
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    $interviewOne = $interviews->create([
        'code' => 'INT-' . $suffix . '-ONE',
        'application_id' => $fixtureOne['application_id'],
        'scheduled_at' => '2027-01-15 10:00:00',
        'duration_minutes' => 60,
        'type' => 'video',
        'created_by' => $userOne,
    ]);

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_interviews WHERE id = ?",
            [$interviewOne]
        ) === $tenantOne,
        'Interview creation assigns the active tenant ID.'
    );

    $interviews->setParticipants($interviewOne, [$userOne], $userOne);

    check(
        $interviews->isParticipant($interviewOne, $userOne),
        'Tenant one participant is recognised.'
    );

    check(
        count($interviews->participantsFor($interviewOne)) === 1,
        'Tenant one can retrieve its interview participants.'
    );

    // ---------------------------------------------------------
    // SERVICE-LEVEL COMPETENCY SCORE VALIDATION
    // ---------------------------------------------------------

    $decimalResult = $interviewService->submitScorecard(
        $interviewOne,
        $userOne,
        'yes',
        'Decimal score validation test.',
        [
            [
                'competency_name' => 'Precision',
                'score' => 1.5,
                'evidence' => 'This decimal score must be rejected.',
            ],
        ]
    );

    check(
        $decimalResult['success'] === false,
        'InterviewService rejects decimal competency scores.'
    );

    check(
        in_array(
            'Competency scores must be between 1 and 5.',
            $decimalResult['errors'],
            true
        ),
        'InterviewService returns the expected competency score validation error.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_scorecards
             WHERE tenant_id = ? AND interview_id = ? AND interviewer_id = ?",
            [$tenantOne, $interviewOne, $userOne]
        ) === 0,
        'Rejected decimal submission does not create a scorecard.'
    );

    $tenantContext->set($tenantTwo);

    $interviewTwo = $interviews->create([
        'code' => 'INT-' . $suffix . '-TWO',
        'application_id' => $fixtureTwo['application_id'],
        'scheduled_at' => '2027-01-16 10:00:00',
        'duration_minutes' => 60,
        'type' => 'video',
        'created_by' => $userTwo,
    ]);

    $interviews->setParticipants($interviewTwo, [$userTwo], $userTwo);

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_interviews WHERE id = ?",
            [$interviewTwo]
        ) === $tenantTwo,
        'Tenant two interview receives its own tenant ID.'
    );

    // ---------------------------------------------------------
    // INTERVIEW LOOKUP AND PARTICIPANT ISOLATION
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    check(
        $interviews->findById($interviewOne) !== null,
        'Tenant one can find its own interview.'
    );

    check(
        $interviews->findById($interviewTwo) === null,
        'Tenant one cannot find tenant two interview.'
    );

    check(
        $interviews->forApplication($fixtureTwo['application_id']) === [],
        'Tenant one cannot retrieve interviews for tenant two application.'
    );

    expectException(
        static fn() => $interviews->setParticipants(
            $interviewOne,
            [$userTwo],
            $userTwo
        ),
        InvalidArgumentException::class,
        'Cross-tenant participant assignment is rejected.'
    );

    check(
        $interviews->isParticipant($interviewOne, $userOne),
        'Rejected participant assignment preserves the existing valid participant.'
    );

    check(
        count($interviews->participantsFor($interviewOne)) === 1,
        'Rejected participant assignment does not erase existing participants.'
    );

    // A deliberately mismatched row. The foreign keys permit this because
    // they validate the referenced records independently, not tenant equality.
    insert(
        $pdo,
        "INSERT INTO recruit_interview_participants
            (tenant_id, interview_id, user_id, role)
         VALUES (?, ?, ?, 'observer')",
        [$tenantOne, $interviewTwo, $userOne]
    );

    check(
        $interviews->participantsFor($interviewTwo) === [],
        'Participant query excludes a row whose tenant differs from its interview.'
    );

    check(
        !$interviews->isParticipant($interviewTwo, $userOne),
        'Participant check excludes a mismatched-tenant participant row.'
    );

    // ---------------------------------------------------------
    // SCORECARD TENANT AND PARTICIPANT VALIDATION
    // ---------------------------------------------------------

    $scorecardOne = $scorecards->createOrUpdate(
        $interviewOne,
        $userOne,
        'yes',
        'Strong technical performance.',
        true
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_scorecards WHERE id = ?",
            [$scorecardOne]
        ) === $tenantOne,
        'Scorecard creation assigns the active tenant ID.'
    );

    check(
        $scorecards->findById($scorecardOne) !== null,
        'Tenant one can retrieve its own scorecard.'
    );

    check(
        $scorecards->findByInterviewAndUser($interviewOne, $userOne) !== null,
        'Tenant one can retrieve its scorecard by interview and interviewer.'
    );

    check(
        $scorecards->countSubmittedForInterview($interviewOne) === 1,
        'Submitted scorecard count includes the tenant one submission.'
    );

    expectException(
        static fn() => $scorecards->createOrUpdate(
            $interviewTwo,
            $userOne,
            'yes',
            'Unauthorised cross-tenant scorecard.',
            true
        ),
        InvalidArgumentException::class,
        'Scorecard creation rejects an interview owned by another tenant.'
    );

    expectException(
        static fn() => $scorecards->createOrUpdate(
            $interviewOne,
            $userTwo,
            'yes',
            'Unauthorised interviewer.',
            true
        ),
        InvalidArgumentException::class,
        'Scorecard creation rejects an interviewer from another tenant.'
    );

    check(
        count($scorecards->forInterview($interviewTwo)) === 0,
        'Tenant one cannot retrieve scorecards for tenant two interview.'
    );

    // ---------------------------------------------------------
    // SCORECARD ATTRIBUTE REPLACEMENT VALIDATION
    // ---------------------------------------------------------

    $scorecards->syncAttributes($scorecardOne, [
        [
            'competency_name' => 'Technical knowledge',
            'score' => 5,
            'evidence' => 'Explained the architecture clearly.',
        ],
        [
            'competency_name' => 'Communication',
            'score' => 4,
            'evidence' => 'Clear and structured responses.',
        ],
    ]);

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_scorecard_attributes
             WHERE scorecard_id = ? AND tenant_id = ?",
            [$scorecardOne, $tenantOne]
        ) === 2,
        'Valid competency attributes are saved.'
    );

    expectException(
        static fn() => $scorecards->syncAttributes($scorecardOne, [
            [
                'competency_name' => 'Replacement competency',
                'score' => 3,
                'evidence' => 'Valid first item.',
            ],
            [
                'competency_name' => 'Invalid competency',
                'score' => 9,
                'evidence' => 'Invalid score.',
            ],
        ]),
        InvalidArgumentException::class,
        'Invalid competency score is rejected.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_scorecard_attributes
             WHERE scorecard_id = ? AND tenant_id = ?",
            [$scorecardOne, $tenantOne]
        ) === 2,
        'Invalid replacement preserves the original competency attributes.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_scorecard_attributes
             WHERE scorecard_id = ? AND tenant_id = ?
               AND competency_name IN ('Technical knowledge', 'Communication')",
            [$scorecardOne, $tenantOne]
        ) === 2,
        'Original competency names remain unchanged after rejected replacement.'
    );

    expectException(
        static fn() => $scorecards->attributesFor(
            insert(
                $pdo,
                "INSERT INTO recruit_scorecards
                    (tenant_id, interview_id, interviewer_id, recommendation, notes)
                 VALUES (?, ?, ?, 'neutral', 'Tenant two fixture')",
                [$tenantTwo, $interviewTwo, $userTwo]
            )
        ),
        InvalidArgumentException::class,
        'Tenant one cannot retrieve attributes belonging to tenant two scorecard.'
    );

    // ---------------------------------------------------------
    // DELETED INTERVIEW VISIBILITY
    // ---------------------------------------------------------

    $interviews->softDelete($interviewOne);

    check(
        $interviews->findById($interviewOne) === null,
        'Soft-deleted interview is no longer visible.'
    );

    check(
        $interviews->participantsFor($interviewOne) === [],
        'Soft-deleted interview participants are not returned.'
    );

    check(
        !$interviews->isParticipant($interviewOne, $userOne),
        'Soft-deleted interview no longer recognises participants.'
    );

    check(
        $scorecards->countSubmittedForInterview($interviewOne) === 0,
        'Submitted scorecard count excludes soft-deleted interviews.'
    );

    // ---------------------------------------------------------
    // TENANT TWO: OWN RECORDS REMAIN ACCESSIBLE
    // ---------------------------------------------------------

    $tenantContext->set($tenantTwo);

    check(
        $interviews->findById($interviewTwo) !== null,
        'Tenant two can find its own interview.'
    );

    check(
        $interviews->isParticipant($interviewTwo, $userTwo),
        'Tenant two can recognise its own participant.'
    );

    check(
        count($interviews->participantsFor($interviewTwo)) === 1,
        'Tenant two participant list excludes the mismatched tenant one row.'
    );

    check(
        $interviews->findById($interviewOne) === null,
        'Tenant two cannot find tenant one interview.'
    );

    check(
        $scorecards->findById($scorecardOne) === null,
        'Tenant two cannot retrieve tenant one scorecard.'
    );

    check(
        count($scorecards->forInterview($interviewTwo)) === 1,
         'Tenant two can retrieve its own scorecard fixture.'
    );

    $scorecardTwo = $scorecards->createOrUpdate(
        $interviewTwo,
        $userTwo,
        'strong_yes',
        'Tenant two scorecard.',
        true
    );

    check(
        $scorecards->findById($scorecardTwo) !== null,
        'Tenant two can create and retrieve its own scorecard.'
    );

    check(
        $scorecards->countSubmittedForInterview($interviewTwo) === 1,
        'Tenant two submitted scorecard count is isolated.'
    );

    check(
        $scorecards->findById($scorecardOne) === null,
        'Tenant two still cannot retrieve tenant one scorecard after creating its own.'
    );

    echo "\nAll InterviewRepository and ScorecardRepository tenant-isolation checks passed.\n";

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
