<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Services\SystemSettingService;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Recruit\Models\Application;
use CorporateAid\Recruit\Models\VacancyStage;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;

final class ApplicationService
{
    private const ACTIVE_VACANCY_STATUSES = [
        'open', 'sourcing', 'screening', 'shortlisting',
        'client_review', 'interviewing', 'reference_check', 'offer',
    ];

    private const TERMINAL_VACANCY_STATUSES = ['filled', 'closed', 'cancelled'];

    public function __construct(
        private ApplicationRepository $applications,
        private VacancyRepository $vacancies,
        private VacancyStageRepository $stages,
        private EngagementRepository $engagements,
        private SystemSettingService $settings,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array{id:int, application:Application|null, errors:string[]}
     */
    public function create(int $vacancyId, int $candidateId, array $input = []): array
    {
        $vacancy = $this->vacancies->findById($vacancyId);
        if ($vacancy === null) {
            return ['id' => 0, 'application' => null, 'errors' => ['Vacancy not found.']];
        }

        if (in_array($vacancy->status, self::TERMINAL_VACANCY_STATUSES, true)) {
            return ['id' => 0, 'application' => null, 'errors' => [
                'This vacancy is "' . $vacancy->statusLabel() . '". Reopen it before adding candidates.',
            ]];
        }

        if (
            !in_array($vacancy->status, self::ACTIVE_VACANCY_STATUSES, true)
            && $vacancy->status !== 'draft'
        ) {
            return ['id' => 0, 'application' => null, 'errors' => [
                'This vacancy is "' . $vacancy->statusLabel() . '". Change it to Open before adding candidates.',
            ]];
        }

        if ($vacancy->status === 'draft') {
            $this->vacancies->update($vacancy->id, ['status' => 'open']);
            $this->activity->log(
                'vacancy.opened',
                'vacancies',
                'Auto-opened vacancy ' . $vacancy->code . ' (' . $vacancy->title . ') when the first candidate was added',
                'vacancy',
                $vacancy->id
            );
            $vacancy = $this->vacancies->findById($vacancyId) ?? $vacancy;
        }

        $existing = $this->applications->findByCandidateAndVacancy($candidateId, $vacancyId);
        if ($existing !== null) {
            return ['id' => 0, 'application' => null, 'errors' => [
                'This candidate is already in the pipeline for this vacancy (' . $existing->code . ').',
            ]];
        }

        $stages = $this->stages->forVacancy($vacancyId);
        $firstStage = $stages[0] ?? null;

        $id = $this->applications->create([
            'code'             => $this->applications->nextCode(),
            'candidate_id'     => $candidateId,
            'vacancy_id'       => $vacancyId,
            'current_stage_id' => $firstStage !== null ? $firstStage->id : null,
            'status'           => 'active',
            'source'           => isset($input['source']) && is_string($input['source']) ? trim($input['source']) : null,
            'notes'            => isset($input['notes']) && is_string($input['notes']) ? trim($input['notes']) : null,
            'created_by'       => $this->auth?->user()?->id,
        ]);

        if ($firstStage !== null) {
            $this->applications->recordStageMove($id, null, $firstStage->id, $this->auth?->user()?->id, 'Application created');
        }

        $application = $this->applications->findById($id);
        if ($application === null) {
            return ['id' => $id, 'application' => null, 'errors' => ['Application created but could not be retrieved.']];
        }

        $this->activity->log(
            'application.created',
            'applications',
            'Created application ' . $application->code . ' for vacancy ' . $vacancy->code,
            'application',
            $id
        );

        return ['id' => $id, 'application' => $application, 'errors' => []];
    }

    /**
     * Move an application to a new stage.
     *
     * Open mode: any forward stage. Skipped stages are recorded in
     * history as 'passed through' so funnel reports stay accurate.
     * Straight mode: only the immediately-next stage (or a terminal).
     *
     * @return array{success:bool, error:?string, passed_through:int[]}
     */
    public function moveToStage(int $applicationId, int $newStageId, ?string $notes = null): array
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) {
            return ['success' => false, 'error' => 'Application not found.', 'passed_through' => []];
        }
        if ($application->status !== 'active') {
            return ['success' => false, 'error' => 'Only active applications can be moved.', 'passed_through' => []];
        }

        $stages = $this->stages->forVacancy($application->vacancyId);
        $target = null;
        foreach ($stages as $s) {
            if ($s->id === $newStageId) { $target = $s; break; }
        }
        if ($target === null) {
            return ['success' => false, 'error' => 'Target stage does not belong to this vacancy.', 'passed_through' => []];
        }

        $ordered = $stages;
        usort($ordered, static fn(VacancyStage $a, VacancyStage $b) => $a->sortOrder <=> $b->sortOrder);

        $currentIndex = -1;
        foreach ($ordered as $i => $s) {
            if ($s->id === $application->currentStageId) { $currentIndex = $i; break; }
        }
        $targetIndex = -1;
        foreach ($ordered as $i => $s) {
            if ($s->id === $target->id) { $targetIndex = $i; break; }
        }

        $mode = $this->effectivePipelineMode($application->vacancyId);

        if ($mode === 'straight' && !$target->isTerminal) {
            if ($currentIndex >= 0 && $targetIndex !== $currentIndex + 1 && $targetIndex <= $currentIndex) {
                return [
                    'success' => false,
                    'error'   => 'Straight-path mode requires moving to the next stage. You cannot skip or go backwards.',
                    'passed_through' => [],
                ];
            }
        }

        // Compute stages skipped over (only meaningful in open mode).
        $passedThroughIds = [];
        if (
            $mode === 'open'
            && $currentIndex >= 0
            && $targetIndex > $currentIndex + 1
        ) {
            for ($i = $currentIndex + 1; $i < $targetIndex; $i++) {
                $skipped = $ordered[$i];
                if ($skipped->isTerminal) continue; // don't count terminal stages as "passed through"
                $passedThroughIds[] = $skipped->id;
            }
        }

        $fromStageId = $application->currentStageId;
        $this->applications->updateStage($applicationId, $newStageId);
        $this->applications->recordStageMove($applicationId, $fromStageId, $newStageId, $this->auth?->user()?->id, $notes);

        // Record passed-through stages for reporting
        foreach ($passedThroughIds as $skippedId) {
            $this->applications->recordStageMove(
                $applicationId,
                null,
                $skippedId,
                null,
                'Passed through (open mode auto-fill)'
            );
        }

        $this->activity->log(
            'application.stage_moved',
            'applications',
            'Moved application ' . $application->code . ' to stage ' . $target->label
                . (empty($passedThroughIds) ? '' : ' (passed through ' . count($passedThroughIds) . ' stage(s))'),
            'application',
            $applicationId
        );

        return ['success' => true, 'error' => null, 'passed_through' => $passedThroughIds];
    }

    public function archive(int $applicationId): bool
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) return false;
        $this->applications->softDelete($applicationId);
        $this->activity->log(
            'application.archived',
            'applications',
            'Archived application ' . $application->code,
            'application',
            $applicationId
        );
        return true;
    }

    public function effectivePipelineMode(int $vacancyId): string
    {
        $vacancy = $this->vacancies->findById($vacancyId);
        if ($vacancy !== null && $vacancy->engagementId !== null) {
            $engagement = $this->engagements->findById($vacancy->engagementId);
            if ($engagement !== null && $engagement->pipelineMode !== null) {
                return $engagement->pipelineMode;
            }
        }
        $global = $this->settings->getString('recruit.pipeline_mode', 'open');
        return in_array($global, ['open', 'straight'], true) ? $global : 'open';
    }
}
