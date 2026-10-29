<?php
/**
 * Vacancies list — top-level, filterable.
 *
 * $vacancies    — Vacancy[]
 * $orgMap       — [orgId => Organisation]
 * $total, $page, $total_pages, $counts, $search, $status
 */
$activeStatus = $status === '' ? 'all' : $status;

$statusPills = [
    'all'              => 'All',
    'draft'            => 'Drafts',
    'open'             => 'Open',
    'sourcing'         => 'Sourcing',
    'screening'        => 'Screening',
    'shortlisting'     => 'Shortlisting',
    'client_review'    => 'Client Review',
    'interviewing'     => 'Interviewing',
    'offer'            => 'Offer',
    'filled'           => 'Filled',
    'on_hold'          => 'On Hold',
    'cancelled'        => 'Cancelled',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Vacancies</h1>
            <p class="ra-page-sub">The jobs you're filling across all clients</p>
        </div>
        <div class="ra-page-band-actions">
            <?php if ($user->can('recruit.vacancies.create')): ?>
                <a href="/vacancies/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Vacancy</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="ra-pill-group" style="flex-wrap:wrap;">
        <?php foreach ($statusPills as $key => $label): ?>
            <a href="/vacancies?status=<?= $key === 'all' ? '' : urlencode($key) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
               class="ra-pill <?= $activeStatus === $key ? 'is-active' : '' ?>">
                <?= e($label) ?>
                <span class="muted ms-1" style="font-size:.75rem;">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<form method="GET" action="/vacancies" class="mb-3">
    <?php if ($activeStatus !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
    <?php endif; ?>
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" value="<?= e($search) ?>" data-live-search="vacancies" autocomplete="off"
               placeholder="Search by title, code, or city..."
               style="max-width:400px;">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i>
            <span>Search</span>
        </button>
        <?php if ($search !== '' || $activeStatus !== 'all'): ?>
            <a href="/vacancies" class="ra-btn ra-btn-outline">
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
                <span data-live-count><?= (int) $total ?></span> vacanc<span data-live-count-plural><?= $total === 1 ? 'y' : 'ies' ?></span>
                <?php if ($search !== ''): ?> matching "<?= e($search) ?>"<?php endif; ?>
            </span>
        </div>
    </div>

    <?php if (empty($vacancies)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($search !== '' || $activeStatus !== 'all'): ?>
                <div class="mb-2">No vacancies match your filters.</div>
                <a href="/vacancies" class="ra-btn ra-btn-outline">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear filters</span>
                </a>
            <?php else: ?>
                <div class="mb-3">No vacancies yet.</div>
                <?php if ($user->can('recruit.vacancies.create')): ?>
                    <a href="/vacancies/create" class="ra-btn ra-btn-primary">
                        <i class="bi bi-plus-lg"></i>
                        <span>Create the first vacancy</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Location</th>
                    <th style="width:120px;">Type</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-results>
            <?php foreach ($vacancies as $v): ?>
                <?php $org = $orgMap[$v->organisationId] ?? null; ?>
                <tr>
                    <td><a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none"><code><?= e($v->code) ?></code></a></td>
                    <td>
                        <a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none">
                            <strong><?= e($v->title) ?></strong>
                        </a>
                        <?php if ($v->department): ?>
                            <div class="muted small"><?= e($v->department) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="muted small">
                        <?php if ($org): ?>
                            <a href="/organisations/<?= e($org->code) ?>"><?= e($org->name) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="muted small">
                        <?= e($v->city ?? '—') ?>
                        <?php if ($v->countryCode): ?>, <?= e($v->countryCode) ?><?php endif; ?>
                        <div class="muted" style="font-size:.72rem;"><?= e($v->workModeLabel()) ?></div>
                    </td>
                    <td class="muted small"><?= e($v->employmentTypeLabel()) ?></td>
                    <td><span class="<?= $v->statusBadgeClass() ?>"><?= e($v->statusLabel()) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/vacancies/<?= e($v->code) ?>" class="ra-row-actions" title="View"><i class="bi bi-eye"></i></a>
                            <?php if ($user->can('recruit.vacancies.edit')): ?>
                                <a href="/vacancies/<?= e($v->code) ?>/edit" class="ra-row-actions" title="Edit"><i class="bi bi-pencil"></i></a>
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
            return '/vacancies?' . implode('&', $parts);
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
