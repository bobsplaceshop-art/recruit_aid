<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\ActivityRepository;

/**
 * Renders a timeline card for a specific entity. Returns empty string
 * when there are no activities to show (so the caller can render the
 * timeline without any conditionals).
 */
final class ActivityTimelineService
{
    public function __construct(
        private ActivityRepository $activities,
        private View $view
    ) {}

    /**
     * @param string $entityType e.g. 'organisation', 'contact', 'engagement', 'vacancy'
     * @param int    $entityId
     * @param int    $limit
     * @return string Rendered HTML, or '' if there are no activities
     */
    public function render(string $entityType, int $entityId, int $limit = 20): string
    {
        $activities = $this->activities->forEntity($entityType, $entityId, $limit);
        if (empty($activities)) {
            return '';
        }

        return $this->view->render('activity/_timeline', [
            'activities' => $activities,
        ]);
    }
}
