<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class EventBus
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function __construct(private Database $db) {}

    public function subscribe(string $eventType, callable $handler): void
    {
        $this->listeners[$eventType][] = $handler;
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $eventType, array $payload): void
    {
        // Write to outbox for async processing
        $this->db->insert(
            "INSERT INTO `core_module_events` (`event_type`, `payload`, `status`) VALUES (?, ?, 'pending')",
            PDOParams::positional([$eventType, json_encode($payload)])
        );

        // Execute synchronous listeners immediately (if any)
        $this->executeListeners($eventType, $payload);
    }

    public function processPending(int $limit = 50): int
    {
        // Use FOR UPDATE SKIP LOCKED to prevent double-processing across workers
        $rows = $this->db->select(
            "SELECT * FROM `core_module_events` 
             WHERE `status` = 'pending' AND `scheduled_at` <= NOW() 
             ORDER BY `id` ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED"
        );

        $processed = 0;
        foreach ($rows as $row) {
            $id = Row::int($row, 'id');
            $type = Row::str($row, 'event_type');
            
            $rawPayload = Row::nullableStr($row, 'payload') ?? '{}';
            $payload = json_decode($rawPayload, true);
            if (!is_array($payload)) $payload = [];

            // Mark as processing
            $this->db->execute(
                "UPDATE `core_module_events` SET `status` = 'processing' WHERE `id` = ?",
                PDOParams::positional([$id])
            );

            try {
                $this->executeListeners($type, $payload);
                $this->db->execute(
                    "UPDATE `core_module_events` SET `status` = 'completed', `processed_at` = NOW() WHERE `id` = ?",
                    PDOParams::positional([$id])
                );
                $processed++;
            } catch (\Throwable $e) {
                $this->db->execute(
                    "UPDATE `core_module_events` SET `status` = 'failed', `last_error` = ?, `attempts` = `attempts` + 1 WHERE `id` = ?",
                    PDOParams::positional([$e->getMessage(), $id])
                );
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $payload */
    private function executeListeners(string $eventType, array $payload): void
    {
        if (!isset($this->listeners[$eventType])) {
            return; // No subscribers yet
        }

        foreach ($this->listeners[$eventType] as $listener) {
            $listener($payload);
        }
    }
}
