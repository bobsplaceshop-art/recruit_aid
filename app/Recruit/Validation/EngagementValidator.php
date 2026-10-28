<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

use CorporateAid\Core\Row;

final class EngagementValidator
{
    private const VALID_SERVICE_TYPES = ['3_month', '6_month', '12_month', 'focused_search', 'assessment'];
    private const VALID_STATUSES = ['draft', 'proposal', 'pending_agreement', 'active', 'paused', 'completed', 'cancelled'];
    private const VALID_FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'custom'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $name = trim(Row::str($input, 'name'));
        if ($name === '') {
            $errors['name'][] = 'Engagement name is required.';
        } elseif (mb_strlen($name) > 200) {
            $errors['name'][] = 'Name must be 200 characters or fewer.';
        }

        $st = Row::str($input, 'service_type');
        if (!in_array($st, self::VALID_SERVICE_TYPES, true)) {
            $errors['service_type'][] = 'Please choose a valid service type.';
        }

        $status = Row::str($input, 'status', 'draft');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $errors['status'][] = 'Please choose a valid status.';
        }

        $freq = Row::str($input, 'reporting_frequency', 'monthly');
        if (!in_array($freq, self::VALID_FREQUENCIES, true)) {
            $errors['reporting_frequency'][] = 'Invalid reporting frequency.';
        }

        $cap = Row::int($input, 'agreed_active_role_capacity', 1);
        if ($cap < 1 || $cap > 500) {
            $errors['agreed_active_role_capacity'][] = 'Capacity must be a number between 1 and 500.';
        }

        $start = trim(Row::str($input, 'start_date'));
        $end   = trim(Row::str($input, 'end_date'));

        if ($start !== '' && !$this->isValidDate($start)) {
            $errors['start_date'][] = 'Start date must be a valid date (YYYY-MM-DD).';
        }
        if ($end !== '' && !$this->isValidDate($end)) {
            $errors['end_date'][] = 'End date must be a valid date (YYYY-MM-DD).';
        }
        if ($start !== '' && $end !== '' && $start > $end) {
            $errors['end_date'][] = 'End date must be on or after the start date.';
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
            'package_id'                  => Row::nullableInt($input, 'package_id'),
            'name'                        => trim(Row::str($input, 'name')),
            'service_type'                => Row::str($input, 'service_type', '3_month'),
            'status'                      => Row::str($input, 'status', 'draft'),
            'start_date'                  => trim(Row::str($input, 'start_date')) ?: null,
            'end_date'                    => trim(Row::str($input, 'end_date')) ?: null,
            'agreed_active_role_capacity' => Row::int($input, 'agreed_active_role_capacity', 1),
            'reporting_frequency'         => Row::str($input, 'reporting_frequency', 'monthly'),
            'countries'                   => $this->normalizeCountries($input['countries'] ?? []),
            'scope_description'           => trim(Row::str($input, 'scope_description')) ?: null,
            'stages_included'             => $this->normalizeStages($input['stages_included'] ?? []),
            'notes'                       => trim(Row::str($input, 'notes')) ?: null,
        ];
    }

    private function isValidDate(string $d): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
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

    /** @return string[] */
    private function normalizeStages(mixed $value): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $v) {
            $str = trim(is_scalar($v) ? (string) $v : '');
            if ($str !== '') $out[] = $str;
        }
        return array_values(array_unique($out));
    }
}
