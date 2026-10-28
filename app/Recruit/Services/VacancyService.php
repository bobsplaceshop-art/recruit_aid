<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Vacancy;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Deletion\DeletionResult;
use CorporateAid\Recruit\Deletion\Entities\VacancyEntity;
use CorporateAid\Recruit\Validation\VacancyValidator;
use CorporateAid\Platform\Deletion\DeletionService;

final class VacancyService
{
    public function __construct(
        private VacancyRepository $vacancies,
        private VacancyStageRepository $stages,
        private EngagementRepository $engagements,
        private VacancyValidator $validator,
        private CustomFieldValueValidator $cfValidator,
        private CustomFieldValueSaver $cfSaver,
        private DeletionService $deletion,
        private ActivityLogger $activity,
        private Database $db,
        private ?Auth $auth = null
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int, vacancy:?Vacancy, errors:string[], capacity_warning:?array<string, mixed>}
     */
    public function create(int $organisationId, array $input): array
    {
        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('vacancy', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['id' => 0, 'vacancy' => null, 'errors' => $allErrors, 'capacity_warning' => null];
        }

        $normalized = $this->validator->normalize($input);
        $normalized['organisation_id'] = $organisationId;
        $normalized['code'] = $this->vacancies->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        // Capacity check — if this vacancy is active and linked to an engagement
        $capacityWarning = null;
        $status = $normalized['status'];
        $isActive = !in_array($status, ['draft', 'cancelled', 'closed'], true);

        if ($isActive && !empty($normalized['engagement_id'])) {
            $eng = $this->engagements->findById((int) $normalized['engagement_id']);
            if ($eng) {
                $currentActive = $this->vacancies->activeCountForEngagement($eng->id);
                if ($currentActive >= $eng->agreedActiveRoleCapacity) {
                    $capacityWarning = [
                        'engagement_code' => $eng->code,
                        'engagement_name' => $eng->name,
                        'capacity'        => $eng->agreedActiveRoleCapacity,
                        'current_active'  => $currentActive,
                    ];
                }
            }
        }

        $id = $this->vacancies->create($normalized);

        // Clone pipeline stages for the new vacancy
        $this->stages->cloneForOrganisation($id, $organisationId);

        // Save requirements
        $requirements = $this->validator->extractRequirements($input);
        $this->vacancies->syncRequirements($id, $requirements);

        if (!empty($cfValues)) {
            $this->cfSaver->save('vacancy', $id, $cfValues);
        }

        $vacancy = $this->vacancies->findById($id);
        if ($vacancy === null) {
            return ['id' => $id, 'vacancy' => null, 'errors' => ['Vacancy was created but could not be retrieved.']];
        }

        $this->activity->log(
            'vacancy.created',
            'vacancies',
            'Created vacancy ' . $vacancy->title . ' (' . $vacancy->code . ')',
            'vacancy',
            $id
        );

        return [
            'id' => $id,
            'vacancy' => $vacancy,
            'errors' => [],
            'capacity_warning' => $capacityWarning,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool, errors:string[], changes:array<string, array{old:mixed,new:mixed}>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->vacancies->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Vacancy not found.'], 'changes' => []];
        }

        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('vacancy', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['success' => false, 'errors' => $allErrors, 'changes' => []];
        }

        $normalized = $this->validator->normalize($input);
        $changes = $this->vacancies->update($id, $normalized);

        $requirements = $this->validator->extractRequirements($input);
        $this->vacancies->syncRequirements($id, $requirements);

        if (!empty($cfValues)) {
            $this->cfSaver->save('vacancy', $id, $cfValues);
        }

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);
            $this->activity->log(
                'vacancy.updated',
                'vacancies',
                'Updated vacancy ' . $existing->title . ' (' . $existing->code . '): ' . $summary,
                'vacancy',
                $id,
                ['changes' => $changes]
            );
        }

        return ['success' => true, 'errors' => [], 'changes' => $changes];
    }

    public function archive(int $id, ?string $reason = null): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);
        if (!$vacancy) return DeletionResult::failure('Vacancy not found.');

        $entity = new VacancyEntity($vacancy, $this->db);
        return $this->deletion->archive($entity, $reason);
    }

    public function restore(int $id): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);
        if (!$vacancy) return DeletionResult::failure('Vacancy not found.');

        $entity = new VacancyEntity($vacancy, $this->db);
        return $this->deletion->restore($entity);
    }

    public function inspectDelete(int $id): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);
        if (!$vacancy) return DeletionResult::failure('Vacancy not found.');

        $entity = new VacancyEntity($vacancy, $this->db);
        return $this->deletion->inspect($entity);
    }

    public function delete(int $id, string $typedPhrase, ?string $reason = null): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);
        if (!$vacancy) return DeletionResult::failure('Vacancy not found.');

        $entity = new VacancyEntity($vacancy, $this->db);
        return $this->deletion->delete($entity, $typedPhrase, $reason);
    }

    // ---------- helpers ----------

    /**
     * @param array<string, string[]> $baseErrors
     * @param array<string, string[]> $cfErrors
     * @return string[]
     */
    private function flattenErrors(array $baseErrors, array $cfErrors): array
    {
        $out = [];
        foreach ($baseErrors as $msgs) {
            foreach ($msgs as $m) $out[] = $m;
        }
        foreach ($cfErrors as $msgs) {
            foreach ($msgs as $m) $out[] = $m;
        }
        return $out;
    }

    /** @param array<string, array{old:mixed,new:mixed}> $changes */
    private function summarizeChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $field => $change) {
            $old = $this->formatValue($change['old']);
            $new = $this->formatValue($change['new']);
            $parts[] = $field . ': "' . $old . '" → "' . $new . '"';
        }
        return implode(', ', $parts);
    }

    private function formatValue(mixed $v): string
    {
        if (is_array($v)) {
            $strs = [];
            foreach ($v as $part) {
                if (is_scalar($part)) $strs[] = (string) $part;
            }
            return implode(', ', $strs);
        }
        if (is_bool($v)) return $v ? 'yes' : 'no';
        if ($v === null) return '(empty)';
        return is_scalar($v) ? (string) $v : '';
    }
}
