<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

use CorporateAid\Core\Row;

/**
 * Validates submitted Organisation data (base fields only).
 * Custom field validation is handled by CustomFieldValueValidator.
 */
final class OrganisationValidator
{
    private const VALID_STATUSES = ['prospect', 'active', 'inactive', 'former_client'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $name = trim(Row::str($input, 'name'));
        if ($name === '') {
            $errors['name'][] = 'Organisation name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = 'Name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'][] = 'Name must be 255 characters or fewer.';
        }

        $legalName = trim(Row::str($input, 'legal_name'));
        if ($legalName !== '' && mb_strlen($legalName) > 255) {
            $errors['legal_name'][] = 'Legal name must be 255 characters or fewer.';
        }

        $industry = trim(Row::str($input, 'industry'));
        if ($industry !== '' && mb_strlen($industry) > 100) {
            $errors['industry'][] = 'Industry must be 100 characters or fewer.';
        }

        $website = trim(Row::str($input, 'website'));
        if ($website !== '') {
            $parts = parse_url($website);
            if ($parts === false || empty($parts['host']) || empty($parts['scheme'])
                || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                $errors['website'][] = 'Website must be a valid URL starting with http:// or https://.';
            } elseif (mb_strlen($website) > 255) {
                $errors['website'][] = 'Website must be 255 characters or fewer.';
            }
        }

        $countryCode = trim(Row::str($input, 'country_code'));
        if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/i', $countryCode)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code (e.g. KE, UG, TZ).';
        }

        $phone = trim(Row::str($input, 'phone'));
        if ($phone !== '' && mb_strlen($phone) > 50) {
            $errors['phone'][] = 'Phone must be 50 characters or fewer.';
        }

        $email = trim(Row::str($input, 'email'));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Email must be a valid address.';
            } elseif (mb_strlen($email) > 190) {
                $errors['email'][] = 'Email must be 190 characters or fewer.';
            }
        }

        $status = Row::str($input, 'status', 'prospect');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $errors['status'][] = 'Status must be one of: ' . implode(', ', self::VALID_STATUSES) . '.';
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
            'name'                => trim(Row::str($input, 'name')),
            'legal_name'          => trim(Row::str($input, 'legal_name')) ?: null,
            'industry'            => trim(Row::str($input, 'industry')) ?: null,
            'website'             => trim(Row::str($input, 'website')) ?: null,
            'country_code'        => strtoupper(trim(Row::str($input, 'country_code'))) ?: null,
            'countries_operation' => $this->normalizeCountries($input['countries_operation'] ?? []),
            'address'             => trim(Row::str($input, 'address')) ?: null,
            'phone'               => trim(Row::str($input, 'phone')) ?: null,
            'email'               => trim(Row::str($input, 'email')) ?: null,
            'status'              => Row::str($input, 'status', 'prospect'),
            'notes'               => trim(Row::str($input, 'notes')) ?: null,
        ];
    }

    /** @return string[] */
    private function normalizeCountries(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) return [];

        $out = [];
        foreach ($value as $code) {
            $code = strtoupper(trim(is_scalar($code) ? (string) $code : ''));
            if ($code !== '' && preg_match('/^[A-Z]{2}$/', $code)) {
                $out[] = $code;
            }
        }
        return array_values(array_unique($out));
    }
}
