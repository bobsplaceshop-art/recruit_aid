<?php /** @var \CorporateAid\Recruit\Models\Vacancy[] $rows */ ?>
<?php /** @var int $total, $page, $perPage, $totalPages */ ?>

<h1 class="mb-3">Open Roles (<?= (int) $total ?>)</h1>

<?php if (empty($rows)): ?>
    <p class="text-muted">No open roles at the moment. Please check back soon.</p>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($rows as $vacancy): ?>
            <div class="col-12">
                <a href="/jobs/<?= e($vacancy->code) ?>" class="job-card d-block p-3 text-decoration-none text-reset">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="h5 mb-1"><?= e($vacancy->title) ?></h2>
                            <p class="mb-1 text-muted">
                                <?= e($vacancy->city ?? '') ?><?= $vacancy->countryCode ? ', ' . e($vacancy->countryCode) : '' ?>
                                · <?= e(ucfirst($vacancy->workMode)) ?>
                                · <?= e(ucfirst($vacancy->employmentType)) ?>
                            </p>
                        </div>
                        <span class="badge bg-secondary"><?= e($vacancy->code) ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="/jobs?page=<?= (int) ($page - 1) ?>">Previous</a></li>
                <?php endif; ?>
                <li class="page-item disabled"><span class="page-link">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span></li>
                <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="/jobs?page=<?= (int) ($page + 1) ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
