<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Support\EmailService;
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

        $validTypes = ['phone','video','onsite','technical','panel','final'];
        $type = Row::str($input, 'type', 'video');
        if (!in_array($type, $validTypes, true)) {
            $errors[] = 'Invalid interview type.';
        }

        if (empty($participantUserIds)) {
            $errors[] = 'At least one interviewer must be assigned.';
        }

        if (!empty($errors)) {
            return ['id' => 0, 'interview' => null, 'errors' => $errors];
        }

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

        $leadUserId = Row::int($input, 'lead_user_id', $participantUserIds[0] ?? 0);
        $this->interviews->setParticipants($id, $participantUserIds, $leadUserId);

        $interview = $this->interviews->findById($id);

        $this->activity->log(
            'interview.scheduled',
            'interviews',
            'Scheduled interview ' . ($interview !== null ? $interview->code : '') . ' for application ' . $application->code,
            'interview',
            $id
        );

        // Auto-advance the pipeline when the interview is scheduled.
        // Open mode: jump straight to the 'interview' stage.
        // Straight mode: move to the next stage only.
        $this->advanceForInterview($application);

        // Fire the interview confirmation email (best-effort)
        if ($interview !== null) {
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
        }

        return ['id' => $id, 'interview' => $interview, 'errors' => []];
    }

    public function complete(int $interviewId): bool
    {
        $interview = $this->interviews->findById($interviewId);
        if ($interview === null) return false;

        $this->interviews->updateStatus($interviewId, 'completed');
        $this->activity->log(
            'interview.completed',
            'interviews',
            'Marked interview ' . $interview->code . ' as completed',
            'interview',
            $interviewId
        );

        // Auto-advance the pipeline when the interview completes.
        $application = $this->applications->findById($interview->applicationId);
        if ($application !== null) {
            $this->advanceForInterview($application);
        }

        return true;
    }

    public function cancel(int $interviewId): bool
    {
        $interview = $this->interviews->findById($interviewId);
        if ($interview === null) return false;

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
     * - Open: move to the 'interview' / 'interviewing' stage (records passed-through).
     * - Straight: move to the next stage after the current one.
     */
    private function advanceForInterview(\CorporateAid\Recruit\Models\Application $application): void
    {
        if ($application->status !== 'active') return;

        $stages = $this->stages->forVacancy($application->vacancyId);
        if (empty($stages)) return;

        $ordered = $stages;
        usort($ordered, static fn(VacancyStage $a, VacancyStage $b) => $a->sortOrder <=> $b->sortOrder);

        $currentIndex = -1;
        foreach ($ordered as $i => $s) {
            if ($s->id === $application->currentStageId) { $currentIndex = $i; break; }
        }

        $mode = $this->appService->effectivePipelineMode($application->vacancyId);
        $targetStage = null;

        if ($mode === 'open') {
            foreach ($ordered as $s) {
                if (in_array($s->keySlug, ['interview', 'interviewing'], true)) {
                    $targetStage = $s;
                    break;
                }
            }
            // Only advance forward
            if ($targetStage !== null) {
                $targetIdx = array_search($targetStage->id, array_column($ordered, 'id'), true);
                if ($targetIdx === false || ($currentIndex >= 0 && $targetIdx <= $currentIndex)) {
                    $targetStage = null;
                }
            }
        } else {
            // Straight: move to the next stage after current (non-terminal)
            foreach ($ordered as $i => $s) {
                if ($i > $currentIndex && !$s->isTerminal) {
                    $targetStage = $s;
                    break;
                }
            }
        }

        if ($targetStage === null) return;
        if ($application->currentStageId === $targetStage->id) return;

        $result = $this->appService->moveToStage(
            $application->id,
            $targetStage->id,
            'Auto-advanced on interview (' . $mode . ' mode)'
        );

        if (!$result['success']) {
            $this->activity->log(
                'application.advance_failed',
                'applications',
                'Could not auto-advance application ' . $application->code . ': ' . ($result['error'] ?? 'unknown'),
                'application',
                $application->id
            );
        }
    }

    /**
     * @param array<int, array{competency_name:string, score:int, evidence:?string}> $attributes
     * @return array{success:bool, errors:string[]}
     */
    public function submitScorecard(int $interviewId, int $interviewerId, ?string $recommendation, ?string $notes, array $attributes): array
    {
        $interview = $this->interviews->findById($interviewId);
        if ($interview === null) {
            return ['success' => false, 'errors' => ['Interview not found.']];
        }

        if (!$this->interviews->isParticipant($interviewId, $interviewerId)) {
            return ['success' => false, 'errors' => ['You are not a participant in this interview.']];
        }

        $existing = $this->scorecards->findByInterviewAndUser($interviewId, $interviewerId);
        if ($existing !== null && $existing->isSubmitted()) {
            return ['success' => false, 'errors' => ['Your scorecard has already been submitted and cannot be edited.']];
        }

        $validRecommendations = ['strong_yes','yes','neutral','no','strong_no'];
        if ($recommendation !== null && !in_array($recommendation, $validRecommendations, true)) {
            return ['success' => false, 'errors' => ['Invalid recommendation.']];
        }

        foreach ($attributes as $attr) {
            if ($attr['score'] < 1 || $attr['score'] > 5) {
                return ['success' => false, 'errors' => ['Competency scores must be between 1 and 5.']];
            }
        }

        $id = $this->scorecards->createOrUpdate($interviewId, $interviewerId, $recommendation, $notes, true);
        $this->scorecards->syncAttributes($id, $attributes);

        $this->activity->log(
            'scorecard.submitted',
            'interviews',
            'Submitted scorecard for interview ' . $interview->code,
            'interview',
            $interviewId
        );

        return ['success' => true, 'errors' => []];
    }
}
