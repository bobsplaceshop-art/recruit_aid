<?php
declare(strict_types=1);

/**
 * Repair pipeline consistency for data created before auto-advance
 * was deployed. Moves applications forward when their business events
 * (completed interviews, sent offers, accepted offers) imply a later
 * stage, using the open-mode rule (skip to the event-appropriate stage).
 *
 * Safe to run multiple times.
 *
 * Usage: php bin/repair-pipeline-consistency.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = CorporateAid\Core\App::boot(dirname(__DIR__));
$c = CorporateAid\Core\App::instance()->container();

$db = $c->get(CorporateAid\Core\Database::class);
$appService = $c->get(CorporateAid\Recruit\Services\ApplicationService::class);

echo "Repairing pipeline consistency...\n";

// 1. For each completed interview, ensure the application is at
//    'interview' or beyond (open mode assumption for legacy data).
$completedInterviews = $db->select(
    "SELECT i.`application_id`, a.`vacancy_id`, a.`current_stage_id`, a.`status`
     FROM `recruit_interviews` i
     JOIN `recruit_applications` a ON a.`id` = i.`application_id`
     WHERE i.`status` = 'completed' AND a.`status` = 'active' AND a.`deleted_at` IS NULL"
);

$movedInterview = 0;
foreach ($completedInterviews as $row) {
    $appId = \CorporateAid\Core\Row::int($row, 'application_id');
    $vacancyId = \CorporateAid\Core\Row::int($row, 'vacancy_id');

    $stages = $db->select(
        "SELECT `id`, `key_slug`, `sort_order`, `is_terminal` FROM `recruit_vacancy_stages`
         WHERE `vacancy_id` = ? ORDER BY `sort_order` ASC",
        \CorporateAid\Core\PDOParams::positional([$vacancyId])
    );

    $targetStageId = null;
    foreach ($stages as $s) {
        $key = \CorporateAid\Core\Row::str($s, 'key_slug');
        if (in_array($key, ['interview', 'interviewing'], true)) {
            $targetStageId = \CorporateAid\Core\Row::int($s, 'id');
            break;
        }
    }
    if ($targetStageId === null) continue;

    if (\CorporateAid\Core\Row::int($row, 'current_stage_id') === $targetStageId) continue;

    $result = $appService->moveToStage($appId, $targetStageId, 'Pipeline repair (completed interview)');
    if ($result['success']) $movedInterview++;
}
echo "  Applications moved to Interview stage: {$movedInterview}\n";

// 2. For each sent/accepted offer, ensure the application is at
//    'offer' or 'hired' respectively (open mode assumption).
$offers = $db->select(
    "SELECT o.`application_id`, o.`status`, a.`vacancy_id`, a.`current_stage_id`, a.`status` AS app_status
     FROM `recruit_offers` o
     JOIN `recruit_applications` a ON a.`id` = o.`application_id`
     WHERE o.`deleted_at` IS NULL AND a.`status` = 'active' AND a.`deleted_at` IS NULL"
);

$movedOffer = 0;
foreach ($offers as $row) {
    $appId = \CorporateAid\Core\Row::int($row, 'application_id');
    $vacancyId = \CorporateAid\Core\Row::int($row, 'vacancy_id');
    $offerStatus = \CorporateAid\Core\Row::str($row, 'status');

    $keyTargets = match ($offerStatus) {
        'sent'     => ['offer'],
        'accepted' => ['hired'],
        default    => [],
    };
    if (empty($keyTargets)) continue;

    $stages = $db->select(
        "SELECT `id`, `key_slug` FROM `recruit_vacancy_stages` WHERE `vacancy_id` = ?",
        \CorporateAid\Core\PDOParams::positional([$vacancyId])
    );

    $targetStageId = null;
    foreach ($stages as $s) {
        if (in_array(\CorporateAid\Core\Row::str($s, 'key_slug'), $keyTargets, true)) {
            $targetStageId = \CorporateAid\Core\Row::int($s, 'id');
            break;
        }
    }
    if ($targetStageId === null) continue;
    if (\CorporateAid\Core\Row::int($row, 'current_stage_id') === $targetStageId) continue;

    $result = $appService->moveToStage($appId, $targetStageId, 'Pipeline repair (' . $offerStatus . ' offer)');
    if ($result['success']) $movedOffer++;
}
echo "  Applications moved to Offer/Hired stage: {$movedOffer}\n";

echo "Done.\n";
