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
     * Move an application to a new stage, respecting the effective pipeline mode.
     * Open mode: any valid stage.
     * Straight mode: only the next stage in sort order (or a terminal stage).
     *
     * @return array{success:bool, error:?string}
     */
    public function moveToStage(int $applicationId, int $newStageId, ?string $notes = null): array
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) {
            return ['success' => false, 'error' => 'Application not found.'];
        }
        if ($application->status !== 'active') {
            return ['success' => false, 'error' => 'Only active applications can be moved.'];
        }

        $stages = $this->stages->forVacancy($application->vacancyId);
        $target = null;
        foreach ($stages as $s) {
            if ($s->id === $newStageId) { $target = $s; break; }
        }
        if ($target === null) {
            return ['success' => false, 'error' => 'Target stage does not belong to this vacancy.'];
        }

        $mode = $this->effectivePipelineMode($application->vacancyId);
        if ($mode === 'straight' && !$target->isTerminal) {
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
            if ($currentIndex >= 0 && $targetIndex !== $currentIndex + 1 && $targetIndex <= $currentIndex) {
                return ['success' => false, 'error' => 'Straight-path mode requires moving to the next stage. You cannot skip or go backwards.'];
            }
        }

        $fromStageId = $application->currentStageId;
        $this->applications->updateStage($applicationId, $newStageId);
        $this->applications->recordStageMove($applicationId, $fromStageId, $newStageId, $this->auth?->user()?->id, $notes);

        $this->activity->log(
            'application.stage_moved',
            'applications',
            'Moved application ' . $application->code . ' to stage ' . $target->label,
            'application',
            $applicationId
        );

        return ['success' => true, 'error' => null];
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

    /**
     * Resolve the effective pipeline mode for a vacancy.
     * Engagement override > global default > 'open' fallback.
     */
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
