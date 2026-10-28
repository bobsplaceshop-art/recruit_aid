<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;

/**
 * Writes entries into the `core_activities` table.
 * This is the business-visible timeline of "what happened".
 * Distinct from audit_logs which tracks security-relevant events only.
 */
final class ActivityLogger
{
    public function __construct(private Database $db, private ?Auth $auth = null)
    {
    }

    /**
     * @param string               $action        Short verb, e.g. "organisation.created"
     * @param string               $module        Short module name, e.g. "organisations"
     * @param string               $description   Human-readable summary for the timeline
     * @param string|null          $subjectType   e.g. "organisation"
     * @param int|null             $subjectId     Numeric ID of the affected record
     * @param array<string, mixed> $meta          Optional structured context
     */
    public function log(
        string $action,
        string $module,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = []
    ): void {
        $userId = null;
        if ($this->auth !== null) {
            $user = $this->auth->user();
            $userId = $user?->id;
        }

        $ip = null;
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $remoteAddr = $_SERVER['REMOTE_ADDR'];
            if (is_string($remoteAddr)) {
                $packed = @inet_pton($remoteAddr);
                if ($packed !== false) {
                    $ip = $packed;
                }
            }
        }

        $this->db->execute(
            "INSERT INTO `core_activities`
                (`user_id`, `action`, `module`, `subject_type`, `subject_id`,
                 `description`, `meta`, `ip_address`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            PDOParams::positional([
                $userId,
                $action,
                $module,
                $subjectType,
                $subjectId,
                $description,
                $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                $ip,
            ])
        );
    }
}
