<?php
/**
 * Global contacts list — filterable.
 *
 * $rows       — array of ['contact' => Contact, 'organisation_name' => string, 'organisation_code' => string]
 * $total, $page, $perPage, $totalPages
 * $allOrgs    — array of ['id','code','name'] for org filter
 * $q, $orgId, $role — current filter values
 */
// Load role options from lookups
$roleOptions = ['' => 'Any role'];
try {
    $lookups = \CorporateAid\Core\App::instance()
        ->container()
        ->get(\CorporateAid\Platform\Services\LookupService::class);
    $roleOptions += $lookups->allFor('contact_role');
} catch (\Throwable) {
    // Fallback to legacy hardcoded roles if LookupService fails
    $roleOptions += [
        'is_primary'         => 'Primary Contact',
        'is_hiring_manager'  => 'Hiring Manager',
        'is_finance'         => 'Finance',
        'is_hr'              => 'HR',
        'is_decision_maker'  => 'Decision Maker',
    ];
}
$hasFilters = $q !== '' || $orgId > 0 || $role !== '';

$pageUrl = function (int $p) use ($q, $orgId, $role): string {
    $parts = ['page=' . $p];
    if ($q !== '') $parts[] = 'q=' . urlencode($q);
    if ($orgId > 0) $parts[] = 'org=' . $orgId;
    if ($role !== '') $parts[] = 'role=' . urlencode($role);
    return '/contacts?' . implode('&', $parts);
};
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Clients · Contacts</div>
            <h1 class="ra-page-band-title">Contacts</h1>
            <p class="ra-page-sub">All contacts across every organisation.</p>
        </div>
    </div>
</div>

<form method="GET" action="/contacts" class="ra-card mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="ra-label">Search</label>
            <input type="text" name="q" class="ra-input" value="<?= e($q) ?>" data-live-search="contacts" autocomplete="off"
                   placeholder="Name, email, position, or org name…">
        </div>
        <div class="col-md-3">
            <label class="ra-label">Organisation</label>
            <select name="org" class="ra-select" style="width:100%;">
                <option value="">All organisations</option>
                <?php foreach ($allOrgs as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= $orgId === (int) $o['id'] ? 'selected' : '' ?>>
                        <?= e($o['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="ra-label">Role</label>
            <select name="role" class="ra-select" style="width:100%;">
                <?php foreach ($roleOptions as $slug => $label): ?>
                    <option value="<?= e($slug) ?>" <?= $role === $slug ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary" style="flex:1;">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($hasFilters): ?>
                <a href="/contacts" class="ra-btn ra-btn-outline" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label">
                <span data-live-count><?= (int) $total ?></span> contact<span data-live-count-plural><?= $total === 1 ? '' : 's' ?></span>
                <?php if ($hasFilters): ?>
                    <span class="muted">(filtered)</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <?php if ($hasFilters): ?>
                <div class="mb-3">No contacts match your filters.</div>
                <a href="/contacts" class="ra-btn ra-btn-outline">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear filters</span>
                </a>
            <?php else: ?>
                No contacts yet. Create your first contact from an organisation's page.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Organisation</th>
                    <th>Position</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Roles</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-results>
            <?php foreach ($rows as $row): ?>
                <?php
                    $c = $row['contact'];
                    $orgCode = $row['organisation_code'];
                    $orgName = $row['organisation_name'];
                ?>
                <tr>
                    <td>
                        <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>?return_to=contacts" class="text-decoration-none">
                            <strong><?= e($c->fullName()) ?></strong>
                        </a>
                        <?php if ($c->department): ?>
                            <div class="muted small"><?= e($c->department) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/organisations/<?= e($orgCode) ?>" class="text-decoration-none">
                            <?= e($orgName) ?>
                        </a>
                    </td>
                    <td class="muted small"><?= e($c->position ?? '—') ?></td>
                    <td>
                        <?php if ($c->email): ?>
                            <a href="mailto:<?= e($c->email) ?>"><?= e($c->email) ?></a>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted small"><?= e($c->phone ?? '—') ?></td>
                    <td>
                        <?php foreach ($c->roleLabels() as $label): ?>
                            <span class="ra-badge me-1"><?= e($label) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>?return_to=contacts" class="ra-row-actions" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($user->can('recruit.contacts.edit')): ?>
                            <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>/edit" class="ra-row-actions" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
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
