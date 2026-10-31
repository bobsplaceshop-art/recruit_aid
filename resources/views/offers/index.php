<?php
use CorporateAid\Core\Row;
?>
<?php
$activeStatus = $status === '' ? 'all' : $status;
$statusPills = [
    'all'       => 'All',
    'draft'     => 'Draft',
    'sent'      => 'Sent',
    'accepted'  => 'Accepted',
    'declined'  => 'Declined',
    'expired'   => 'Expired',
    'withdrawn' => 'Withdrawn',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Offers</h1>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/offers?status=<?= $key === 'all' ? '' : urlencode($key) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
                <span class="muted ms-1" style="font-size:.75rem;">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<form method="GET" action="/offers" class="mb-3">
    <?php if ($activeStatus !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" placeholder="Search by candidate, vacancy, or offer code..." value="<?= e($q) ?>" style="max-width:400px;" autocomplete="off">
        <button type="submit" class="ra-btn ra-btn-outline"><i class="bi bi-search"></i> Search</button>
        <?php if ($q !== '' || $activeStatus !== 'all'): ?>
            <a href="/recruit/offers" class="ra-btn ra-btn-outline"><i class="bi bi-x-lg"></i> Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label"><?= (int) $total ?> offer<?= $total === 1 ? '' : 's' ?></span>
        </div>
    </div>
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">No offers found.</div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Candidate</th>
                    <th>Vacancy</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th>Expires</th>
                    <th style="width:80px;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $code     = Row::str($r, 'code');
                        $candF    = Row::str($r, 'first_name');
                        $candL    = Row::str($r, 'last_name');
                        $candCode = Row::str($r, 'cand_code');
                        $vacTitle = Row::str($r, 'vac_title');
                        $vacCode  = Row::str($r, 'vac_code');
                        $salary   = Row::float($r, 'offered_salary');
                        $currency = Row::str($r, 'currency');
                        $statusVal = Row::str($r, 'status');
                        $startDate = Row::str($r, 'start_date');
                        $expiresAt = Row::str($r, 'expires_at');
                        $badge = match ($statusVal) {
                            'draft'     => 'ra-badge',
                            'sent'      => 'ra-badge ra-badge-warn',
                            'accepted'  => 'ra-badge ra-badge-success',
                            'declined'  => 'ra-badge ra-badge-danger',
                            'expired'   => 'ra-badge ra-badge-warn',
                            'withdrawn' => 'ra-badge ra-badge-danger',
                            default     => 'ra-badge',
                        };
                    ?>
                    <tr>
                        <td><code style="font-size:.85rem;"><?= e($code) ?></code></td>
                        <td><a href="/candidates/<?= e($candCode) ?>" class="text-decoration-none"><strong><?= e($candF . ' ' . $candL) ?></strong></a></td>
                        <td><a href="/vacancies/<?= e($vacCode) ?>" class="text-decoration-none"><?= e($vacTitle) ?></a></td>
                        <td><strong><?= e($currency) ?> <?= e(number_format($salary, 0)) ?></strong></td>
                        <td><span class="<?= $badge ?>"><?= e(ucfirst($statusVal)) ?></span></td>
                        <td class="muted small"><?= e($startDate) ?></td>
                        <td class="muted small"><?= e($expiresAt) ?></td>
                        <td><a href="/offers/<?= e($code) ?>" class="ra-row-actions"><i class="bi bi-eye"></i></a></td>
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
                <a href="/offers?page=<?= $page - 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $activeStatus !== 'all' ? '&status=' . urlencode($activeStatus) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="/offers?page=<?= $page + 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $activeStatus !== 'all' ? '&status=' . urlencode($activeStatus) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
