<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Engagement;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Deletion\DeletionResult;
use CorporateAid\Recruit\Deletion\Entities\EngagementEntity;
use CorporateAid\Recruit\Validation\EngagementValidator;
use CorporateAid\Platform\Deletion\DeletionService;

final class EngagementService
{
    public function __construct(
        private EngagementRepository $engagements,
        private EngagementValidator $validator,
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
     * @return array{id:int, engagement:?Engagement, errors:string[]}
     */
    public function create(int $organisationId, array $input): array
    {
        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('engagement', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['id' => 0, 'engagement' => null, 'errors' => $allErrors];
        }

        $normalized = $this->validator->normalize($input);
        $normalized['organisation_id'] = $organisationId;
        $normalized['code'] = $this->engagements->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        $id = $this->engagements->create($normalized);

        if (!empty($cfValues)) {
            $this->cfSaver->save('engagement', $id, $cfValues);
        }

        $engagement = $this->engagements->findById($id);
        if ($engagement === null) {
            return ['id' => $id, 'engagement' => null, 'errors' => ['Engagement was created but could not be retrieved.']];
        }

        $this->activity->log(
            'engagement.created',
            'engagements',
            'Created engagement ' . $engagement->name . ' (' . $engagement->code . ') for org ID ' . $organisationId,
            'engagement',
            $id
        );

        return ['id' => $id, 'engagement' => $engagement, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool, errors:string[], changes:array<string, array{old:mixed,new:mixed}>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->engagements->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Engagement not found.'], 'changes' => []];
        }

        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('engagement', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['success' => false, 'errors' => $allErrors, 'changes' => []];
        }

        $normalized = $this->validator->normalize($input);
        $changes = $this->engagements->update($id, $normalized);

        if (!empty($cfValues)) {
            $this->cfSaver->save('engagement', $id, $cfValues);
        }

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);
            $this->activity->log(
                'engagement.updated',
                'engagements',
                'Updated engagement ' . $existing->name . ' (' . $existing->code . '): ' . $summary,
                'engagement',
                $id,
                ['changes' => $changes]
            );
        }

        return ['success' => true, 'errors' => [], 'changes' => $changes];
    }

    public function archive(int $id, ?string $reason = null): DeletionResult
    {
        $engagement = $this->engagements->findById($id);
        if (!$engagement) return DeletionResult::failure('Engagement not found.');

        $entity = new EngagementEntity($engagement, $this->db);
        return $this->deletion->archive($entity, $reason);
    }

    public function restore(int $id): DeletionResult
    {
        $engagement = $this->engagements->findById($id);
        if (!$engagement) return DeletionResult::failure('Engagement not found.');

        $entity = new EngagementEntity($engagement, $this->db);
        return $this->deletion->restore($entity);
    }

    public function inspectDelete(int $id): DeletionResult
    {
        $engagement = $this->engagements->findById($id);
        if (!$engagement) return DeletionResult::failure('Engagement not found.');

        $entity = new EngagementEntity($engagement, $this->db);
        return $this->deletion->inspect($entity);
    }

    public function delete(int $id, string $typedPhrase, ?string $reason = null): DeletionResult
    {
        $engagement = $this->engagements->findById($id);
        if (!$engagement) return DeletionResult::failure('Engagement not found.');

        $entity = new EngagementEntity($engagement, $this->db);
        return $this->deletion->delete($entity, $typedPhrase, $reason);
    }

    // ---------- Recruiter assignment ----------

    public function assignRecruiter(int $engagementId, int $userId, string $role = 'recruiter'): void
    {
        $this->engagements->assignRecruiter($engagementId, $userId, $role);

        $engagement = $this->engagements->findById($engagementId);
        if ($engagement) {
            $this->activity->log(
                'engagement.recruiter_assigned',
                'engagements',
                'Assigned user #' . $userId . ' as ' . $role . ' on engagement ' . $engagement->code,
                'engagement',
                $engagementId
            );
        }
    }

    public function unassignRecruiter(int $engagementId, int $userId): void
    {
        $this->engagements->unassignRecruiter($engagementId, $userId);

        $engagement = $this->engagements->findById($engagementId);
        if ($engagement) {
            $this->activity->log(
                'engagement.recruiter_unassigned',
                'engagements',
                'Unassigned user #' . $userId . ' from engagement ' . $engagement->code,
                'engagement',
                $engagementId
            );
        }
    }

    // ---------- Capacity ----------

    /**
     * @return array{capacity:int, used:int, available:int, exceeded:bool}
     */
    public function capacityStatus(int $engagementId): array
    {
        $engagement = $this->engagements->findById($engagementId);
        if (!$engagement) {
            return ['capacity' => 0, 'used' => 0, 'available' => 0, 'exceeded' => false];
        }

        $capacity = $engagement->agreedActiveRoleCapacity;
        $used = $this->engagements->activeVacancyCount($engagementId);
        $available = max(0, $capacity - $used);

        return [
            'capacity'  => $capacity,
            'used'      => $used,
            'available' => $available,
            'exceeded'  => $used >= $capacity,
        ];
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
        if ($v === null) return '(empty)';
        return is_scalar($v) ? (string) $v : '';
    }
}
