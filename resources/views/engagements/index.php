<?php
/**
 * Engagements list — top-level, filterable.
 *
 * $engagements — Engagement[]
 * $orgMap      — [orgId => Organisation]
 * $total, $page, $total_pages, $counts, $search, $status
 */
$activeStatus = $status === '' ? 'all' : $status;

$statusPills = [
    'all'               => 'All',
    'draft'             => 'Drafts',
    'proposal'          => 'Proposals',
    'pending_agreement' => 'Pending',
    'active'            => 'Active',
    'paused'            => 'Paused',
    'completed'         => 'Completed',
    'cancelled'         => 'Cancelled',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Clients</div>
            <h1 class="ra-page-band-title">Engagements</h1>
            <p class="ra-page-sub">Recruitment contracts with client organisations</p>
        </div>
        <div class="ra-page-band-actions">
            <?php if ($user->can('engagements.create')): ?>
                <a href="/engagements/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Engagement</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/engagements?status=<?= $key === 'all' ? '' : urlencode($key) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
                <span class="muted ms-1" style="font-size:.75rem;">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<form method="GET" action="/engagements" class="mb-3">
    <?php if ($activeStatus !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" value="<?= e($search) ?>"
               placeholder="Search by name or code..."
               style="max-width:400px;">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i>
            <span>Search</span>
        </button>
        <?php if ($search !== '' || $activeStatus !== 'all'): ?>
            <a href="/engagements" class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i>
                <span>Clear</span>
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label">
                <?= (int) $total ?> engagement<?= $total === 1 ? '' : 's' ?>
                <?php if ($search !== ''): ?> matching "<?= e($search) ?>"<?php endif; ?>
            </span>
        </div>
    </div>

    <?php if (empty($engagements)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($search !== '' || $activeStatus !== 'all'): ?>
                <div class="mb-2">No engagements match your filters.</div>
                <a href="/engagements" class="ra-btn ra-btn-outline">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear filters</span>
                </a>
            <?php else: ?>
                <div class="mb-3">No engagements yet.</div>
                <?php if ($user->can('engagements.create')): ?>
                    <a href="/engagements/create" class="ra-btn ra-btn-primary">
                        <i class="bi bi-plus-lg"></i>
                        <span>Create the first engagement</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Dates</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($engagements as $e): ?>
                <?php $org = $orgMap[$e->organisationId] ?? null; ?>
                <tr>
                    <td><a href="/engagements/<?= e($e->code) ?>" class="text-decoration-none"><code><?= e($e->code) ?></code></a></td>
                    <td><a href="/engagements/<?= e($e->code) ?>" class="text-decoration-none"><strong><?= e($e->name) ?></strong></a></td>
                    <td class="muted small">
                        <?php if ($org): ?>
                            <a href="/organisations/<?= e($org->code) ?>"><?= e($org->name) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="muted small"><?= e($e->serviceTypeLabel()) ?></td>
                    <td class="muted small">
                        <?= e($e->startDate ?? '—') ?>
                        <?php if ($e->endDate): ?> → <?= e($e->endDate) ?><?php endif; ?>
                    </td>
                    <td><span class="<?= $e->statusBadgeClass() ?>"><?= e($e->statusLabel()) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/engagements/<?= e($e->code) ?>" class="ra-row-actions" title="View"><i class="bi bi-eye"></i></a>
                            <?php if ($user->can('engagements.edit')): ?>
                                <a href="/engagements/<?= e($e->code) ?>/edit" class="ra-row-actions" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
    <?php
        $pageUrl = function (int $p) use ($search, $activeStatus) {
            $parts = ['page=' . $p];
            if ($search !== '') $parts[] = 'q=' . urlencode($search);
            if ($activeStatus !== 'all') $parts[] = 'status=' . urlencode($activeStatus);
            return '/engagements?' . implode('&', $parts);
        };
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">Page <?= (int) $page ?> of <?= (int) $total_pages ?></div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <i class="bi bi-chevron-left"></i><span>Previous</span>
                </a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <span>Next</span><i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
