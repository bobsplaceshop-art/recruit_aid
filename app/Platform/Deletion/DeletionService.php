<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Deletion;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Deletion\ConfirmationPhrase;
use CorporateAid\Platform\Deletion\DeletableEntity;
use CorporateAid\Platform\Deletion\DeletionResult;

/**
 * Central deletion/archive/restore service.
 * Every module uses this. No module writes its own deletion logic.
 */
final class DeletionService
{
    public function __construct(
        private Database $db,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {
    }

    public function inspect(DeletableEntity $entity): DeletionResult
    {
        if (!$this->canDelete($entity)) {
            return DeletionResult::failure(
                'You do not have permission to delete this record. Contact your administrator.'
            );
        }

        $dependents = $entity->getDependents();
        $hasDependents = !empty($dependents);
        $phrase = ConfirmationPhrase::forSingle($hasDependents);

        return DeletionResult::inspection($hasDependents, $dependents, $phrase);
    }

    public function delete(
        DeletableEntity $entity,
        string $typedPhrase,
        ?string $reason
    ): DeletionResult {
        if (!$this->canDelete($entity)) {
            return DeletionResult::failure(
                'You do not have permission to delete this record.'
            );
        }

        $dependents = $entity->getDependents();
        $hasDependents = !empty($dependents);
        $requiredPhrase = ConfirmationPhrase::forSingle($hasDependents);

        if (!ConfirmationPhrase::verify($requiredPhrase, $typedPhrase)) {
            return DeletionResult::failure(
                'Confirmation phrase does not match. Please type exactly: ' . $requiredPhrase
            );
        }

        $this->db->beginTransaction();
        try {
            $deleted = 0;
            if ($hasDependents) {
                $deleted = $entity->cascadeDelete();
            } else {
                $deleted = $entity->cascadeDelete();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->logDeletion($entity, $requiredPhrase, $reason, $deleted, $dependents);

        return DeletionResult::success(
            'Deleted ' . $entity->entityLabel() . ' and ' . max(0, $deleted - 1) . ' dependent record(s).',
            $deleted
        );
    }

    public function archive(DeletableEntity $entity, ?string $reason = null): DeletionResult
    {
        if (!$this->canDelete($entity)) {
            return DeletionResult::failure('You do not have permission to archive this record.');
        }

        $entity->archive();

        $this->activity->log(
            $entity->entityType() . '.archived',
            $entity->entityType(),
            'Archived ' . $entity->entityLabel()
                . ($reason ? ' — Reason: ' . $reason : ''),
            $entity->entityType(),
            $entity->entityId()
        );

        return DeletionResult::success('Archived ' . $entity->entityLabel() . '.');
    }

    public function restore(DeletableEntity $entity): DeletionResult
    {
        if (!$this->canDelete($entity)) {
            return DeletionResult::failure('You do not have permission to restore this record.');
        }

        $entity->restore();

        $this->activity->log(
            $entity->entityType() . '.restored',
            $entity->entityType(),
            'Restored ' . $entity->entityLabel(),
            $entity->entityType(),
            $entity->entityId()
        );

        return DeletionResult::success('Restored ' . $entity->entityLabel() . '.');
    }

    /**
     * Bulk delete. First inspects ALL records.
     *
     * @param array<int, DeletableEntity> $entities
     */
    public function deleteBulk(array $entities, string $typedPhrase, ?string $reason = null): DeletionResult
    {
        if (empty($entities)) {
            return DeletionResult::failure('No records selected.');
        }

        foreach ($entities as $entity) {
            if (!$this->canDelete($entity)) {
                return DeletionResult::failure(
                    'You do not have permission to delete ' . $entity->entityLabel() . '.'
                );
            }
        }

        $anyDependents = false;
        foreach ($entities as $entity) {
            if (!empty($entity->getDependents())) {
                $anyDependents = true;
                break;
            }
        }

        $requiredPhrase = ConfirmationPhrase::forBulk(count($entities), $anyDependents);
        if (!ConfirmationPhrase::verify($requiredPhrase, $typedPhrase)) {
            return DeletionResult::failure(
                'Confirmation phrase does not match. Please type exactly: ' . $requiredPhrase
            );
        }

        $this->db->beginTransaction();
        $totalDeleted = 0;
        try {
            foreach ($entities as $entity) {
                $totalDeleted += $entity->cascadeDelete();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->activity->log(
            'bulk.deleted',
            'system',
            'Bulk deleted ' . count($entities) . ' record(s). Reason: ' . ($reason ?? '(none)')
                . '. Confirmation typed: "' . $requiredPhrase . '"',
            null,
            null,
            ['count' => count($entities), 'total_deleted' => $totalDeleted, 'reason' => $reason]
        );

        return DeletionResult::success(
            'Deleted ' . count($entities) . ' record(s), ' . $totalDeleted . ' including dependents.',
            $totalDeleted
        );
    }

    private function canDelete(DeletableEntity $entity): bool
    {
        if ($this->auth === null) return false;
        $user = $this->auth->user();
        if ($user === null) return false;
        return $user->can($entity->entityType() . '.delete');
    }

    /**
     * @param array<int, array{label:string, count:int, route:string}> $dependents
     */
    private function logDeletion(
        DeletableEntity $entity,
        string $phrase,
        ?string $reason,
        int $totalDeleted,
        array $dependents
    ): void {
        $dependencySummary = '';
        if (!empty($dependents)) {
            $parts = [];
            foreach ($dependents as $d) {
                $parts[] = $d['count'] . ' ' . $d['label'];
            }
            $dependencySummary = ' Dependents also deleted: ' . implode(', ', $parts) . '.';
        }

        $this->activity->log(
            $entity->entityType() . '.deleted',
            $entity->entityType(),
            'Permanently deleted ' . $entity->entityLabel() . '.'
                . $dependencySummary
                . ' Reason: ' . ($reason ?? '(none)')
                . '. Confirmation typed: "' . $phrase . '".',
            $entity->entityType(),
            $entity->entityId(),
            [
                'total_deleted' => $totalDeleted,
                'reason' => $reason,
                'dependents' => $dependents,
            ]
        );
    }
}
