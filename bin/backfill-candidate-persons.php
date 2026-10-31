<?php
declare(strict_types=1);

use CorporateAid\Core\Database;
use CorporateAid\Core\DatabaseConfig;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Shared\Repositories\PersonRepository;

/**
 * Recruit Aid — backfill candidate person links.
 *
 * Links every recruit_candidates row that has no person_id to a
 * core_persons row: find-or-create by exact (first_name, last_name)
 * via PersonRepository::findByName(), then UPDATE the candidate.
 *
 * Idempotent: candidates with person_id already set are skipped.
 * Safe on zero rows (today: recruit_candidates is empty).
 *
 * Runs per-candidate in its own transaction, so a failure mid-run
 * leaves earlier candidates linked and a re-run picks up the rest.
 *
 * Usage (Laragon Terminal or WSL):
 *   php bin/backfill-candidate-persons.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);

$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(1);
}
require $autoload;

// CLI scripts use the elevated user from config/cli.php,
// NOT the limited user that the web app uses.
$cliConfig = require $basePath . '/config/cli.php';
$dbConfig  = $cliConfig['database'];

$database = new Database(DatabaseConfig::fromArray($dbConfig));
$persons  = new PersonRepository($database);

$rows = $database->select(
    "SELECT `id`, `first_name`, `last_name` FROM `recruit_candidates`
     WHERE `person_id` IS NULL
     ORDER BY `id` ASC"
);

$total      = count($rows);
$linked     = 0;
$skipped    = 0;
$failed     = 0;
$skippedIds = [];
$failedIds  = [];

echo "Candidates needing a person link: {$total}\n";

foreach ($rows as $row) {
    $id        = Row::int($row, 'id');
    $firstName = trim(Row::str($row, 'first_name'));
    $lastName  = trim(Row::str($row, 'last_name'));

    if ($firstName === '' || $lastName === '') {
        $skipped++;
        $skippedIds[] = $id;
        fwrite(STDERR, "SKIP candidate #{$id}: empty first/last name\n");
        continue;
    }

    try {
        $database->beginTransaction();

        $person = $persons->findByName($firstName, $lastName);
        if ($person === null) {
            $personId = $persons->create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
        } else {
            $personId = $person->id;
        }

        $database->execute(
            "UPDATE `recruit_candidates` SET `person_id` = ? WHERE `id` = ?",
            PDOParams::positional([$personId, $id])
        );

        $database->commit();
        $linked++;
        echo "LINKED candidate #{$id} -> person #{$personId}\n";
    } catch (\Throwable $e) {
        $database->rollBack();
        $failed++;
        $failedIds[] = $id;
        fwrite(STDERR, "FAIL candidate #{$id}: " . $e->getMessage() . "\n");
    }
}

echo "\nSummary:\n";
echo "  Total needing link : {$total}\n";
echo "  Linked             : {$linked}\n";
echo "  Skipped (no name)  : {$skipped}" . ($skippedIds ? ' (ids: ' . implode(',', $skippedIds) . ')' : '') . "\n";
echo "  Failed             : {$failed}" . ($failedIds ? ' (ids: ' . implode(',', $failedIds) . ')' : '') . "\n";

exit($failed > 0 ? 1 : 0);
