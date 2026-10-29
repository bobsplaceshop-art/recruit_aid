<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

use CorporateAid\Core\Row;

/**
 * Validates submitted Contact data (base fields only).
 * Custom field validation handled by CustomFieldValueValidator.
 */
final class ContactValidator
{
    private const VALID_COMMS = ['email', 'phone', 'whatsapp', 'sms', 'any'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $first = trim(Row::str($input, 'first_name'));
        if ($first === '') {
            $errors['first_name'][] = 'First name is required.';
        } elseif (mb_strlen($first) > 100) {
            $errors['first_name'][] = 'First name must be 100 characters or fewer.';
        }

        $last = trim(Row::str($input, 'last_name'));
        if ($last === '') {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (mb_strlen($last) > 100) {
            $errors['last_name'][] = 'Last name must be 100 characters or fewer.';
        }

        $pos = trim(Row::str($input, 'position'));
        if ($pos !== '' && mb_strlen($pos) > 150) {
            $errors['position'][] = 'Position must be 150 characters or fewer.';
        }

        $dept = trim(Row::str($input, 'department'));
        if ($dept !== '' && mb_strlen($dept) > 150) {
            $errors['department'][] = 'Department must be 150 characters or fewer.';
        }

        $email = trim(Row::str($input, 'email'));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Email must be a valid address.';
            } elseif (mb_strlen($email) > 190) {
                $errors['email'][] = 'Email must be 190 characters or fewer.';
            }
        }

        $phone = trim(Row::str($input, 'phone'));
        if ($phone !== '' && mb_strlen($phone) > 50) {
            $errors['phone'][] = 'Phone must be 50 characters or fewer.';
        }

        $altPhone = trim(Row::str($input, 'alt_phone'));
        if ($altPhone !== '' && mb_strlen($altPhone) > 50) {
            $errors['alt_phone'][] = 'Alternative phone must be 50 characters or fewer.';
        }

        $cc = trim(Row::str($input, 'country_code'));
        if ($cc !== '' && !preg_match('/^[A-Z]{2}$/i', $cc)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code (e.g. KE, UG, TZ).';
        }

        $comms = Row::str($input, 'preferred_communication', 'email');
        if (!in_array($comms, self::VALID_COMMS, true)) {
            $errors['preferred_communication'][] = 'Invalid preferred communication method.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        return [
            'first_name'              => trim(Row::str($input, 'first_name')),
            'last_name'               => trim(Row::str($input, 'last_name')),
            'position'                => trim(Row::str($input, 'position')) ?: null,
            'department'              => trim(Row::str($input, 'department')) ?: null,
            'email'                   => trim(Row::str($input, 'email')) ?: null,
            'phone'                   => trim(Row::str($input, 'phone')) ?: null,
            'alt_phone'               => trim(Row::str($input, 'alt_phone')) ?: null,
            'country_code'            => strtoupper(trim(Row::str($input, 'country_code'))) ?: null,
            'preferred_communication' => Row::str($input, 'preferred_communication', 'email'),
            'is_primary'              => Row::bool($input, 'is_primary'),
            'is_hiring_manager'       => Row::bool($input, 'is_hiring_manager'),
            'is_finance'              => Row::bool($input, 'is_finance'),
            'is_hr'                   => Row::bool($input, 'is_hr'),
            'is_decision_maker'       => Row::bool($input, 'is_decision_maker'),
            // NEW: roles[] array from the pivot-driven form. When present,
            // this supersedes the boolean flags (service will call syncRoles).
            'roles'                   => $this->normalizeRoles($input),
            'notes'                   => trim(Row::str($input, 'notes')) ?: null,
        ];
    }

    /**
     * Normalize a roles[] array from form input.
     * Accepts array of slugs (from checkboxes) or CSV string.
     * Returns unique, lowercased, non-empty slugs.
     *
     * @param array<string, mixed> $input
     * @return string[]
     */
    private function normalizeRoles(array $input): array
    {
        $raw = $input['roles'] ?? [];
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $v) {
            if (!is_scalar($v)) continue;
            $slug = strtolower(trim((string) $v));
            if ($slug !== '') $out[$slug] = true;
        }
        return array_keys($out);
    }
}
