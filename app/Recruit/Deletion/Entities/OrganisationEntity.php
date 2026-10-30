<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\Organisation;
use CorporateAid\Platform\Deletion\DeletableEntity;

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

    /** @return array<int, array{label:string, count:int, route:string}> */
    public function getDependents(): array
    {
        $dependents = [];

        $contactCount = $this->safeCount('recruit_client_contacts', 'client_organisation_id');
        if ($contactCount > 0) {
            $dependents[] = [
                'label' => $contactCount === 1 ? 'Contact' : 'Contacts',
                'count' => $contactCount,
                'route' => '/organisations/' . $this->organisation->code . '/contacts',
            ];
        }

        $engagementCount = $this->safeCount('recruit_engagements', 'client_organisation_id');
        if ($engagementCount > 0) {
            $dependents[] = [
                'label' => $engagementCount === 1 ? 'Engagement' : 'Engagements',
                'count' => $engagementCount,
                'route' => '/organisations/' . $this->organisation->code . '/engagements',
            ];
        }

        $vacancyCount = $this->safeCount('recruit_vacancies', 'client_organisation_id');
        if ($vacancyCount > 0) {
            $dependents[] = [
                'label' => $vacancyCount === 1 ? 'Vacancy' : 'Vacancies',
                'count' => $vacancyCount,
                'route' => '/organisations/' . $this->organisation->code . '/vacancies',
            ];
        }

        $customValueCount = $this->safeCountEntity('core_custom_field_values', 'organisation');
        if ($customValueCount > 0) {
            $dependents[] = [
                'label' => 'Custom field value' . ($customValueCount === 1 ? '' : 's'),
                'count' => $customValueCount,
                'route' => '#',
            ];
        }

        return $dependents;
    }

    public function cascadeDelete(): int
    {
        $deleted = 0;

        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = ? AND `entity_id` = ?",
            PDOParams::positional(['organisation', $this->organisation->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_client_organisations` WHERE `id` = ?",
            PDOParams::positional([$this->organisation->id])
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$this->organisation->id])
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$this->organisation->id])
        );
    }

    private function safeCount(string $table, string $foreignKey): int
    {
        try {
            return $this->db->scalarInt(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = ?",
                PDOParams::positional([$this->organisation->id])
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeCountEntity(string $table, string $entityType): int
    {
        try {
            return $this->db->scalarInt(
                "SELECT COUNT(*) FROM `{$table}` WHERE `entity_type` = ? AND `entity_id` = ?",
                PDOParams::positional([$entityType, $this->organisation->id])
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
