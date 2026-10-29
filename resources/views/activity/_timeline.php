<?php
/**
 * Reusable activity timeline card — preview (5 activities).
 *
 * $activities — array<int, array{
 *   id:int, user_id:?int, user_name:string, action:string,
 *   description:string, created_at:string
 * }>
 * $entityType — 'organisation' | 'contact' | 'engagement' | 'vacancy'
 * $entityId   — numeric id (for the "View all" link)
 * $totalCount — total activity count for this entity
 * $limit      — how many are shown here
 */
$showViewAll = $totalCount > $limit;

// Context-aware return parameter for the "View all" page
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$returnTo = '';
if ($entityType === 'contact' && rtrim($currentPath, '/') === '/contacts') {
    $returnTo = '?return_to=contacts';
}
?>
<div class="ra-card mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Activity</h2>
        <?php if ($showViewAll): ?>
            <a href="/activity/<?= e($entityType) ?>/<?= (int) $entityId ?><?= $returnTo ?>"
               class="small text-decoration-none"
               style="color: var(--ra-primary);">
                View all <?= (int) $totalCount ?> →
            </a>
        <?php endif; ?>
    </div>
    <div class="ra-timeline">
        <?php foreach ($activities as $a): ?>
            <div class="ra-timeline-item">
                <div class="ra-timeline-dot"></div>
                <div class="ra-timeline-body">
                    <div class="ra-timeline-text">
                        <strong><?= e($a['user_name']) ?></strong>
                        <span class="muted">·</span>
                        <span><?= e(activity_summary($a['description'])) ?></span>
                    </div>
                    <div class="ra-timeline-time muted small">
                        <?= e(human_time($a['created_at'])) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
