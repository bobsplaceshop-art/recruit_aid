<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Validation;

use CorporateAid\Core\Row;

/**
 * Validates submitted Candidate data (base identity fields).
 */
final class CandidateValidator
{
    private const VALID_STATUSES = ['active', 'passive', 'hired', 'blacklisted', 'archived'];
    private const VALID_SENIORITY = ['junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $firstName = trim(Row::str($input, 'first_name'));
        if ($firstName === '') {
            $errors['first_name'][] = 'First name is required.';
        } elseif (mb_strlen($firstName) > 100) {
            $errors['first_name'][] = 'First name must be 100 characters or fewer.';
        }

        $lastName = trim(Row::str($input, 'last_name'));
        if ($lastName === '') {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (mb_strlen($lastName) > 100) {
            $errors['last_name'][] = 'Last name must be 100 characters or fewer.';
        }

        $email = trim(Row::str($input, 'email'));
        if ($email === '') {
            $errors['email'][] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email must be a valid address.';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'][] = 'Email must be 255 characters or fewer.';
        }

        $phone = trim(Row::str($input, 'phone'));
        if ($phone !== '' && mb_strlen($phone) > 50) {
            $errors['phone'][] = 'Phone must be 50 characters or fewer.';
        }

        $countryCode = trim(Row::str($input, 'country_code'));
        if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/i', $countryCode)) {
            $errors['country_code'][] = 'Country must be a 2-letter ISO code (e.g. KE, UG, TZ).';
        }

        $linkedinUrl = trim(Row::str($input, 'linkedin_url'));
        if ($linkedinUrl !== '') {
            if (strlen($linkedinUrl) > 255) {
                $errors['linkedin_url'][] = 'LinkedIn URL must be 255 characters or fewer.';
            } elseif (!filter_var($linkedinUrl, FILTER_VALIDATE_URL)) {
                $errors['linkedin_url'][] = 'LinkedIn URL must be a valid URL.';
            }
        }

        $portfolioUrl = trim(Row::str($input, 'portfolio_url'));
        if ($portfolioUrl !== '' && !filter_var($portfolioUrl, FILTER_VALIDATE_URL)) {
            $errors['portfolio_url'][] = 'Portfolio URL must be a valid URL.';
        }

        $yearsExperience = $input['years_experience'] ?? null;
        if ($yearsExperience !== null && $yearsExperience !== '') {
            $y = is_numeric($yearsExperience) ? (int) $yearsExperience : -1;
            if ($y < 0 || $y > 60) {
                $errors['years_experience'][] = 'Years of experience must be between 0 and 60.';
            }
        }

        $seniority = Row::str($input, 'seniority');
        if ($seniority !== '' && !in_array($seniority, self::VALID_SENIORITY, true)) {
            $errors['seniority'][] = 'Seniority must be one of: ' . implode(', ', self::VALID_SENIORITY) . '.';
        }

        $status = Row::str($input, 'status', 'active');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $errors['status'][] = 'Status must be one of: ' . implode(', ', self::VALID_STATUSES) . '.';
        }

        $source = trim(Row::str($input, 'source'));
        if ($source !== '' && mb_strlen($source) > 100) {
            $errors['source'][] = 'Source must be 100 characters or fewer.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $yearsRaw = $input['years_experience'] ?? null;
        $years = ($yearsRaw !== null && $yearsRaw !== '' && is_numeric($yearsRaw)) ? (int) $yearsRaw : null;

        return [
            'first_name'         => trim(Row::str($input, 'first_name')),
            'last_name'          => trim(Row::str($input, 'last_name')),
            'email'              => strtolower(trim(Row::str($input, 'email'))),
            'phone'              => trim(Row::str($input, 'phone')) ?: null,
            'country_code'       => strtoupper(trim(Row::str($input, 'country_code'))) ?: null,
            'city'               => trim(Row::str($input, 'city')) ?: null,
            'linkedin_url'       => trim(Row::str($input, 'linkedin_url')) ?: null,
            'portfolio_url'      => trim(Row::str($input, 'portfolio_url')) ?: null,
            'current_job_title'  => trim(Row::str($input, 'current_job_title')) ?: null,
            'current_company'    => trim(Row::str($input, 'current_company')) ?: null,
            'years_experience'   => $years,
            'seniority'          => Row::str($input, 'seniority') ?: null,
            'summary'            => trim(Row::str($input, 'summary')) ?: null,
            'internal_notes'     => trim(Row::str($input, 'internal_notes')) ?: null,
            'status'             => Row::str($input, 'status', 'active'),
            'source'             => trim(Row::str($input, 'source')) ?: null,
        ];
    }
}
