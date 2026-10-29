<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">System-wide</div>
            <h1 class="ra-page-band-title">Activity Feed</h1>
            <p class="ra-page-sub"><?= (int) $total ?> total event<?= $total === 1 ? '' : 's' ?></p>
        </div>
        <div class="ra-page-band-actions">
            <form method="GET" action="/activity" class="d-flex gap-2">
                <input type="text" name="q" class="ra-input" placeholder="Search activities..." value="<?= e($search) ?>" style="width: 250px;">
                <button type="submit" class="ra-btn ra-btn-outline">Search</button>
            </form>
        </div>
    </div>
</div>

<div class="ra-card">
    <?php if (empty($activities)): ?>
        <div class="text-center muted" style="padding: 2rem;">
            No activities found<?= $search !== '' ? ' for "' . e($search) . '"' : '' ?>.
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
                            <?php if ($a['subject_type'] && $a['subject_id']): ?>
                                <span class="muted">·</span>
                                <span><?= e($a['subject_type']) ?> #<?= (int) $a['subject_id'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">Page <?= (int) $page ?> of <?= (int) $totalPages ?></div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="/activity?page=<?= $page - 1 ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="/activity?page=<?= $page + 1 ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
