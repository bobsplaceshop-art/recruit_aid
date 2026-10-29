<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

use CorporateAid\Core\Row;
use CorporateAid\Platform\Services\LookupService;

final class VacancyValidator
{
    public function __construct(private LookupService $lookups) {}

    private const VALID_STATUSES = [
        'draft', 'pending_approval', 'open', 'sourcing', 'screening',
        'shortlisting', 'client_review', 'interviewing', 'reference_check',
        'offer', 'filled', 'on_hold', 'cancelled', 'closed',
    ];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $title = trim(Row::str($input, 'title'));
        if ($title === '') {
            $errors['title'][] = 'Job title is required.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'][] = 'Title must be 200 characters or fewer.';
        }

        $dept = trim(Row::str($input, 'department'));
        if ($dept !== '' && mb_strlen($dept) > 150) {
            $errors['department'][] = 'Department must be 150 characters or fewer.';
        }

        $cc = trim(Row::str($input, 'country_code'));
        if ($cc !== '' && !preg_match('/^[A-Z]{2}$/i', $cc)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code.';
        }

        $city = trim(Row::str($input, 'city'));
        if ($city !== '' && mb_strlen($city) > 100) {
            $errors['city'][] = 'City must be 100 characters or fewer.';
        }

        $wm = Row::str($input, 'work_mode', 'onsite');
        if (!$this->lookups->isValid('work_mode', $wm)) {
            $errors['work_mode'][] = 'Invalid work mode.';
        }
        $et = Row::str($input, 'employment_type', 'permanent');
        if (!$this->lookups->isValid('employment_type', $et)) {
            $errors['employment_type'][] = 'Invalid employment type.';
        }
        $sen = Row::str($input, 'seniority', 'mid');
        if (!$this->lookups->isValid('seniority', $sen)) {
            $errors['seniority'][] = 'Invalid seniority.';
        }
        $status = Row::str($input, 'status', 'draft');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $errors['status'][] = 'Invalid status.';
        }

        $positions = Row::int($input, 'positions_count', 1);
        if ($positions < 1 || $positions > 1000) {
            $errors['positions_count'][] = 'Positions must be a number between 1 and 1000.';
        }

        $opening = trim(Row::str($input, 'opening_date'));
        $target  = trim(Row::str($input, 'target_hire_date'));
        if ($opening !== '' && !$this->isValidDate($opening)) {
            $errors['opening_date'][] = 'Opening date must be a valid date (YYYY-MM-DD).';
        }
        if ($target !== '' && !$this->isValidDate($target)) {
            $errors['target_hire_date'][] = 'Target hire date must be a valid date (YYYY-MM-DD).';
        }
        if ($opening !== '' && $target !== '' && $opening > $target) {
            $errors['target_hire_date'][] = 'Target hire date must be on or after the opening date.';
        }

        $smin = Row::nullableFloat($input, 'salary_min');
        $smax = Row::nullableFloat($input, 'salary_max');
        if ($smin !== null && $smax !== null && $smax < $smin) {
            $errors['salary_max'][] = 'Maximum must be greater than or equal to minimum.';
        }

        $cur = trim(Row::str($input, 'currency'));
        if ($cur !== '' && !preg_match('/^[A-Z]{3}$/i', $cur)) {
            $errors['currency'][] = 'Currency must be a 3-letter ISO code (e.g. KES, USD).';
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
            'engagement_id'             => Row::nullableInt($input, 'engagement_id'),
            'title'                     => trim(Row::str($input, 'title')),
            'department'                => trim(Row::str($input, 'department')) ?: null,
            'country_code'              => strtoupper(trim(Row::str($input, 'country_code'))) ?: null,
            'city'                      => trim(Row::str($input, 'city')) ?: null,
            'work_mode'                 => Row::str($input, 'work_mode', 'onsite'),
            'employment_type'           => Row::str($input, 'employment_type', 'permanent'),
            'seniority'                 => Row::str($input, 'seniority', 'mid'),
            'positions_count'           => Row::int($input, 'positions_count', 1),
            'opening_date'              => trim(Row::str($input, 'opening_date')) ?: null,
            'target_hire_date'          => trim(Row::str($input, 'target_hire_date')) ?: null,
            'recruiter_id'              => Row::nullableInt($input, 'recruiter_id'),
            'hiring_manager_contact_id' => Row::nullableInt($input, 'hiring_manager_contact_id'),
            'salary_min'                => Row::nullableFloat($input, 'salary_min'),
            'salary_max'                => Row::nullableFloat($input, 'salary_max'),
            'currency'                  => strtoupper(trim(Row::str($input, 'currency'))) ?: null,
            'description'               => trim(Row::str($input, 'description')) ?: null,
            'responsibilities'          => trim(Row::str($input, 'responsibilities')) ?: null,
            'requirements'              => trim(Row::str($input, 'requirements')) ?: null,
            'preferred_qualifications'  => trim(Row::str($input, 'preferred_qualifications')) ?: null,
            'skills'                    => $this->normalizeList($input['skills'] ?? []),
            'languages'                 => $this->normalizeList($input['languages'] ?? []),
            'assessment_criteria'       => trim(Row::str($input, 'assessment_criteria')) ?: null,
            'internal_notes'            => trim(Row::str($input, 'internal_notes')) ?: null,
            'client_notes'              => trim(Row::str($input, 'client_notes')) ?: null,
            'status'                    => Row::str($input, 'status', 'draft'),
            'is_public'                 => Row::bool($input, 'is_public'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function extractRequirements(array $input): array
    {
        return [
            'responsibility' => $this->normalizeList($input['responsibilities_list'] ?? []),
            'requirement'    => $this->normalizeList($input['requirements_list'] ?? []),
            'preferred'      => $this->normalizeList($input['preferred_list'] ?? []),
            'skill'          => $this->normalizeList($input['skills'] ?? []),
            'language'       => $this->normalizeList($input['languages'] ?? []),
        ];
    }

    private function isValidDate(string $d): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }

    /** @return string[] */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $v) {
            $str = trim(is_scalar($v) ? (string) $v : '');
            if ($str !== '') $out[] = $str;
        }
        return array_values(array_unique($out));
    }
}
