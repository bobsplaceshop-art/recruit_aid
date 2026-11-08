<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use RuntimeException;

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
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        // Persist to the outbox. Listeners run only when a worker processes it.
        $this->db->insert(
            "INSERT INTO `core_module_events` (`event_type`, `payload`, `status`)
             VALUES (?, ?, 'pending')",
            PDOParams::positional([$eventType, $encoded])
        );
    }

    public function processPending(int $limit = 50): int
    {
        $limit = max(1, min($limit, 500));

        // Claim events atomically. The row locks remain held until every
        // selected event has been changed to processing and the transaction commits.
        $this->db->beginTransaction();

        try {
            $rows = $this->db->select(
                "SELECT `id`, `event_type`, `payload`
                 FROM `core_module_events`
                 WHERE `status` = 'pending' AND `scheduled_at` <= NOW()
                 ORDER BY `id` ASC
                 LIMIT {$limit}
                 FOR UPDATE SKIP LOCKED"
            );

            foreach ($rows as $row) {
                $this->db->execute(
                    "UPDATE `core_module_events`
                     SET `status` = 'processing'
                     WHERE `id` = ? AND `status` = 'pending'",
                    PDOParams::positional([Row::int($row, 'id')])
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $processed = 0;

        foreach ($rows as $row) {
            $id = Row::int($row, 'id');
            $type = Row::str($row, 'event_type');
            $rawPayload = Row::nullableStr($row, 'payload') ?? '{}';

            try {
                $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($payload)) {
                    throw new RuntimeException("Invalid payload for event {$id}.");
                }

                $this->executeListeners($type, $payload);

                $this->db->execute(
                    "UPDATE `core_module_events`
                     SET `status` = 'completed', `processed_at` = NOW(), `last_error` = NULL
                     WHERE `id` = ? AND `status` = 'processing'",
                    PDOParams::positional([$id])
                );

                $processed++;
            } catch (\Throwable $e) {
                $this->db->execute(
                    "UPDATE `core_module_events`
                     SET `status` = 'failed',
                         `last_error` = ?,
                         `attempts` = `attempts` + 1
                     WHERE `id` = ? AND `status` = 'processing'",
                    PDOParams::positional([mb_substr($e->getMessage(), 0, 2000), $id])
                );
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $payload */
    private function executeListeners(string $eventType, array $payload): void
    {
        if (empty($this->listeners[$eventType])) {
            throw new RuntimeException("No listener registered for event type '{$eventType}'.");
        }

        foreach ($this->listeners[$eventType] as $listener) {
            $listener($payload);
        }
    }
}
