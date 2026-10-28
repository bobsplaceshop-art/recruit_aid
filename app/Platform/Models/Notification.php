<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

final class Notification
{
    public int $id;
    public int $userId;
    public string $type;
    public string $title;
    public ?string $body;
    public ?string $link;
    public ?string $readAt;
    public ?string $createdAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $n = new self();
        $n->id = Row::int($row, 'id');
        $n->userId = Row::int($row, 'user_id');
        $n->type = Row::str($row, 'type');
        $n->title = Row::str($row, 'title');
        $n->body = Row::nullableStr($row, 'body');
        $n->link = Row::nullableStr($row, 'link');
        $n->readAt = Row::nullableStr($row, 'read_at');
        $n->createdAt = Row::nullableStr($row, 'created_at');
        return $n;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function icon(): string
    {
        return match ($this->type) {
            'candidate.new'       => 'bi-person-plus',
            'client.submission'   => 'bi-send',
            'client.feedback'     => 'bi-chat-left-text',
            'interview.scheduled' => 'bi-calendar-event',
            'offer.issued'        => 'bi-file-earmark-check',
            'task.assigned'       => 'bi-check2-square',
            'settings.changed'    => 'bi-gear',
            'rate_limit.hit'      => 'bi-shield-exclamation',
            'approval.request'    => 'bi-check2-circle',
            'security.alert'      => 'bi-shield-lock',
            default               => 'bi-bell',
        };
    }

    public function relativeTime(): string
    {
        if ($this->createdAt === null) return '';
        $ts = strtotime($this->createdAt);
        if ($ts === false) return '';
        $diff = time() - $ts;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', $ts);
    }
}
