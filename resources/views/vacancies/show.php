<?php
/**
 * Vacancy detail page.
 *
 * $vacancy, $organisation, $engagement, $stages, $requirements
 */
use CorporateAid\Core\App;

$renderer = App::instance()
    ->container()
    ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

$customFieldsHtml = $renderer->renderDetail('vacancy', $vacancy->id);

$delBase = '/vacancies/' . $vacancy->code;

// Group requirements by type
$byType = ['responsibility' => [], 'requirement' => [], 'preferred' => [], 'skill' => [], 'language' => []];
foreach ($requirements as $r) {
    if (isset($byType[$r->type])) $byType[$r->type][] = $r->value;
}
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Recruitment ·
                <?php if ($organisation): ?>
                    <a href="/organisations/<?= e($organisation->code) ?>" class="text-decoration-none">
                        <?= e($organisation->name) ?>
                    </a>
                <?php else: ?>Unknown client<?php endif; ?>
                · <code><?= e($vacancy->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($vacancy->title) ?></h1>
            <div class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                <span class="<?= $vacancy->statusBadgeClass() ?>"><?= e($vacancy->statusLabel()) ?></span>
                <span class="muted small">· <?= e($vacancy->seniorityLabel()) ?></span>
                <span class="muted small">· <?= e($vacancy->employmentTypeLabel()) ?></span>
                <span class="muted small">· <?= e($vacancy->workModeLabel()) ?></span>
                <?php if ($vacancy->positionsCount > 1): ?>
                    <span class="ra-badge"><?= (int) $vacancy->positionsCount ?> positions</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/vacancies" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i><span>Back</span>
            </a>
            <?php if ($user->can('vacancies.edit')): ?>
                <a href="<?= e($delBase) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i><span>Edit</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('vacancies.close')): ?>
                <button type="button" class="ra-btn ra-btn-outline"
                        style="color:var(--ra-error-fg);border-color:var(--ra-error-bd);"
                        onclick="RA_DeletionModal.open({
                            entityLabel: '<?= e(addslashes($vacancy->title)) ?> (<?= e($vacancy->code) ?>)',
                            infoUrl: '<?= e($delBase) ?>/delete-info',
                            submitUrl: '<?= e($delBase) ?>/delete',
                            csrfToken: '<?= e(csrf_token()) ?>'
                        })">
                    <i class="bi bi-trash"></i><span>Delete</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="ra-card">
            <h2 class="h5 mb-3">Overview</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Client</div>
                    <div>
                        <?php if ($organisation): ?>
                            <a href="/organisations/<?= e($organisation->code) ?>"><?= e($organisation->name) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
                <?php if ($engagement): ?>
                <div class="col-md-6">
                    <div class="muted small">Engagement</div>
                    <div>
                        <a href="/engagements/<?= e($engagement->code) ?>">
                            <?= e($engagement->name) ?> (<?= e($engagement->code) ?>)
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($vacancy->department): ?>
                <div class="col-md-6">
                    <div class="muted small">Department</div>
                    <div><?= e($vacancy->department) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($vacancy->city || $vacancy->countryCode): ?>
                <div class="col-md-6">
                    <div class="muted small">Location</div>
                    <div>
                        <?= e($vacancy->city ?? '') ?>
                        <?php if ($vacancy->city && $vacancy->countryCode): ?>, <?php endif; ?>
                        <?= e($vacancy->countryCode ?? '') ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($vacancy->openingDate): ?>
                <div class="col-md-3">
                    <div class="muted small">Opening date</div>
                    <div><?= e($vacancy->openingDate) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($vacancy->targetHireDate): ?>
                <div class="col-md-3">
                    <div class="muted small">Target hire</div>
                    <div><?= e($vacancy->targetHireDate) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($vacancy->salaryRange()): ?>
                <div class="col-md-6">
                    <div class="muted small">Salary range</div>
                    <div><?= e($vacancy->salaryRange()) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pipeline stages -->
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Pipeline</h2>
            <?php if (empty($stages)): ?>
                <div class="muted small">No pipeline stages defined.</div>
            <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;">
                    <?php foreach ($stages as $i => $s): ?>
                        <?php if ($i > 0): ?>
                            <i class="bi bi-chevron-right muted" style="font-size:.7rem;"></i>
                        <?php endif; ?>
                        <span class="ra-badge"
                              style="<?= $s->color ? 'background:' . e($s->color) . '20;color:' . e($s->color) . ';' : '' ?>">
                            <?= e($s->label) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="muted small mt-3">
                    <?= count($stages) ?> stages defined for this vacancy. These stages will be used to track candidates through the recruitment process.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($vacancy->description): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Job description</h2>
            <div style="white-space:pre-line;"><?= e($vacancy->description) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($byType['responsibility'])): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Responsibilities</h2>
            <ul style="padding-left:1.2rem;margin:0;">
                <?php foreach ($byType['responsibility'] as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($byType['requirement'])): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Requirements</h2>
            <ul style="padding-left:1.2rem;margin:0;">
                <?php foreach ($byType['requirement'] as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($byType['preferred'])): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Preferred qualifications</h2>
            <ul style="padding-left:1.2rem;margin:0;">
                <?php foreach ($byType['preferred'] as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($byType['skill']) || !empty($byType['language'])): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Skills &amp; languages</h2>
            <?php if (!empty($byType['skill'])): ?>
                <div class="muted small mb-1">Skills</div>
                <div class="mb-3">
                    <?php foreach ($byType['skill'] as $item): ?>
                        <span class="ra-badge me-1"><?= e($item) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($byType['language'])): ?>
                <div class="muted small mb-1">Languages</div>
                <div>
                    <?php foreach ($byType['language'] as $item): ?>
                        <span class="ra-badge me-1"><?= e($item) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($customFieldsHtml): ?><?= $customFieldsHtml ?><?php endif; ?>

        <?php if ($vacancy->clientNotes): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Client notes</h2>
            <div style="white-space:pre-line;"><?= e($vacancy->clientNotes) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($vacancy->internalNotes): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Internal notes</h2>
            <div style="white-space:pre-line;"><?= e($vacancy->internalNotes) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ra-card">
            <h2 class="h6 mb-3 muted">Record info</h2>
            <div class="mb-2">
                <div class="ra-section-label">Code</div>
                <div><code><?= e($vacancy->code) ?></code></div>
            </div>
            <div class="mb-2">
                <div class="ra-section-label">Created</div>
                <div class="small"><?= e($vacancy->createdAt ?? '—') ?></div>
            </div>
            <div class="mb-0">
                <div class="ra-section-label">Last updated</div>
                <div class="small"><?= e($vacancy->updatedAt ?? '—') ?></div>
            </div>
        </div>

        <div class="ra-card mt-3">
            <h2 class="h6 mb-3 muted">Quick actions</h2>
            <?php if ($user->can('vacancies.edit')): ?>
                <a href="<?= e($delBase) ?>/edit" class="ra-dropdown-item mb-1">
                    <i class="bi bi-pencil"></i><span>Edit vacancy</span>
                </a>
            <?php endif; ?>
            <a href="#" class="ra-dropdown-item disabled" onclick="return false;">
                <i class="bi bi-people"></i>
                <span>Add candidate <span class="muted small">(soon)</span></span>
            </a>
            <a href="#" class="ra-dropdown-item disabled" onclick="return false;">
                <i class="bi bi-diagram-3"></i>
                <span>View applications <span class="muted small">(soon)</span></span>
            </a>
        </div>
    </div>
</div>

<?= view_partial('partials/deletion-modal') ?>
<script src="<?= asset('js/deletion-modal.js') ?>?v=2"></script>
