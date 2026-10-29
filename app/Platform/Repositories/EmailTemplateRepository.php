<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Reads `core_email_templates` rows by template_key + locale.
 */
final class EmailTemplateRepository
{
    public function __construct(private Database $db) {}

    /**
     * @return array{id:int, template_key:string, locale:string, subject:string, body_html:?string, body_text:?string, variables:?string}|null
     */
    public function findByKey(string $templateKey, string $locale = 'en'): ?array
    {
        $row = $this->db->selectOne(
            "SELECT `id`, `template_key`, `locale`, `subject`, `body_html`, `body_text`, `variables`
             FROM `core_email_templates`
             WHERE `template_key` = ? AND `locale` = ? AND `is_active` = 1
             LIMIT 1",
            PDOParams::positional([$templateKey, $locale])
        );

        if ($row === null) return null;

        return [
            'id'            => Row::int($row, 'id'),
            'template_key'  => Row::str($row, 'template_key'),
            'locale'        => Row::str($row, 'locale', 'en'),
            'subject'       => Row::str($row, 'subject'),
            'body_html'     => Row::nullableStr($row, 'body_html'),
            'body_text'     => Row::nullableStr($row, 'body_text'),
            'variables'     => Row::nullableStr($row, 'variables'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->db->select(
            "SELECT * FROM `core_email_templates` ORDER BY `template_key` ASC, `locale` ASC"
        );
    }
}
