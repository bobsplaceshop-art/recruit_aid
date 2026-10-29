<?php
/**
 * Reusable activity timeline card.
 *
 * $activities — array<int, array{
 *   id:int, user_id:?int, user_name:string, action:string,
 *   description:string, created_at:string
 * }>
 */
?>
<div class="ra-card mt-3">
    <h2 class="h5 mb-3">Activity</h2>
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
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
