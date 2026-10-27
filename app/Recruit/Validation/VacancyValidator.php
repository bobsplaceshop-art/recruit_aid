<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

final class VacancyValidator
{
    private const VALID_WORK_MODES = ['onsite', 'hybrid', 'remote'];
    private const VALID_EMPLOYMENT_TYPES = ['permanent', 'contract', 'fixed_term', 'internship', 'consultancy'];
    private const VALID_SENIORITIES = ['junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'];
    private const VALID_STATUSES = [
        'draft', 'pending_approval', 'open', 'sourcing', 'screening',
        'shortlisting', 'client_review', 'interviewing', 'reference_check',
        'offer', 'filled', 'on_hold', 'cancelled', 'closed',
    ];

    public function validate(array $input): array
    {
        $errors = [];

        // title (required)
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $errors['title'][] = 'Job title is required.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'][] = 'Title must be 200 characters or fewer.';
        }

        // department (optional)
        $dept = trim((string) ($input['department'] ?? ''));
        if ($dept !== '' && mb_strlen($dept) > 150) {
            $errors['department'][] = 'Department must be 150 characters or fewer.';
        }

        // country_code
        $cc = trim((string) ($input['country_code'] ?? ''));
        if ($cc !== '' && !preg_match('/^[A-Z]{2}$/i', $cc)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code.';
        }

        // city (optional)
        $city = trim((string) ($input['city'] ?? ''));
        if ($city !== '' && mb_strlen($city) > 100) {
            $errors['city'][] = 'City must be 100 characters or fewer.';
        }

        // enums
        $wm = (string) ($input['work_mode'] ?? 'onsite');
        if (!in_array($wm, self::VALID_WORK_MODES, true)) {
            $errors['work_mode'][] = 'Invalid work mode.';
        }
        $et = (string) ($input['employment_type'] ?? 'permanent');
        if (!in_array($et, self::VALID_EMPLOYMENT_TYPES, true)) {
            $errors['employment_type'][] = 'Invalid employment type.';
        }
        $sen = (string) ($input['seniority'] ?? 'mid');
        if (!in_array($sen, self::VALID_SENIORITIES, true)) {
            $errors['seniority'][] = 'Invalid seniority.';
        }
        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $errors['status'][] = 'Invalid status.';
        }

        // positions_count
        $positions = $input['positions_count'] ?? 1;
        if (!is_numeric($positions) || (int) $positions < 1 || (int) $positions > 1000) {
            $errors['positions_count'][] = 'Positions must be a number between 1 and 1000.';
        }

        // dates
        $opening = trim((string) ($input['opening_date'] ?? ''));
        $target  = trim((string) ($input['target_hire_date'] ?? ''));
        if ($opening !== '' && !$this->isValidDate($opening)) {
            $errors['opening_date'][] = 'Opening date must be a valid date (YYYY-MM-DD).';
        }
        if ($target !== '' && !$this->isValidDate($target)) {
            $errors['target_hire_date'][] = 'Target hire date must be a valid date (YYYY-MM-DD).';
        }
        if ($opening !== '' && $target !== '' && $opening > $target) {
            $errors['target_hire_date'][] = 'Target hire date must be on or after the opening date.';
        }

        // salary
        $smin = $input['salary_min'] ?? null;
        $smax = $input['salary_max'] ?? null;
        if ($smin !== null && $smin !== '' && !is_numeric($smin)) {
            $errors['salary_min'][] = 'Salary minimum must be a number.';
        }
        if ($smax !== null && $smax !== '' && !is_numeric($smax)) {
            $errors['salary_max'][] = 'Salary maximum must be a number.';
        }
        if ($smin !== null && $smin !== '' && $smax !== null && $smax !== '' && is_numeric($smin) && is_numeric($smax)) {
            if ((float) $smax < (float) $smin) {
                $errors['salary_max'][] = 'Maximum must be greater than or equal to minimum.';
            }
        }

        // currency
        $cur = trim((string) ($input['currency'] ?? ''));
        if ($cur !== '' && !preg_match('/^[A-Z]{3}$/i', $cur)) {
            $errors['currency'][] = 'Currency must be a 3-letter ISO code (e.g. KES, USD).';
        }

        // public slug uniqueness is checked at the service layer if needed

        return $errors;
    }

    public function normalize(array $input): array
    {
        return [
            'engagement_id'             => !empty($input['engagement_id']) ? (int) $input['engagement_id'] : null,
            'title'                     => trim((string) ($input['title'] ?? '')),
            'department'                => trim((string) ($input['department'] ?? '')) ?: null,
            'country_code'              => strtoupper(trim((string) ($input['country_code'] ?? ''))) ?: null,
            'city'                      => trim((string) ($input['city'] ?? '')) ?: null,
            'work_mode'                 => (string) ($input['work_mode'] ?? 'onsite'),
            'employment_type'           => (string) ($input['employment_type'] ?? 'permanent'),
            'seniority'                 => (string) ($input['seniority'] ?? 'mid'),
            'positions_count'           => (int) ($input['positions_count'] ?? 1),
            'opening_date'              => trim((string) ($input['opening_date'] ?? '')) ?: null,
            'target_hire_date'          => trim((string) ($input['target_hire_date'] ?? '')) ?: null,
            'recruiter_id'              => !empty($input['recruiter_id']) ? (int) $input['recruiter_id'] : null,
            'hiring_manager_contact_id' => !empty($input['hiring_manager_contact_id']) ? (int) $input['hiring_manager_contact_id'] : null,
            'salary_min'                => (isset($input['salary_min']) && $input['salary_min'] !== '') ? (float) $input['salary_min'] : null,
            'salary_max'                => (isset($input['salary_max']) && $input['salary_max'] !== '') ? (float) $input['salary_max'] : null,
            'currency'                  => strtoupper(trim((string) ($input['currency'] ?? ''))) ?: null,
            'description'               => trim((string) ($input['description'] ?? '')) ?: null,
            'responsibilities'          => trim((string) ($input['responsibilities'] ?? '')) ?: null,
            'requirements'              => trim((string) ($input['requirements'] ?? '')) ?: null,
            'preferred_qualifications'  => trim((string) ($input['preferred_qualifications'] ?? '')) ?: null,
            'skills'                    => $this->normalizeList($input['skills'] ?? []),
            'languages'                 => $this->normalizeList($input['languages'] ?? []),
            'assessment_criteria'       => trim((string) ($input['assessment_criteria'] ?? '')) ?: null,
            'internal_notes'            => trim((string) ($input['internal_notes'] ?? '')) ?: null,
            'client_notes'              => trim((string) ($input['client_notes'] ?? '')) ?: null,
            'status'                    => (string) ($input['status'] ?? 'draft'),
            'is_public'                 => !empty($input['is_public']),
        ];
    }

    /**
     * Extract requirement lists from input.
     * Returns ['responsibility' => [...], 'requirement' => [...], 'preferred' => [...], 'skill' => [...], 'language' => [...]]
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

    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $v) {
            $v = trim((string) $v);
            if ($v !== '') $out[] = $v;
        }
        return array_values(array_unique($out));
    }
}
