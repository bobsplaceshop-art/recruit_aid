<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Recruit\Models\Interview;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;

final class InterviewService
{
    public function __construct(
        private InterviewRepository $interviews,
        private ScorecardRepository $scorecards,
        private ApplicationRepository $applications,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {}

    /**
     * Schedule a new interview against an application.
     *
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
     * Submit a scorecard. Enforces independent submission:
     * the interviewer must be a participant, and can only edit their
     * own scorecard. Other scorecards are not visible until submitted.
     *
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
