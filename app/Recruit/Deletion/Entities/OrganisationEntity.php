<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Organisation;
use CorporateAid\Platform\Deletion\DeletableEntity;

/**
 * Wraps an Organisation so DeletionService can archive/delete it
 * with proper dependency checks.
 */
final class OrganisationEntity implements DeletableEntity
{
    public function __construct(
        private Organisation $organisation,
        private Database $db
    ) {
    }

    public function entityType(): string
    {
        return 'organisation';
    }

    public function entityId(): int
    {
        return $this->organisation->id;
    }

    public function entityLabel(): string
    {
        return $this->organisation->name . ' (' . $this->organisation->code . ')';
    }

    public function getDependents(): array
    {
        // These tables don't exist yet. Counts return 0 for now.
        // When each child module is built, we'll plug the queries in here.
        $dependents = [];

        // Contacts (future)
        $contactCount = $this->safeCount('organisation_contacts', 'organisation_id');
        if ($contactCount > 0) {
            $dependents[] = [
                'label' => $contactCount === 1 ? 'Contact' : 'Contacts',
                'count' => $contactCount,
                'route' => '/organisations/' . $this->organisation->code . '/contacts',
            ];
        }

        // Engagements (future)
        $engagementCount = $this->safeCount('engagements', 'organisation_id');
        if ($engagementCount > 0) {
            $dependents[] = [
                'label' => $engagementCount === 1 ? 'Engagement' : 'Engagements',
                'count' => $engagementCount,
                'route' => '/organisations/' . $this->organisation->code . '/engagements',
            ];
        }

        // Vacancies (future)
        $vacancyCount = $this->safeCount('vacancies', 'organisation_id');
        if ($vacancyCount > 0) {
            $dependents[] = [
                'label' => $vacancyCount === 1 ? 'Vacancy' : 'Vacancies',
                'count' => $vacancyCount,
                'route' => '/organisations/' . $this->organisation->code . '/vacancies',
            ];
        }

        // Custom field values (already exists)
        $customValueCount = $this->safeCountEntity('custom_field_values', 'organisation');
        if ($customValueCount > 0) {
            $dependents[] = [
                'label' => 'Custom field value' . ($customValueCount === 1 ? '' : 's'),
                'count' => $customValueCount,
                'route' => '#', // no view for these
            ];
        }

        return $dependents;
    }

    public function cascadeDelete(): int
    {
        $deleted = 0;

        // Custom field values (children)
        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = ? AND `entity_id` = ?",
            ['organisation', $this->organisation->id]
        );

        // Future: cascades for contacts, engagements, vacancies go here.

        // The organisation itself
        $deleted += $this->db->execute(
            "DELETE FROM `recruit_client_organisations` WHERE `id` = ?",
            [$this->organisation->id]
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NOW() WHERE `id` = ?",
            [$this->organisation->id]
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NULL WHERE `id` = ?",
            [$this->organisation->id]
        );
    }

    // ---------- helpers ----------

    /**
     * Count rows in a child table IF the table exists.
     * Returns 0 otherwise. This lets us build Organisations now
     * and plug in child modules later without breaking anything.
     */
    private function safeCount(string $table, string $foreignKey): int
    {
        try {
            $count = $this->db->scalar(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = ?",
                [$this->organisation->id]
            );
            return (int) ($count ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeCountEntity(string $table, string $entityType): int
    {
        try {
            $count = $this->db->scalar(
                "SELECT COUNT(*) FROM `{$table}` WHERE `entity_type` = ? AND `entity_id` = ?",
                [$entityType, $this->organisation->id]
            );
            return (int) ($count ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
