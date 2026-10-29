<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Candidate;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Recruit\Validation\CandidateValidator;

final class CandidateService
{
    public function __construct(
        private CandidateRepository $candidates,
        private CandidateValidator $validator,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array{id:int, candidate:Candidate|null, errors:string[]}
     */
    public function create(array $input): array
    {
        $errors = [];
        foreach ($this->validator->validate($input) as $msgs) {
            foreach ($msgs as $m) $errors[] = $m;
        }
        if (!empty($errors)) {
            return ['id' => 0, 'candidate' => null, 'errors' => $errors];
        }

        // Dedup: email
        $email = strtolower(trim(Row::str($input, 'email')));
        if ($email !== '') {
            $existing = $this->candidates->findByEmail($email);
            if ($existing !== null) {
                return [
                    'id' => 0,
                    'candidate' => null,
                    'errors' => ['A candidate with this email already exists: ' . $existing->fullName() . ' (' . $existing->code . ').'],
                ];
            }
        }

        // Dedup: LinkedIn URL (only if provided)
        $linkedin = trim(Row::str($input, 'linkedin_url'));
        if ($linkedin !== '') {
            $existing = $this->candidates->findByLinkedin($linkedin);
            if ($existing !== null) {
                return [
                    'id' => 0,
                    'candidate' => null,
                    'errors' => ['A candidate with this LinkedIn URL already exists: ' . $existing->fullName() . ' (' . $existing->code . ').'],
                ];
            }
        }

        $normalized = $this->validator->normalize($input);
        $normalized['code'] = $this->candidates->nextCode();
        $normalized['created_by'] = $this->auth?->user()?->id;

        // Denormalized skills/languages cache for live search
        $normalized['skills'] = $this->extractStringList($input['skills_flat'] ?? null);
        $normalized['languages'] = $this->extractStringList($input['languages'] ?? null);

        $id = $this->candidates->create($normalized);

        // Sync child tables (skills, experience, education, tags)
        $this->syncChildTables($id, $input);

        $candidate = $this->candidates->findById($id);
        if ($candidate === null) {
            return ['id' => $id, 'candidate' => null, 'errors' => ['Candidate was created but could not be retrieved.']];
        }

        $this->activity->log(
            'candidate.created',
            'candidates',
            'Created candidate ' . $candidate->fullName() . ' (' . $candidate->code . ')',
            'candidate',
            $id
        );

        return ['id' => $id, 'candidate' => $candidate, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool, errors:string[], changes:array<string, array{old:mixed,new:mixed}>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->candidates->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Candidate not found.'], 'changes' => []];
        }

        $errors = [];
        foreach ($this->validator->validate($input) as $msgs) {
            foreach ($msgs as $m) $errors[] = $m;
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'changes' => []];
        }

        // Dedup: email (excluding self)
        $email = strtolower(trim(Row::str($input, 'email')));
        if ($email !== '') {
            $conflict = $this->candidates->findByEmail($email, $id);
            if ($conflict !== null) {
                return [
                    'success' => false,
                    'errors' => ['A candidate with this email already exists: ' . $conflict->fullName() . ' (' . $conflict->code . ').'],
                    'changes' => [],
                ];
            }
        }

        // Dedup: LinkedIn (excluding self)
        $linkedin = trim(Row::str($input, 'linkedin_url'));
        if ($linkedin !== '') {
            $conflict = $this->candidates->findByLinkedin($linkedin, $id);
            if ($conflict !== null) {
                return [
                    'success' => false,
                    'errors' => ['A candidate with this LinkedIn URL already exists: ' . $conflict->fullName() . ' (' . $conflict->code . ').'],
                    'changes' => [],
                ];
            }
        }

        $normalized = $this->validator->normalize($input);
        $normalized['skills'] = $this->extractStringList($input['skills_flat'] ?? null);
        $normalized['languages'] = $this->extractStringList($input['languages'] ?? null);

        $changes = $this->candidates->update($id, $normalized);
        $this->syncChildTables($id, $input);

        if (!empty($changes)) {
            $summary = $this->summarizeChanges($changes);
            $this->activity->log(
                'candidate.updated',
                'candidates',
                'Updated candidate ' . $existing->fullName() . ' (' . $existing->code . '): ' . $summary,
                'candidate',
                $id,
                ['changes' => $changes]
            );
        }

        return ['success' => true, 'errors' => [], 'changes' => $changes];
    }

    public function archive(int $id): bool
    {
        $candidate = $this->candidates->findById($id);
        if (!$candidate) return false;
        $this->candidates->softDelete($id);
        $this->activity->log(
            'candidate.archived',
            'candidates',
            'Archived candidate ' . $candidate->fullName() . ' (' . $candidate->code . ')',
            'candidate',
            $id
        );
        return true;
    }

    public function restore(int $id): bool
    {
        $candidate = $this->candidates->findById($id);
        if (!$candidate) return false;
        
        $this->candidates->restore($id);
        
        $this->activity->log(
            'candidate.restored',
            'candidates',
            'Restored candidate ' . $candidate->fullName() . ' (' . $candidate->code . ')',
            'candidate',
            $id
        );
        return true;
    }

    // ---------- helpers ----------

    /**
     * @param array<string, mixed> $input
     */
    private function syncChildTables(int $candidateId, array $input): void
    {
        // Skills — accept a flat list of skill names (textarea, one per line)
        $skillNames = $this->extractStringList($input['skills_flat'] ?? null);
        $skills = [];
        foreach ($skillNames as $name) {
            $skills[] = ['skill_name' => $name, 'proficiency' => null, 'years_used' => null];
        }
        $this->candidates->syncSkills($candidateId, $skills);

        // Experience — array of rows from the form
        /** @var array<int, array<string, mixed>> $experience */
        $experience = is_array($input['experience'] ?? null) ? $input['experience'] : [];
        $this->candidates->syncExperience($candidateId, $experience);

        // Education — array of rows from the form
        /** @var array<int, array<string, mixed>> $education */
        $education = is_array($input['education'] ?? null) ? $input['education'] : [];
        $this->candidates->syncEducation($candidateId, $education);

        // Tags — comma-separated string
        $tagsRaw = $input['tags'] ?? '';
        $tags = is_string($tagsRaw) ? array_map('trim', explode(',', $tagsRaw)) : [];
        $this->candidates->syncTags($candidateId, array_filter($tags, static fn($t) => $t !== ''));
    }

    /** @return string[] */
    private function extractStringList(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            return [];
        }
        $out = [];
        foreach ($parts as $p) {
            $s = is_scalar($p) ? trim((string) $p) : '';
            if ($s !== '') $out[] = $s;
        }
        return array_values(array_unique($out));
    }

    /** @param array<string, array{old:mixed,new:mixed}> $changes */
    private function summarizeChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $field => $change) {
            $parts[] = $field . ': "' . $this->formatValue($change['old']) . '" → "' . $this->formatValue($change['new']) . '"';
        }
        return implode(', ', $parts);
    }

    private function formatValue(mixed $v): string
    {
        if (is_array($v)) {
            $strs = [];
            foreach ($v as $part) {
                if (is_scalar($part)) $strs[] = (string) $part;
            }
            return implode(', ', $strs);
        }
        if ($v === null) return '(empty)';
        return is_scalar($v) ? (string) $v : '';
    }
}
