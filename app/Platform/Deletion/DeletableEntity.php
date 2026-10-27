<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Deletion;

/**
 * Contract for any entity that can be deleted or archived through DeletionService.
 *
 * Each entity (Organisation, Contact, Candidate, etc.) implements this
 * interface. The service handles permissions, confirmation phrases, audit
 * logging, and cascade ordering — the entity only knows how to describe
 * itself and how to perform its own cascade.
 */
interface DeletableEntity
{
    /**
     * Machine name of the entity type. Must match the permission prefix.
     * Examples: 'organisation', 'contact', 'candidate', 'vacancy'.
     */
    public function entityType(): string;

    /**
     * Numeric primary key of the record.
     */
    public function entityId(): int;

    /**
     * Human-readable identifier used in audit logs and confirmation dialogs.
     * Examples: 'Acme Corporation (ORG-000042)', 'Jane Doe (CAND-000123)'.
     */
    public function entityLabel(): string;

    /**
     * List of dependents that would be deleted or affected by cascading.
     * Return an empty array if there are no dependents.
     *
     * Each item: ['label' => 'Contacts', 'count' => 3, 'route' => '/organisations/ORG-000042/contacts']
     *
     * @return array<int, array{label:string, count:int, route:string}>
     */
    public function getDependents(): array;

    /**
     * Delete this record and its dependents in the correct order.
     * Called inside a database transaction managed by DeletionService.
     *
     * @return int  Number of records deleted (including the parent)
     */
    public function cascadeDelete(): int;

    /**
     * Archive (soft delete) this record. Should set a `deleted_at` timestamp.
     */
    public function archive(): void;

    /**
     * Restore an archived record. Should clear the `deleted_at` timestamp.
     */
    public function restore(): void;
}
