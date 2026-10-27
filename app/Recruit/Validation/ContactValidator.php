<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

/**
 * Validates submitted Contact data (base fields only).
 * Custom field validation handled by CustomFieldValueValidator.
 */
final class ContactValidator
{
    private const VALID_COMMS = ['email', 'phone', 'whatsapp', 'sms', 'any'];

    /**
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        // first_name (required)
        $first = trim((string) ($input['first_name'] ?? ''));
        if ($first === '') {
            $errors['first_name'][] = 'First name is required.';
        } elseif (mb_strlen($first) > 100) {
            $errors['first_name'][] = 'First name must be 100 characters or fewer.';
        }

        // last_name (required)
        $last = trim((string) ($input['last_name'] ?? ''));
        if ($last === '') {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (mb_strlen($last) > 100) {
            $errors['last_name'][] = 'Last name must be 100 characters or fewer.';
        }

        // position (optional)
        $pos = trim((string) ($input['position'] ?? ''));
        if ($pos !== '' && mb_strlen($pos) > 150) {
            $errors['position'][] = 'Position must be 150 characters or fewer.';
        }

        // department (optional)
        $dept = trim((string) ($input['department'] ?? ''));
        if ($dept !== '' && mb_strlen($dept) > 150) {
            $errors['department'][] = 'Department must be 150 characters or fewer.';
        }

        // email (optional)
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Email must be a valid address.';
            } elseif (mb_strlen($email) > 190) {
                $errors['email'][] = 'Email must be 190 characters or fewer.';
            }
        }

        // phone (optional)
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($phone !== '' && mb_strlen($phone) > 50) {
            $errors['phone'][] = 'Phone must be 50 characters or fewer.';
        }

        // alt_phone (optional)
        $altPhone = trim((string) ($input['alt_phone'] ?? ''));
        if ($altPhone !== '' && mb_strlen($altPhone) > 50) {
            $errors['alt_phone'][] = 'Alternative phone must be 50 characters or fewer.';
        }

        // country_code (optional)
        $cc = trim((string) ($input['country_code'] ?? ''));
        if ($cc !== '' && !preg_match('/^[A-Z]{2}$/i', $cc)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code (e.g. KE, UG, TZ).';
        }

        // preferred_communication (enum)
        $comms = (string) ($input['preferred_communication'] ?? 'email');
        if (!in_array($comms, self::VALID_COMMS, true)) {
            $errors['preferred_communication'][] = 'Invalid preferred communication method.';
        }

        return $errors;
    }

    public function normalize(array $input): array
    {
        return [
            'first_name'              => trim((string) ($input['first_name'] ?? '')),
            'last_name'               => trim((string) ($input['last_name'] ?? '')),
            'position'                => trim((string) ($input['position'] ?? '')) ?: null,
            'department'              => trim((string) ($input['department'] ?? '')) ?: null,
            'email'                   => trim((string) ($input['email'] ?? '')) ?: null,
            'phone'                   => trim((string) ($input['phone'] ?? '')) ?: null,
            'alt_phone'               => trim((string) ($input['alt_phone'] ?? '')) ?: null,
            'country_code'            => strtoupper(trim((string) ($input['country_code'] ?? ''))) ?: null,
            'preferred_communication' => (string) ($input['preferred_communication'] ?? 'email'),
            'is_primary'              => !empty($input['is_primary']),
            'is_hiring_manager'       => !empty($input['is_hiring_manager']),
            'is_finance'              => !empty($input['is_finance']),
            'is_hr'                   => !empty($input['is_hr']),
            'is_decision_maker'       => !empty($input['is_decision_maker']),
            'notes'                   => trim((string) ($input['notes'] ?? '')) ?: null,
        ];
    }
}
