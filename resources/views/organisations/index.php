<?php
/**
 * Organisations list — search, filter pills, pagination, row actions.
 *
 * $organisations  — Organisation[]
 * $total          — total count
 * $page           — current page
 * $per_page       — per-page size
 * $total_pages    — total pages
 * $counts         — ['all'=>N, 'prospect'=>N, 'active'=>N, ...]
 * $search         — current search term
 * $status         — current status filter
 */
$activeStatus = $status === '' ? 'all' : $status;

$statusPills = [
    'all'           => 'All',
    'prospect'      => 'Prospects',
    'active'        => 'Active',
    'inactive'      => 'Inactive',
    'former_client' => 'Former Clients',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Clients</div>
            <h1 class="ra-page-band-title">Organisations</h1>
        </div>
        <div class="ra-page-band-actions">
            <?php if ($user->can('recruit.organisations.create')): ?>
                <a href="/organisations/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Organisation</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter pills -->
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/organisations?status=<?= $key === 'all' ? '' : urlencode($key) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
                <span class="muted ms-1" style="font-size:.75rem;">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Search -->
<form method="GET" action="/organisations" class="mb-3">
    <?php if ($activeStatus !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" value="<?= e($search) ?>"
               placeholder="Search by name, code, industry, or email..."
               style="max-width:400px;">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i>
            <span>Search</span>
        </button>
        <?php if ($search !== '' || $activeStatus !== 'all'): ?>
            <a href="/organisations" class="ra-btn ra-btn-outline">
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
                <?= (int) $total ?> organisation<?= $total === 1 ? '' : 's' ?>
                <?php if ($search !== ''): ?>
                    matching "<?= e($search) ?>"
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php if (empty($organisations)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($search !== '' || $activeStatus !== 'all'): ?>
                <div class="mb-2">No organisations match your filters.</div>
                <a href="/organisations" class="ra-btn ra-btn-outline">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear filters</span>
                </a>
            <?php else: ?>
                <div class="mb-3">No organisations yet.</div>
                <?php if ($user->can('recruit.organisations.create')): ?>
                    <a href="/organisations/create" class="ra-btn ra-btn-primary">
                        <i class="bi bi-plus-lg"></i>
                        <span>Add the first organisation</span>
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
                    <th>Industry</th>
                    <th>Country</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:150px;">Last updated</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($organisations as $org): ?>
                <tr>
                    <td>
                        <a href="/organisations/<?= e($org->code) ?>" class="text-decoration-none">
                            <code style="font-size:.85rem;"><?= e($org->code) ?></code>
                        </a>
                    </td>
                    <td>
                        <a href="/organisations/<?= e($org->code) ?>" class="text-decoration-none">
                            <strong><?= e($org->name) ?></strong>
                        </a>
                        <?php if ($org->legalName): ?>
                            <div class="muted small"><?= e($org->legalName) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="muted small"><?= e($org->industry ?? '—') ?></td>
                    <td class="muted small"><?= e($org->countryCode ?? '—') ?></td>
                    <td>
                        <span class="<?= $org->statusBadgeClass() ?>"><?= e($org->statusLabel()) ?></span>
                    </td>
                    <td class="muted small"><?= e($org->updatedAt ?? '—') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/organisations/<?= e($org->code) ?>" class="ra-row-actions" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($user->can('recruit.organisations.edit')): ?>
                                <a href="/organisations/<?= e($org->code) ?>/edit" class="ra-row-actions" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
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
            return '/organisations?' . implode('&', $parts);
        };
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">
            Page <?= (int) $page ?> of <?= (int) $total_pages ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <i class="bi bi-chevron-left"></i>
                    <span>Previous</span>
                </a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <span>Next</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
