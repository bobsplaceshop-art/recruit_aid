<?php
/**
 * Candidates list — search, status pills, pagination, row actions.
 *
 * $rows     — Candidate[]
 * $total    — total count
 * $page     — current page
 * $perPage  — per-page size
 * $totalPages — total pages
 * $search   — current search term
 * $status   — current status filter
 */
$activeStatus = $status === '' ? 'all' : $status;

$statusPills = [
    'all'         => 'All',
    'active'      => 'Active',
    'passive'     => 'Passive',
    'hired'       => 'Hired',
    'blacklisted' => 'Blacklisted',
    'archived'    => 'Archived',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Candidates</h1>
        </div>
        <div class="ra-page-band-actions">
            <?php if ($user->can('recruit.candidates.create')): ?>
                <a href="/candidates/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Candidate</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/candidates?status=<?= $key === 'all' ? '' : urlencode($key) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<form method="GET" action="/candidates" class="mb-3">
    <?php if ($activeStatus !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" value="<?= e($search) ?>" data-live-search="candidates" autocomplete="off"
               placeholder="Search by name, email, code, or job title..."
               style="max-width:400px;">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i>
            <span>Search</span>
        </button>
        <?php if ($search !== '' || $activeStatus !== 'all'): ?>
            <a href="/candidates" class="ra-btn ra-btn-outline">
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
                <span data-live-count><?= (int) $total ?></span> candidate<span data-live-count-plural><?= $total === 1 ? '' : 's' ?></span>
                <?php if ($search !== ''): ?>
                    matching "<?= e($search) ?>"
                <?php endif; ?>
            </span>
        </div>
        <div class="ra-table-toolbar-right">
            <?= view_partial('partials/export-button', [
                'entity'  => 'candidates',
                'filters' => ['q' => $search, 'status' => $status],
            ]) ?>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($search !== '' || $activeStatus !== 'all'): ?>
                <div class="mb-2">No candidates match your filters.</div>
                <a href="/candidates" class="ra-btn ra-btn-outline">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear filters</span>
                </a>
            <?php else: ?>
                <div class="mb-3">No candidates yet.</div>
                <?php if ($user->can('recruit.candidates.create')): ?>
                    <a href="/candidates/create" class="ra-btn ra-btn-primary">
                        <i class="bi bi-plus-lg"></i>
                        <span>Add the first candidate</span>
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
                    <th>Email</th>
                    <th>Current role</th>
                    <th>Seniority</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-results>
            <?php foreach ($rows as $c): ?>
                <tr>
                    <td>
                        <a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none">
                            <code style="font-size:.85rem;"><?= e($c->code) ?></code>
                        </a>
                    </td>
                    <td>
                        <a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none">
                            <strong><?= e($c->fullName()) ?></strong>
                        </a>
                    </td>
                    <td class="muted small"><?= e($c->email) ?></td>
                    <td class="muted small">
                        <?php if ($c->currentJobTitle): ?>
                            <?= e($c->currentJobTitle) ?><?php if ($c->currentCompany): ?><span class="muted"> · <?= e($c->currentCompany) ?></span><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="muted small"><?= $c->seniority ? e($c->seniorityLabel()) : '—' ?></td>
                    <td>
                        <span class="<?= $c->statusBadgeClass() ?>"><?= e($c->statusLabel()) ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/candidates/<?= e($c->code) ?>" class="ra-row-actions" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($user->can('recruit.candidates.edit')): ?>
                                <a href="/candidates/<?= e($c->code) ?>/edit" class="ra-row-actions" title="Edit">
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

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="muted small">Page <?= (int) $page ?> of <?= (int) $totalPages ?></div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
                <a href="/candidates?page=<?= $page - 1 ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $activeStatus !== 'all' ? '&status=' . urlencode($activeStatus) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="/candidates?page=<?= $page + 1 ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $activeStatus !== 'all' ? '&status=' . urlencode($activeStatus) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
