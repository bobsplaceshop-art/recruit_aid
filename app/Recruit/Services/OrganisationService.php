<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Organisation;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Deletion\DeletionService;
use CorporateAid\Platform\Deletion\DeletionResult;
use CorporateAid\Recruit\Deletion\Entities\OrganisationEntity;
use CorporateAid\Recruit\Validation\OrganisationValidator;

final class OrganisationService
{
    public function __construct(
        private OrganisationRepository $organisations,
        private OrganisationValidator $validator,
        private CustomFieldValueValidator $cfValidator,
        private CustomFieldValueSaver $cfSaver,
        private DeletionService $deletion,
        private ActivityLogger $activity,
        private Database $db,
        private ?Auth $auth = null
    ) {
    }

    /**
     * Create a new organisation.
     *
     * @param array<string, mixed> $input
     * @return array{id:int, organisation:Organisation|null, errors:string[]}
     */
    public function create(array $input): array
    {
        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('organisation', $cfValues);

        // Merge errors for the caller (form re-render)
        $allErrors = [];
        foreach ($baseErrors as $field => $msgs) {
            foreach ($msgs as $m) $allErrors[] = $m;
        }
        foreach ($cfErrors as $fieldKey => $msgs) {
            foreach ($msgs as $m) $allErrors[] = $m;
        }

        if (!empty($allErrors)) {
            return ['id' => 0, 'organisation' => null, 'errors' => $allErrors];
        }

        // Duplicate detection — check for existing org with same email
        $email = strtolower(trim(Row::str($input, 'email')));
        if ($email !== '') {
            $existing = $this->organisations->findByEmail($email);
            if ($existing !== null) {
                return [
                    'id' => 0,
                    'organisation' => null,
                    'errors' => [
                        'An organisation with this email already exists: ' . $existing->name . ' (' . $existing->code . ').',
                    ],
                ];
            }
        }

        $normalized = $this->validator->normalize($input);
        $normalized['code'] = $this->organisations->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        $id = $this->organisations->create($normalized);

        // Save custom field values (including any uploaded files)
        if (!empty($cfValues)) {
            $this->cfSaver->save('organisation', $id, $cfValues);
        }

        $org = $this->organisations->findById($id);
        if ($org === null) {
            return ['id' => $id, 'organisation' => null, 'errors' => ['Organisation was created but could not be retrieved.']];
        }

        $this->activity->log(
            'organisation.created',
            'organisations',
            'Created organisation ' . $org->name . ' (' . $org->code . ')',
            'organisation',
            $id
        );

        return ['id' => $id, 'organisation' => $org, 'errors' => []];
    }

    /**
     * Update an existing organisation.
     *
     * @param array<string, mixed> $input
     * @return array{success:bool, errors:string[], changes:array<string, array{old:mixed,new:mixed}>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->organisations->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Organisation not found.'], 'changes' => []];
        }

        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('organisation', $cfValues);

        $allErrors = [];
        foreach ($baseErrors as $field => $msgs) {
            foreach ($msgs as $m) $allErrors[] = $m;
        }
        foreach ($cfErrors as $fieldKey => $msgs) {
            foreach ($msgs as $m) $allErrors[] = $m;
        }

        if (!empty($allErrors)) {
            return ['success' => false, 'errors' => $allErrors, 'changes' => []];
        }

        // Duplicate detection — excluding the current record
        $email = strtolower(trim(Row::str($input, 'email')));
        if ($email !== '') {
            $conflict = $this->organisations->findByEmail($email, $id);
            if ($conflict !== null) {
                return [
                    'success' => false,
                    'errors' => [
                        'An organisation with this email already exists: ' . $conflict->name . ' (' . $conflict->code . ').',
                    ],
                    'changes' => [],
                ];
            }
        }

        $normalized = $this->validator->normalize($input);
        $changes = $this->organisations->update($id, $normalized);

        if (!empty($cfValues)) {
            $this->cfSaver->save('organisation', $id, $cfValues);
        }

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);
            $this->activity->log(
                'organisation.updated',
                'organisations',
                'Updated organisation ' . $existing->name . ' (' . $existing->code . '): ' . $summary,
                'organisation',
                $id,
                ['changes' => $changes]
            );
        }

        return ['success' => true, 'errors' => [], 'changes' => $changes];
    }

    public function archive(int $id, ?string $reason = null): DeletionResult
    {
        $org = $this->organisations->findById($id);
        if (!$org) return DeletionResult::failure('Organisation not found.');

        $entity = new OrganisationEntity($org, $this->db);
        return $this->deletion->archive($entity, $reason);
    }

    public function restore(int $id): DeletionResult
    {
        $org = $this->organisations->findById($id);
        if (!$org) return DeletionResult::failure('Organisation not found.');

        $entity = new OrganisationEntity($org, $this->db);
        return $this->deletion->restore($entity);
    }

    /**
     * Inspect what delete would do (dependents, required phrase).
     */
    public function inspectDelete(int $id): DeletionResult
    {
        $org = $this->organisations->findById($id);
        if (!$org) return DeletionResult::failure('Organisation not found.');

        $entity = new OrganisationEntity($org, $this->db);
        return $this->deletion->inspect($entity);
    }

    public function delete(int $id, string $typedPhrase, ?string $reason = null): DeletionResult
    {
        $org = $this->organisations->findById($id);
        if (!$org) return DeletionResult::failure('Organisation not found.');

        $entity = new OrganisationEntity($org, $this->db);
        return $this->deletion->delete($entity, $typedPhrase, $reason);
    }

    // ---------- helpers ----------

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
