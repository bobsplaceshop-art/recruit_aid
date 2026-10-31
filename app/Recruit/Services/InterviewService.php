<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\Logger;
use CorporateAid\Recruit\Models\Interview;
use CorporateAid\Recruit\Models\VacancyStage;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;

final class InterviewService
{
    public function __construct(
        private Database $db,
        private InterviewRepository $interviews,
        private ScorecardRepository $scorecards,
        private ApplicationRepository $applications,
        private CandidateRepository $candidates,
        private VacancyRepository $vacancies,
        private VacancyStageRepository $stages,
        private ApplicationService $appService,
        private ActivityLogger $activity,
        private EmailService $mailer,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array<string, mixed> $input
     * @param int[] $participantUserIds
     * @return array{id:int, interview:?Interview, errors:string[]}
     */
    public function schedule(int $applicationId, array $input, array $participantUserIds): array
    {
        $application = $this->applications->findById($applicationId);

        if ($application === null) {
            return ['id' => 0, 'interview' => null, 'errors' => ['Application not found.']];
        }

        $errors = [];
        $scheduledAt = trim(Row::str($input, 'scheduled_at'));

        if ($scheduledAt === '') {
            $errors[] = 'Date and time are required.';
        } elseif (strtotime($scheduledAt) === false) {
            $errors[] = 'Date/time is not valid.';
        } elseif (strtotime($scheduledAt) < time()) {
            $errors[] = 'Interview must be scheduled in the future.';
        }

        $duration = Row::int($input, 'duration_minutes', 60);

        if ($duration < 15 || $duration > 480) {
            $errors[] = 'Duration must be between 15 and 480 minutes.';
        }

        $validTypes = ['phone', 'video', 'onsite', 'technical', 'panel', 'final'];
        $type = Row::str($input, 'type', 'video');

        if (!in_array($type, $validTypes, true)) {
            $errors[] = 'Invalid interview type.';
        }

        if (empty($participantUserIds)) {
            $errors[] = 'At least one interviewer must be assigned.';
        }

        if ($errors !== []) {
            return ['id' => 0, 'interview' => null, 'errors' => $errors];
        }

        $leadUserId = Row::int($input, 'lead_user_id', (int) ($participantUserIds[0] ?? 0));

        try {
            $this->db->beginTransaction();

            $id = $this->interviews->create([
                'code'             => $this->interviews->nextCode(),
                'application_id'   => $applicationId,
                'scheduled_at'     => $scheduledAt,
                'duration_minutes' => $duration,
                'type'             => $type,
                'location'         => $input['location'] ?? null,
                'status'           => 'scheduled',
                'notes'            => $input['notes'] ?? null,
                'created_by'       => $this->auth?->user()?->id,
            ]);

            $this->interviews->setParticipants($id, $participantUserIds, $leadUserId);

            $interview = $this->interviews->findById($id);

            if ($interview === null) {
                throw new \RuntimeException('The interview could not be retrieved after creation.');
            }

            $this->db->commit();
        } catch (\InvalidArgumentException $e) {
            $this->db->rollBack();

            return [
                'id' => 0,
                'interview' => null,
                'errors' => [$e->getMessage()],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // The interview is committed. Secondary operations must not make
        // the caller believe that scheduling failed.
        try {
            $this->activity->log(
                'interview.scheduled',
                'interviews',
                'Scheduled interview ' . $interview->code . ' for application ' . $application->code,
                'interview',
                $id
            );
        } catch (\Throwable $e) {
            $this->logPostCommitFailure('Interview scheduling activity log failed.', $e, $id);
        }

        // Pipeline advancement is independent of the interview transaction.
        try {
            $this->advanceForInterview($application);
        } catch (\Throwable $e) {
            $this->logPostCommitFailure('Interview pipeline advancement failed.', $e, $id);
        }

        // Confirmation email is best-effort. Lookup, rendering and transport
        // failures must not change the successful scheduling result.
        try {
            $candidate = $this->candidates->findById($application->candidateId);
            $vacancy = $this->vacancies->findById($application->vacancyId);

            if ($candidate !== null && $vacancy !== null && $candidate->email !== '') {
                $authUser = $this->auth?->user();
                $senderName = $authUser !== null ? $authUser->name : 'The team';

                $this->mailer->sendDbTemplate(
                    $candidate->email,
                    'interview.scheduled',
                    [
                        'candidate_first_name' => $candidate->firstName,
                        'vacancy_title'        => $vacancy->title,
                        'client_name'          => app_setting_string('branding.app_name', 'Recruit Aid'),
                        'scheduled_at'         => $interview->scheduledAt,
                        'duration'             => (string) $interview->durationMinutes,
                        'interview_type'       => $interview->typeLabel(),
                        'location'             => $interview->location ?? 'To be confirmed',
                        'notes_block'          => $interview->notes !== null && $interview->notes !== ''
                            ? '<p style="font-size:14px;line-height:1.6;background:#fef3c7;padding:12px;border-radius:6px;margin:0 0 24px;">' . e($interview->notes) . '</p>'
                            : '',
                        'interview_url'        => app_url('/interviews/' . $interview->code),
                        'sender_name'          => $senderName,
                        'product_name'         => app_setting_string('branding.app_name', 'Recruit Aid'),
                    ]
                );
            }
        } catch (\Throwable $e) {
            $this->logPostCommitFailure('Interview confirmation email failed.', $e, $id);
        }

        return ['id' => $id, 'interview' => $interview, 'errors' => []];
    }

    public function complete(int $interviewId): bool
    {
        $interview = $this->interviews->findById($interviewId);

        if ($interview === null) {
            return false;
        }

        $this->interviews->updateStatus($interviewId, 'completed');

        $this->activity->log(
            'interview.completed',
            'interviews',
            'Marked interview ' . $interview->code . ' as completed',
            'interview',
            $interviewId
        );

        $application = $this->applications->findById($interview->applicationId);

        if ($application !== null) {
            $this->advanceForInterview($application);
        }

        return true;
    }

    public function cancel(int $interviewId): bool
    {
        $interview = $this->interviews->findById($interviewId);

        if ($interview === null) {
            return false;
        }

        $this->interviews->updateStatus($interviewId, 'cancelled');

        $this->activity->log(
            'interview.cancelled',
            'interviews',
            'Cancelled interview ' . $interview->code,
            'interview',
            $interviewId
        );

        return true;
    }

    /**
     * Advance the pipeline based on the effective mode.
     * - Open: move to the 'interview' / 'interviewing' stage.
     * - Straight: move to the next stage after the current one.
     */
    private function advanceForInterview(\CorporateAid\Recruit\Models\Application $application): void
    {
        if ($application->status !== 'active') {
            return;
        }

        $user = $this->auth?->user();

        if ($user === null || !$user->can('recruit.pipeline.advance')) {
            return;
        }

        $stages = $this->stages->forVacancy($application->vacancyId);

        if (empty($stages)) {
            return;
        }

        $ordered = $stages;

        usort(
            $ordered,
            static fn(VacancyStage $a, VacancyStage $b) => $a->sortOrder <=> $b->sortOrder
        );

        $currentIndex = -1;

        foreach ($ordered as $i => $stage) {
            if ($stage->id === $application->currentStageId) {
                $currentIndex = $i;
                break;
            }
        }

        $mode = $this->appService->effectivePipelineMode($application->vacancyId);
        $targetStage = null;

        if ($mode === 'open') {
            foreach ($ordered as $stage) {
                if (in_array($stage->keySlug, ['interview', 'interviewing'], true)) {
                    $targetStage = $stage;
                    break;
                }
            }

            if ($targetStage !== null) {
                $targetIndex = array_search(
                    $targetStage->id,
                    array_column($ordered, 'id'),
                    true
                );

                if ($targetIndex === false || ($currentIndex >= 0 && $targetIndex <= $currentIndex)) {
                    $targetStage = null;
                }
            }
        } else {
            foreach ($ordered as $i => $stage) {
                if ($i > $currentIndex && !$stage->isTerminal) {
                    $targetStage = $stage;
                    break;
                }
            }
        }

        if ($targetStage === null || $application->currentStageId === $targetStage->id) {
            return;
        }

        $result = $this->appService->moveToStage(
            $application->id,
            $targetStage->id,
            'Auto-advanced on interview (' . $mode . ' mode)'
        );

        if (!$result['success']) {
            try {
                $this->activity->log(
                    'application.advance_failed',
                    'applications',
                    'Could not auto-advance application ' . $application->code . ': ' . ($result['error'] ?? 'unknown'),
                    'application',
                    $application->id
                );
            } catch (\Throwable $e) {
                $this->logPostCommitFailure('Pipeline advancement failure activity log failed.', $e, $application->id);
            }
        }
    }

    /**
     * @param array<int, array{competency_name:string, score:int, evidence:?string}> $attributes
     * @return array{success:bool, errors:string[]}
     */
    public function submitScorecard(
        int $interviewId,
        int $interviewerId,
        ?string $recommendation,
        ?string $notes,
        array $attributes
    ): array {
        $interview = $this->interviews->findById($interviewId);

        if ($interview === null) {
            return ['success' => false, 'errors' => ['Interview not found.']];
        }

        if (!$this->interviews->isParticipant($interviewId, $interviewerId)) {
            return ['success' => false, 'errors' => ['You are not a participant in this interview.']];
        }

        $validRecommendations = ['strong_yes', 'yes', 'neutral', 'no', 'strong_no'];

        if ($recommendation !== null && !in_array($recommendation, $validRecommendations, true)) {
            return ['success' => false, 'errors' => ['Invalid recommendation.']];
        }

        foreach ($attributes as $attr) {
            $rawScore = $attr['score'] ?? null;

            $validScore = is_int($rawScore)
                || (is_string($rawScore) && preg_match('/^[1-5]$/D', $rawScore) === 1);

            if (!$validScore || (int) $rawScore < 1 || (int) $rawScore > 5) {
                return [
                    'success' => false,
                    'errors' => ['Competency scores must be between 1 and 5.'],
                ];
            }
        }

        try {
            $this->db->beginTransaction();

            // Recheck inside the transaction to avoid relying on a stale
            // pre-transaction submitted-state check.
            $existing = $this->scorecards->findByInterviewAndUser(
                $interviewId,
                $interviewerId
            );

            if ($existing !== null && $existing->isSubmitted()) {
                throw new \DomainException(
                    'Your scorecard has already been submitted and cannot be edited.'
                );
            }

            $id = $this->scorecards->createOrUpdate(
                $interviewId,
                $interviewerId,
                $recommendation,
                $notes,
                true
            );

            $this->scorecards->syncAttributes($id, $attributes);

            $this->db->commit();
        } catch (\DomainException $e) {
            $this->db->rollBack();

            return ['success' => false, 'errors' => [$e->getMessage()]];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        try {
            $this->activity->log(
                'scorecard.submitted',
                'interviews',
                'Submitted scorecard for interview ' . $interview->code,
                'interview',
                $interviewId
            );
        } catch (\Throwable $e) {
            $this->logPostCommitFailure('Scorecard submission activity log failed.', $e, $interviewId);
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Record a post-commit failure without changing the committed operation's
     * result. Logger is static and designed not to interrupt application flow.
     */
    private function logPostCommitFailure(string $message, \Throwable $exception, int $recordId): void
    {
        Logger::exception($exception);
        Logger::warning($message, [
            'record_id' => $recordId,
            'exception' => $exception::class,
        ]);
    }
}
