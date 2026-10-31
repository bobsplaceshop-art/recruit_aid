<?php
use CorporateAid\Core\Row;
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Applications</h1>
        </div>
    </div>
</div>

<form method="GET" action="/applications" class="mb-3">
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" placeholder="Search by candidate, vacancy, or app code..."
               value="<?= e($q) ?>" style="max-width:400px;" autocomplete="off">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i> Search
        </button>
        <?php if ($q !== ''): ?>
            <a href="/recruit/applications" class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i> Clear
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label"><?= (int) $total ?> total applications</span>
        </div>
    </div>
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            No applications found.
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>App Code</th>
                    <th>Candidate</th>
                    <th>Vacancy</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th style="width:80px;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $appCode   = Row::str($r, 'code');
                        $candFirst = Row::str($r, 'first_name');
                        $candLast  = Row::str($r, 'last_name');
                        $candCode  = Row::str($r, 'cand_code');
                        $vacTitle  = Row::str($r, 'vac_title');
                        $vacCode   = Row::str($r, 'vac_code');
                        $status    = Row::str($r, 'status');
                        $appliedAt = Row::str($r, 'applied_at');
                    ?>
                    <tr>
                        <td><code style="font-size:.85rem;"><?= e($appCode) ?></code></td>
                        <td>
                            <a href="/candidates/<?= e($candCode) ?>" class="text-decoration-none">
                                <strong><?= e($candFirst . ' ' . $candLast) ?></strong>
                            </a>
                        </td>
                        <td>
                            <a href="/vacancies/<?= e($vacCode) ?>" class="text-decoration-none">
                                <?= e($vacTitle) ?>
                            </a>
                        </td>
                        <td><span class="ra-badge"><?= e(ucfirst($status)) ?></span></td>
                        <td class="muted small"><?= e(human_time($appliedAt)) ?></td>
                        <td>
                            <a href="/vacancies/<?= e($vacCode) ?>" class="ra-row-actions" title="View Pipeline">
                                <i class="bi bi-kanban"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">Page <?= (int) $page ?> of <?= (int) $totalPages ?></div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="/applications?page=<?= $page - 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="/applications?page=<?= $page + 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
