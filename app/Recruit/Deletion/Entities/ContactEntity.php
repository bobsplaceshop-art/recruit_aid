<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Contact;
use CorporateAid\Platform\Deletion\DeletableEntity;

final class ContactEntity implements DeletableEntity
{
    public function __construct(
        private Contact $contact,
        private Database $db
    ) {
    }

    public function entityType(): string
    {
        return 'contact';
    }

    public function entityId(): int
    {
        return $this->contact->id;
    }

    public function entityLabel(): string
    {
        return $this->contact->fullName() . ' (' . $this->contact->code . ')';
    }

    public function getDependents(): array
    {
        $dependents = [];

        // Custom field values for this contact
        $customValueCount = $this->safeCountEntity('custom_field_values', 'contact');
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

        // Delete custom field values
        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = ? AND `entity_id` = ?",
            ['contact', $this->contact->id]
        );

        // Delete the contact
        $deleted += $this->db->execute(
            "DELETE FROM `recruit_client_contacts` WHERE `id` = ?",
            [$this->contact->id]
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NOW() WHERE `id` = ?",
            [$this->contact->id]
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NULL WHERE `id` = ?",
            [$this->contact->id]
        );
    }

    private function safeCountEntity(string $table, string $entityType): int
    {
        try {
            $count = $this->db->scalar(
                "SELECT COUNT(*) FROM `{$table}` WHERE `entity_type` = ? AND `entity_id` = ?",
                [$entityType, $this->contact->id]
            );
            return (int) ($count ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
