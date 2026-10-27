<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\Notification;

final class NotificationRepository
{
    public function __construct(private Database $db) {}

    /** @return Notification[] */
    public function forUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->select(
            "SELECT * FROM `core_notifications`
             WHERE `user_id` = ?
             ORDER BY `created_at` DESC
             LIMIT {$limit}",
            [$userId]
        );
        return array_map([Notification::class, 'fromRow'], $rows);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `core_notifications` WHERE `user_id` = ? AND `read_at` IS NULL",
            [$userId]
        );
    }

    public function findById(int $id): ?Notification
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_notifications` WHERE `id` = ? LIMIT 1",
            [$id]
        );
        return $row ? Notification::fromRow($row) : null;
    }

    public function create(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        return $this->db->insert(
            "INSERT INTO `core_notifications` (`user_id`, `type`, `title`, `body`, `link`)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $type, $title, $body, $link]
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_notifications` SET `read_at` = NOW()
             WHERE `id` = ? AND `user_id` = ? AND `read_at` IS NULL",
            [$id, $userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_notifications` SET `read_at` = NOW()
             WHERE `user_id` = ? AND `read_at` IS NULL",
            [$userId]
        );
    }

    public function purgeOldRead(int $days = 30): int
    {
        return $this->db->execute(
            "DELETE FROM `core_notifications`
             WHERE `read_at` IS NOT NULL
               AND `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
}
