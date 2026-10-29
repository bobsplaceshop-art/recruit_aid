<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Offer;
use CorporateAid\Recruit\Models\OfferHistory;

final class OfferRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Offer
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_offers` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Offer::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Offer
    {
        $sql = "SELECT * FROM `recruit_offers` WHERE `code` = ?";
        if (!$includeDeleted) $sql .= " AND `deleted_at` IS NULL";
        $sql .= " LIMIT 1";
        $row = $this->db->selectOne($sql, PDOParams::positional([$code]));
        return $row ? Offer::fromRow($row) : null;
    }

    public function findByApplication(int $applicationId): ?Offer
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_offers`
             WHERE `application_id` = ? AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC LIMIT 1",
            PDOParams::positional([$applicationId])
        );
        return $row ? Offer::fromRow($row) : null;
    }

    /** @return Offer[] */
    public function forApplication(int $applicationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_offers`
             WHERE `application_id` = ? AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC",
            PDOParams::positional([$applicationId])
        );
        return array_map([Offer::class, 'fromRow'], $rows);
    }

    /** @return Offer[] */
    public function pendingExpiry(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_offers`
             WHERE `status` = 'sent'
               AND `expires_at` < CURDATE()
               AND `deleted_at` IS NULL"
        );
        return array_map([Offer::class, 'fromRow'], $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `recruit_offers`
                (`code`, `application_id`, `offered_salary`, `currency`,
                 `start_date`, `expires_at`, `status`, `notes`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $data['code'],
                $data['application_id'],
                $data['offered_salary'],
                $data['currency'],
                $data['start_date'],
                $data['expires_at'],
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
            ])
        );
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['offered_salary', 'currency', 'start_date', 'expires_at', 'notes'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;
            $sets[] = "`{$col}` = ?";
            $params[] = $data[$col];
        }
        if (empty($sets)) return;
        $params[] = $id;
        $this->db->execute(
            "UPDATE `recruit_offers` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );
    }

    public function updateStatus(int $id, string $status, ?string $responseNotes = null): void
    {
        $now = date('Y-m-d H:i:s');
        if ($status === 'sent') {
            $this->db->execute(
                "UPDATE `recruit_offers` SET `status` = ?, `sent_at` = ? WHERE `id` = ?",
                PDOParams::positional([$status, $now, $id])
            );
        } elseif (in_array($status, ['accepted', 'declined'], true)) {
            $this->db->execute(
                "UPDATE `recruit_offers` SET `status` = ?, `responded_at` = ?, `response_notes` = ? WHERE `id` = ?",
                PDOParams::positional([$status, $now, $responseNotes, $id])
            );
        } else {
            $this->db->execute(
                "UPDATE `recruit_offers` SET `status` = ? WHERE `id` = ?",
                PDOParams::positional([$status, $id])
            );
        }
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_offers` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function recordHistory(int $offerId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): void
    {
        $this->db->execute(
            "INSERT INTO `recruit_offer_history`
                (`offer_id`, `from_status`, `to_status`, `changed_by`, `notes`)
             VALUES (?, ?, ?, ?, ?)",
            PDOParams::positional([$offerId, $fromStatus, $toStatus, $changedBy, $notes])
        );
    }

    /** @return OfferHistory[] */
    public function historyFor(int $offerId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_offer_history`
             WHERE `offer_id` = ?
             ORDER BY `changed_at` ASC, `id` ASC",
            PDOParams::positional([$offerId])
        );
        return array_map([OfferHistory::class, 'fromRow'], $rows);
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_offer_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_offer_code'"
        );
        return 'OFF-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }
}
