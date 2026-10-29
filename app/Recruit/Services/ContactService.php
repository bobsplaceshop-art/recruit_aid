<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Contact;
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Deletion\DeletionResult;
use CorporateAid\Recruit\Deletion\Entities\ContactEntity;
use CorporateAid\Recruit\Validation\ContactValidator;
use CorporateAid\Platform\Deletion\DeletionService;

final class ContactService
{
    public function __construct(
        private ContactRepository $contacts,
        private ContactValidator $validator,
        private CustomFieldValueValidator $cfValidator,
        private CustomFieldValueSaver $cfSaver,
        private DeletionService $deletion,
        private ActivityLogger $activity,
        private Database $db,
        private ?Auth $auth = null
    ) {
    }

    /**
     * Create a new contact for an organisation.
     *
     * @param array<string, mixed> $input
     * @return array{id:int, contact:?Contact, errors:string[]}
     */
    public function create(int $organisationId, array $input): array
    {
        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('contact', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['id' => 0, 'contact' => null, 'errors' => $allErrors];
        }

        $normalized = $this->validator->normalize($input);
        /** @var array{organisation_id: int, code: string, first_name: string, last_name: string, position?: string|null, department?: string|null, email?: string|null, phone?: string|null, alt_phone?: string|null, country_code?: string|null, preferred_communication?: string, is_primary?: bool, is_hiring_manager?: bool, is_finance?: bool, is_hr?: bool, is_decision_maker?: bool, notes?: string|null, created_by?: int|null} $normalized */
        $normalized['organisation_id'] = $organisationId;
        $normalized['code'] = $this->contacts->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        $id = $this->contacts->create($normalized);

        if (!empty($cfValues)) {
            $this->cfSaver->save('contact', $id, $cfValues);
        }

        $contact = $this->contacts->findById($id);
        if ($contact === null) {
            return ['id' => $id, 'contact' => null, 'errors' => ['Contact was created but could not be retrieved.']];
        }

        $this->activity->log(
            'contact.created',
            'contacts',
            'Created contact ' . $contact->fullName() . ' (' . $contact->code . ')',
            'contact',
            $id
        );

        return ['id' => $id, 'contact' => $contact, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool, errors:string[], changes:array<string, array{old:mixed,new:mixed}>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->contacts->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Contact not found.'], 'changes' => []];
        }

        $baseErrors = $this->validator->validate($input);
        $cfValues = is_array($input['cf'] ?? null) ? $input['cf'] : [];
        $cfErrors = $this->cfValidator->validate('contact', $cfValues);

        $allErrors = $this->flattenErrors($baseErrors, $cfErrors);
        if (!empty($allErrors)) {
            return ['success' => false, 'errors' => $allErrors, 'changes' => []];
        }

        $normalized = $this->validator->normalize($input);
        $changes = $this->contacts->update($id, $normalized);

        if (!empty($cfValues)) {
            $this->cfSaver->save('contact', $id, $cfValues);
        }

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);
            $this->activity->log(
                'contact.updated',
                'contacts',
                'Updated contact ' . $existing->fullName() . ' (' . $existing->code . '): ' . $summary,
                'contact',
                $id,
                ['changes' => $changes]
            );
        }

        return ['success' => true, 'errors' => [], 'changes' => $changes];
    }

    public function archive(int $id, ?string $reason = null): DeletionResult
    {
        $contact = $this->contacts->findById($id);
        if (!$contact) return DeletionResult::failure('Contact not found.');

        $entity = new ContactEntity($contact, $this->db);
        return $this->deletion->archive($entity, $reason);
    }

    public function restore(int $id): DeletionResult
    {
        $contact = $this->contacts->findById($id);
        if (!$contact) return DeletionResult::failure('Contact not found.');

        $entity = new ContactEntity($contact, $this->db);
        return $this->deletion->restore($entity);
    }

    public function inspectDelete(int $id): DeletionResult
    {
        $contact = $this->contacts->findById($id);
        if (!$contact) return DeletionResult::failure('Contact not found.');

        $entity = new ContactEntity($contact, $this->db);
        return $this->deletion->inspect($entity);
    }

    public function delete(int $id, string $typedPhrase, ?string $reason = null): DeletionResult
    {
        $contact = $this->contacts->findById($id);
        if (!$contact) return DeletionResult::failure('Contact not found.');

        $entity = new ContactEntity($contact, $this->db);
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
        foreach ($baseErrors as $field => $msgs) {
            foreach ($msgs as $m) $out[] = $m;
        }
        foreach ($cfErrors as $fieldKey => $msgs) {
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
        if (is_bool($v)) return $v ? 'yes' : 'no';
        if (is_array($v)) {
            $parts = [];
            foreach ($v as $part) {
                if (is_scalar($part)) $parts[] = (string) $part;
            }
            return implode(', ', $parts);
        }
        if ($v === null) return '(empty)';
        return is_scalar($v) ? (string) $v : '';
    }
}
