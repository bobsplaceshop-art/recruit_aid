<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Candidate;
use CorporateAid\Recruit\Models\CandidateEducation;
use CorporateAid\Recruit\Models\CandidateExperience;
use CorporateAid\Recruit\Models\CandidateSkill;
use CorporateAid\Recruit\Models\CandidateTag;

final class CandidateRepository implements \CorporateAid\Platform\LiveSearch\LiveSearchable
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Candidate
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_candidates` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Candidate::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Candidate
    {
        $sql = "SELECT * FROM `recruit_candidates` WHERE `code` = ?";
        if (!$includeDeleted) $sql .= " AND `deleted_at` IS NULL";
        $sql .= " LIMIT 1";
        $row = $this->db->selectOne($sql, PDOParams::positional([$code]));
        return $row ? Candidate::fromRow($row) : null;
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Candidate
    {
        $sql = "SELECT * FROM `recruit_candidates` WHERE `email` = ? AND `deleted_at` IS NULL";
        $params = [$email];
        if ($excludeId !== null) { $sql .= " AND `id` != ?"; $params[] = $excludeId; }
        $sql .= " LIMIT 1";
        $row = $this->db->selectOne($sql, PDOParams::positional($params));
        return $row ? Candidate::fromRow($row) : null;
    }

    public function findByLinkedin(string $linkedinUrl, ?int $excludeId = null): ?Candidate
    {
        $sql = "SELECT * FROM `recruit_candidates` WHERE `linkedin_url` = ? AND `deleted_at` IS NULL";
        $params = [$linkedinUrl];
        if ($excludeId !== null) { $sql .= " AND `id` != ?"; $params[] = $excludeId; }
        $sql .= " LIMIT 1";
        $row = $this->db->selectOne($sql, PDOParams::positional($params));
        return $row ? Candidate::fromRow($row) : null;
    }

    /**
     * @param array{search?:string,status?:string,recruiter_id?:int} $filters
     * @return array{rows:Candidate[], total:int, page:int, per_page:int, total_pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ["`deleted_at` IS NULL"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(`first_name` LIKE ? OR `last_name` LIKE ? OR `email` LIKE ? OR `code` LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if (!empty($filters['status'])) { $where[] = "`status` = ?"; $params[] = $filters['status']; }
        if (!empty($filters['recruiter_id'])) { $where[] = "`recruiter_id` = ?"; $params[] = (int) $filters['recruiter_id']; }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_candidates` {$whereSql}",
            $paramObj
        );

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT * FROM `recruit_candidates` {$whereSql}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        return [
            'rows'        => array_map([Candidate::class, 'fromRow'], $rows),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `recruit_candidates`
            (`code`, `first_name`, `last_name`, `email`, `phone`, `country_code`, `city`,
             `linkedin_url`, `portfolio_url`, `current_job_title`, `current_company`,
             `years_experience`, `seniority`, `skills`, `languages`, `summary`, `internal_notes`,
             `status`, `source`, `recruiter_id`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $data['code'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['country_code'] ?? null,
            $data['city'] ?? null,
            $data['linkedin_url'] ?? null,
            $data['portfolio_url'] ?? null,
            $data['current_job_title'] ?? null,
            $data['current_company'] ?? null,
            $data['years_experience'] ?? null,
            $data['seniority'] ?? null,
            !empty($data['skills']) ? json_encode($data['skills']) : null,
            !empty($data['languages']) ? json_encode($data['languages']) : null,
            $data['summary'] ?? null,
            $data['internal_notes'] ?? null,
            $data['status'] ?? 'active',
            $data['source'] ?? null,
            $data['recruiter_id'] ?? null,
            $data['created_by'] ?? null,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) throw new \RuntimeException('Candidate not found.');

        $allowed = [
            'first_name', 'last_name', 'email', 'phone', 'country_code', 'city',
            'linkedin_url', 'portfolio_url', 'current_job_title', 'current_company',
            'years_experience', 'seniority', 'skills', 'languages', 'summary', 'internal_notes',
            'status', 'source', 'recruiter_id',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;

            $newVal = $data[$col];
            $prop = match ($col) {
                'first_name'          => 'firstName',
                'last_name'           => 'lastName',
                'country_code'        => 'countryCode',
                'linkedin_url'        => 'linkedinUrl',
                'portfolio_url'       => 'portfolioUrl',
                'current_job_title'   => 'currentJobTitle',
                'current_company'     => 'currentCompany',
                'years_experience'    => 'yearsExperience',
                'internal_notes'      => 'internalNotes',
                'recruiter_id'        => 'recruiterId',
                default               => $col,
            };
            $oldVal = $existing->$prop ?? null;

            $newForCompare = $newVal;
            if (in_array($col, ['skills', 'languages'], true)) {
                $newForCompare = !empty($newVal) ? (is_array($newVal) ? $newVal : (is_string($newVal) ? json_decode($newVal, true) : [])) : [];
                $oldVal = $col === 'skills' ? $existing->skills : $existing->languages;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if (in_array($col, ['skills', 'languages'], true)) {
                $newVal = !empty($newVal) ? json_encode($newVal) : null;
            }
            $params[] = $newVal;
        }

        if (empty($sets)) return [];

        $params[] = $id;
        $this->db->execute(
            "UPDATE `recruit_candidates` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_candidates` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_candidates` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_candidate_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_candidate_code'"
        );
        return 'CAND-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /** @return CandidateSkill[] */
    public function skillsFor(int $candidateId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_candidate_skills` WHERE `candidate_id` = ? ORDER BY `skill_name` ASC",
            PDOParams::positional([$candidateId])
        );
        return array_map([CandidateSkill::class, 'fromRow'], $rows);
    }

    /** @param array<int, array{skill_name:string,proficiency?:?string,years_used?:?int}> $items */
    public function syncSkills(int $candidateId, array $items): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_candidate_skills` WHERE `candidate_id` = ?",
            PDOParams::positional([$candidateId])
        );
        foreach ($items as $item) {
            $name = trim($item['skill_name']);
            if ($name === '') continue;
            $this->db->execute(
                "INSERT INTO `recruit_candidate_skills` (`candidate_id`, `skill_name`, `proficiency`, `years_used`) VALUES (?, ?, ?, ?)",
                PDOParams::positional([$candidateId, $name, $item['proficiency'] ?? null, $item['years_used'] ?? null])
            );
        }
    }

    /** @return CandidateExperience[] */
    public function experienceFor(int $candidateId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_candidate_experience` WHERE `candidate_id` = ? ORDER BY `sort_order` ASC, `start_date` DESC",
            PDOParams::positional([$candidateId])
        );
        return array_map([CandidateExperience::class, 'fromRow'], $rows);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function syncExperience(int $candidateId, array $items): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_candidate_experience` WHERE `candidate_id` = ?",
            PDOParams::positional([$candidateId])
        );
        $order = 0;
        foreach ($items as $item) {
            $title = trim(Row::str($item, 'job_title'));
            $company = trim(Row::str($item, 'company'));
            if ($title === '' || $company === '') continue;
            $this->db->execute(
                "INSERT INTO `recruit_candidate_experience` (`candidate_id`, `job_title`, `company`, `location`, `start_date`, `end_date`, `is_current`, `description`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $candidateId, $title, $company,
                    $item['location'] ?? null, $item['start_date'] ?? null, $item['end_date'] ?? null,
                    !empty($item['is_current']) ? 1 : 0, $item['description'] ?? null, $order++,
                ])
            );
        }
    }

    /** @return CandidateEducation[] */
    public function educationFor(int $candidateId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_candidate_education` WHERE `candidate_id` = ? ORDER BY `end_year` DESC, `id` DESC",
            PDOParams::positional([$candidateId])
        );
        return array_map([CandidateEducation::class, 'fromRow'], $rows);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function syncEducation(int $candidateId, array $items): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_candidate_education` WHERE `candidate_id` = ?",
            PDOParams::positional([$candidateId])
        );
        foreach ($items as $item) {
            $institution = trim(Row::str($item, 'institution'));
            if ($institution === '') continue;
            $this->db->execute(
                "INSERT INTO `recruit_candidate_education` (`candidate_id`, `institution`, `degree`, `field_of_study`, `start_year`, `end_year`, `grade`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $candidateId, $institution,
                    $item['degree'] ?? null, $item['field_of_study'] ?? null,
                    $item['start_year'] ?? null, $item['end_year'] ?? null,
                    $item['grade'] ?? null, $item['description'] ?? null,
                ])
            );
        }
    }

    /** @return CandidateTag[] */
    public function tagsFor(int $candidateId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_candidate_tags` WHERE `candidate_id` = ? ORDER BY `tag` ASC",
            PDOParams::positional([$candidateId])
        );
        return array_map([CandidateTag::class, 'fromRow'], $rows);
    }

    /** @param string[] $tags */
    public function syncTags(int $candidateId, array $tags): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_candidate_tags` WHERE `candidate_id` = ?",
            PDOParams::positional([$candidateId])
        );
        foreach ($tags as $tag) {
            $t = trim($tag);
            if ($t === '') continue;
            $this->db->execute(
                "INSERT IGNORE INTO `recruit_candidate_tags` (`candidate_id`, `tag`) VALUES (?, ?)",
                PDOParams::positional([$candidateId, $t])
            );
        }
    }

    public function liveSearchConfig(): array
    {
        return [
            'table'            => 'recruit_candidates',
            'alias'            => 'c',
            'columns'          => ['c.first_name', 'c.last_name', 'c.email', 'c.code', 'c.current_job_title'],
            'order'            => 'c.created_at DESC',
            'permission'       => 'recruit.candidates.view',
            'baseFilters'      => ['c.`deleted_at` IS NULL'],
            'joins'            => [],
            'rowView'          => 'candidates/_row',
            'extraColumns'     => [],
            'aggregateColumns' => [],
            'filterable'       => ['status' => 'c.`status` = ?'],
        ];
    }
}
