<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Recruitment · <a href="/vacancies/<?= e($vacancy->code) ?>" class="text-decoration-none"><?= e($vacancy->title) ?></a> · Add candidate
            </div>
            <h1 class="ra-page-band-title">Add candidate to pipeline</h1>
            <p class="ra-page-sub">Choose a candidate to link to this vacancy. The candidate will start at the first pipeline stage.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/vacancies/<?= e($vacancy->code) ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<form method="GET" action="/vacancies/<?= e($vacancy->code) ?>/add-candidate" class="mb-3">
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" placeholder="Search candidates by name, email, code..."
               value="<?= e($q) ?>" style="max-width:400px;" autocomplete="off">
        <button type="submit" class="ra-btn ra-btn-outline">
            <i class="bi bi-search"></i> Search
        </button>
        <?php if ($q !== ''): ?>
            <a href="/vacancies/<?= e($vacancy->code) ?>/add-candidate" class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i> Clear
            </a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($candidates)): ?>
    <div class="ra-card text-center muted" style="padding:3rem 1.5rem;">
        <?php if ($q !== ''): ?>
            No candidates match "<?= e($q) ?>".
        <?php else: ?>
            No candidates available. All active candidates are already in this pipeline, or there are no candidates yet.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="ra-table-card">
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Current role</th>
                    <th>Status</th>
                    <th style="width:140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $c): ?>
                    <tr>
                        <td><code style="font-size:.85rem;"><?= e($c->code) ?></code></td>
                        <td><a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none"><strong><?= e($c->fullName()) ?></strong></a></td>
                        <td class="muted small"><?= e($c->email) ?></td>
                        <td class="muted small">
                            <?php if ($c->currentJobTitle): ?>
                                <?= e($c->currentJobTitle) ?><?php if ($c->currentCompany): ?> · <?= e($c->currentCompany) ?><?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><span class="<?= $c->statusBadgeClass() ?>"><?= e($c->statusLabel()) ?></span></td>
                        <td>
                            <form method="POST" action="/vacancies/<?= e($vacancy->code) ?>/add-candidate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="candidate_id" value="<?= (int) $c->id ?>">
                                <button type="submit" class="ra-btn ra-btn-primary ra-btn-sm">
                                    <i class="bi bi-plus-lg"></i> Add to pipeline
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="muted small">Page <?= (int) $page ?> of <?= (int) $totalPages ?></div>
            <div class="d-flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="/vacancies/<?= e($vacancy->code) ?>/add-candidate?page=<?= $page - 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="/vacancies/<?= e($vacancy->code) ?>/add-candidate?page=<?= $page + 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="ra-btn ra-btn-outline ra-btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
