<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Activity · <code><?= e($entity['code']) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($entity['label']) ?></h1>
            <p class="ra-page-sub"><?= (int) $total ?> total event<?= $total === 1 ? '' : 's' ?></p>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= e($entity['backUrl']) ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-card">
    <?php if (empty($activities)): ?>
        <div class="text-center muted" style="padding: 2rem;">
            No activity recorded for this <?= e($entityType) ?>.
        </div>
    <?php else: ?>
        <div class="ra-timeline">
            <?php foreach ($activities as $a): ?>
                <div class="ra-timeline-item">
                    <div class="ra-timeline-dot"></div>
                    <div class="ra-timeline-body">
                        <div class="ra-timeline-text">
                            <strong><?= e($a['user_name']) ?></strong>
                            <span class="muted">·</span>
                            <span><?= e($a['description']) ?></span>
                        </div>
                        <div class="ra-timeline-time muted small">
                            <?= e(human_time($a['created_at'])) ?>
                            <span class="muted">·</span>
                            <code class="muted" style="font-size: .75em;"><?= e($a['action']) ?></code>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <?php
        $pageUrl = function (int $p) use ($entityType, $entityId): string {
            return '/activity/' . rawurlencode($entityType) . '/' . $entityId . '?page=' . $p;
        };
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">
            Page <?= (int) $page ?> of <?= (int) $totalPages ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <i class="bi bi-chevron-left"></i>
                    <span>Previous</span>
                </a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <span>Next</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
