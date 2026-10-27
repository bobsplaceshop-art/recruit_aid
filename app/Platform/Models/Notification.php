<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

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

    public static function fromRow(array $row): self
    {
        $n = new self();
        $n->id = (int) $row['id'];
        $n->userId = (int) $row['user_id'];
        $n->type = (string) $row['type'];
        $n->title = (string) $row['title'];
        $n->body = $row['body'] !== null ? (string) $row['body'] : null;
        $n->link = $row['link'] !== null ? (string) $row['link'] : null;
        $n->readAt = $row['read_at'] !== null ? (string) $row['read_at'] : null;
        $n->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
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
