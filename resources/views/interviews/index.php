<?php
$activeStatus = $status === '' ? 'all' : $status;
$statusPills = [
    'all'       => 'All',
    'scheduled' => 'Scheduled',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show'   => 'No Show',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Interviews</h1>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/interviews?status=<?= $key === 'all' ? '' : urlencode($key) ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
                <span class="muted ms-1" style="font-size:.75rem;">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label">
                <?= (int) ($counts['all'] ?? 0) ?> interview<?= ($counts['all'] ?? 0) === 1 ? '' : 's' ?>
            </span>
        </div>
    </div>
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($activeStatus !== 'all'): ?>
                No <?= e($activeStatus) ?> interviews.
                <a href="/recruit/interviews" class="ms-2">Clear filter</a>
            <?php else: ?>
                No interviews scheduled yet.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Candidate</th>
                    <th>Vacancy</th>
                    <th>Scheduled</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="width:80px;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $i = $row['interview']; $c = $row['candidate']; $v = $row['vacancy']; ?>
                    <tr>
                        <td><code style="font-size:.85rem;"><?= e($i->code) ?></code></td>
                        <td>
                            <a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none">
                                <strong><?= e($c->fullName()) ?></strong>
                            </a>
                        </td>
                        <td>
                            <a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none">
                                <?= e($v->title) ?>
                            </a>
                        </td>
                        <td class="muted small">
                            <?= e($i->scheduledAt) ?>
                            <?php if ($i->isScheduled() && strtotime($i->scheduledAt) < time()): ?>
                                <span class="ra-badge ra-badge-warn ms-1" title="Past scheduled time, not yet marked complete">Overdue</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted small"><?= e($i->typeLabel()) ?></td>
                        <td><span class="<?= $i->statusBadgeClass() ?>"><?= e($i->statusLabel()) ?></span></td>
                        <td>
                            <a href="/interviews/<?= e($i->code) ?>" class="ra-row-actions" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
