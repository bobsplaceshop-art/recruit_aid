<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Vacancy;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyRecruiterRepository;
use CorporateAid\Recruit\Repositories\VacancyHiringManagerRepository;
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
        private VacancyRecruiterRepository $recruiters,
        private VacancyHiringManagerRepository $hiringManagers,
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
            return [
                'id' => 0,
                'vacancy' => null,
                'errors' => $allErrors,
                'capacity_warning' => null,
            ];
        }

        $normalized = $this->validator->normalize($input);
        $normalized['client_organisation_id'] = $organisationId;
        $normalized['code'] = $this->vacancies->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        $capacityWarning = null;
        $statusRaw = $normalized['status'] ?? 'draft';
        $status = is_scalar($statusRaw) ? (string) $statusRaw : 'draft';
        $isActive = !in_array($status, ['draft', 'cancelled', 'closed'], true);

        if ($isActive && !empty($normalized['engagement_id'])) {
            $engIdRaw = $normalized['engagement_id'];
            $engId = is_numeric($engIdRaw) ? (int) $engIdRaw : 0;
            $eng = $engId > 0 ? $this->engagements->findById($engId) : null;

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

        $id = 0;
        $vacancy = null;

        $this->db->beginTransaction();

        try {
            $id = $this->vacancies->create($normalized);

            $this->syncAssignments($id, $input);

            $this->stages->cloneForOrganisation($id, $organisationId);

            $requirements = $this->validator->extractRequirements($input);
            $this->vacancies->syncRequirements($id, $requirements);

            if (!empty($cfValues)) {
                $this->cfSaver->save('vacancy', $id, $cfValues);
            }

            $vacancy = $this->vacancies->findById($id);

            if ($vacancy === null) {
                throw new \RuntimeException(
                    'Vacancy was created but could not be retrieved.'
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->logActivitySafely(
            'vacancy.created',
            'Created vacancy ' . $vacancy->title . ' (' . $vacancy->code . ')',
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

        if (!$existing || $existing->isDeleted()) {
            return [
                'success' => false,
                'errors' => ['Vacancy not found.'],
                'changes' => [],
            ];
        }

        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('vacancy', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);

        if (!empty($allErrors)) {
            return [
                'success' => false,
                'errors' => $allErrors,
                'changes' => [],
            ];
        }

        $normalized = $this->validator->normalize($input);
        $changes = [];

        $this->db->beginTransaction();

        try {
            $changes = $this->vacancies->update($id, $normalized);

            $this->syncAssignments($id, $input);

            $requirements = $this->validator->extractRequirements($input);
            $this->vacancies->syncRequirements($id, $requirements);

            if (!empty($cfValues)) {
                $this->cfSaver->save('vacancy', $id, $cfValues);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);

            $this->logActivitySafely(
                'vacancy.updated',
                'Updated vacancy ' . $existing->title . ' (' . $existing->code . '): ' . $summary,
                $id,
                ['changes' => $changes]
            );
        }

        return [
            'success' => true,
            'errors' => [],
            'changes' => $changes,
        ];
    }

    public function archive(int $id, ?string $reason = null): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);

        if (!$vacancy) {
            return DeletionResult::failure('Vacancy not found.');
        }

        $entity = new VacancyEntity($vacancy, $this->db);

        return $this->deletion->archive($entity, $reason);
    }

    public function restore(int $id): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);

        if (!$vacancy) {
            return DeletionResult::failure('Vacancy not found.');
        }

        $entity = new VacancyEntity($vacancy, $this->db);

        return $this->deletion->restore($entity);
    }

    public function inspectDelete(int $id): DeletionResult
    {
        $vacancy = $this->vacancies->findById($id);

        if (!$vacancy) {
            return DeletionResult::failure('Vacancy not found.');
        }

        $entity = new VacancyEntity($vacancy, $this->db);

        return $this->deletion->inspect($entity);
    }

    public function delete(
        int $id,
        string $typedPhrase,
        ?string $reason = null
    ): DeletionResult {
        $vacancy = $this->vacancies->findById($id);

        if (!$vacancy) {
            return DeletionResult::failure('Vacancy not found.');
        }

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
            foreach ($msgs as $message) {
                $out[] = $message;
            }
        }

        foreach ($cfErrors as $msgs) {
            foreach ($msgs as $message) {
                $out[] = $message;
            }
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

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            $strings = [];

            foreach ($value as $part) {
                if (is_scalar($part)) {
                    $strings[] = (string) $part;
                }
            }

            return implode(', ', $strings);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value === null) {
            return '(empty)';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Activity logging must not turn a committed operation into an apparent
     * failure. Logging is deliberately performed after the transaction.
     *
     * @param array<string, mixed>|null $metadata
     */
    private function logActivitySafely(
        string $event,
        string $description,
        int $vacancyId,
        ?array $metadata = null
    ): void {
        try {
            $this->activity->log(
                $event,
                'vacancies',
                $description,
                'vacancy',
                $vacancyId,
                $metadata
            );
        } catch (\Throwable) {
            // The vacancy transaction has already committed.
            // Activity logging must not reverse or misreport that result.
        }
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);

            if ($parsed !== false && $parsed > 0) {
                return $parsed;
            }
        }

        throw new \InvalidArgumentException(
            "Invalid {$field} supplied to vacancy assignment."
        );
    }

    /**
     * Sync the recruiter and hiring-manager assignments.
     *
     * @param array<string, mixed> $input
     */
    private function syncAssignments(int $vacancyId, array $input): void
    {
        $lead = $this->positiveInteger(
            $input['recruiter_id'] ?? null,
            'lead recruiter ID'
        );

        $rawRecruiterIds = $input['recruiter_ids'] ?? [];

        if (!is_array($rawRecruiterIds)) {
            throw new \InvalidArgumentException(
                'Recruiter assignments must be supplied as a list.'
            );
        }

        $recruiterIds = [];

        foreach ($rawRecruiterIds as $value) {
            $recruiterIds[] = $this->positiveInteger(
                $value,
                'recruiter ID'
            );
        }

        array_unshift($recruiterIds, $lead);

        $recruiterIds = array_values(array_unique($recruiterIds));

        $this->recruiters->sync($vacancyId, $recruiterIds, $lead);

        $rawHiringManagerIds = $input['hiring_manager_contact_ids'] ?? [];

        if (!is_array($rawHiringManagerIds)) {
            throw new \InvalidArgumentException(
                'Hiring manager assignments must be supplied as a list.'
            );
        }

        $hiringManagerIds = [];

        foreach ($rawHiringManagerIds as $value) {
            $hiringManagerIds[] = $this->positiveInteger(
                $value,
                'hiring manager contact ID'
            );
        }

        $hiringManagerIds = array_values(array_unique($hiringManagerIds));

        $assignedBy = $this->auth?->user()?->id;

        $this->hiringManagers->sync(
            $vacancyId,
            $hiringManagerIds,
            $assignedBy
        );
    }
}
