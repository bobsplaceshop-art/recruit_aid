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
$activityHtml = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Platform\Services\ActivityTimelineService::class)->render('vacancy', $vacancy->id);

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
            <?php if ($user->can('recruit.vacancies.edit')): ?>
                <a href="<?= e($delBase) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i><span>Edit</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.vacancies.close')): ?>
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

        <!-- Pipeline Kanban -->
        <div class="ra-card mt-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Pipeline</h2>
                <?php if ($user->can('recruit.pipeline.advance')): ?>
                    <a href="/vacancies/<?= e($vacancy->code) ?>/add-candidate" class="ra-btn ra-btn-primary ra-btn-sm">
                        <i class="bi bi-plus-lg"></i> Add candidate
                    </a>
                <?php endif; ?>
            </div>
            <?php if (empty($stages)): ?>
                <div class="muted small">No pipeline stages defined for this vacancy. Edit the vacancy to define stages.</div>
            <?php else: ?>
                <div class="ra-pipeline-board">
                    <?php foreach ($stages as $s): ?>
                        <?php
                            $entries = $applicationsByStage[$s->id] ?? [];
                            $count = count($entries);
                        ?>
                        <div class="ra-pipeline-column">
                            <div class="ra-pipeline-column-header">
                                <span class="ra-pipeline-column-title-pill" style="--stage-pill-color: <?= e($s->color ?? 'var(--ra-primary)') ?>;">
                                    <?= e($s->label) ?>
                                </span>
                                <span class="ra-pipeline-column-count"><?= (int) $count ?></span>
                                <?php if ($user->can('recruit.vacancies.edit')): ?>
                                    <a href="/vacancies/<?= e($vacancy->code) ?>/add-candidate" class="ra-pipeline-add-btn" title="Add candidate to this vacancy">
                                        <i class="bi bi-plus-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="ra-pipeline-column-body">
                                <?php if (empty($entries)): ?>
                                    <div class="ra-pipeline-empty">
                                        <i class="bi bi-inbox"></i>
                                        <span>No candidates</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($entries as $entry): ?>
                                        <?php
                                            $a = $entry['application'];
                                            $c = $entry['candidate'];
                                            $initials = strtoupper(substr($c->firstName, 0, 1) . substr($c->lastName, 0, 1));
                                        ?>
                                        <div class="ra-pipeline-card" style="--stage-color: <?= e($s->color ?? 'var(--ra-primary)') ?>;">
                                            <div class="ra-pipeline-card-header">
                                                <div class="ra-pipeline-avatar"><?= e($initials) ?></div>
                                                <div class="ra-pipeline-card-info">
                                                    <a href="/candidates/<?= e($c->code) ?>" class="ra-pipeline-card-name">
                                                        <?= e($c->fullName()) ?>
                                                    </a>
                                                    <div class="ra-pipeline-card-sub">
                                                        <?php if ($c->currentJobTitle): ?>
                                                            <?= e($c->currentJobTitle) ?>
                                                        <?php else: ?>
                                                            <code style="font-size:.7rem;"><?= e($c->code) ?></code>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ra-pipeline-card-footer">
                                                <span class="ra-pipeline-time-badge" title="Time in stage">
                                                    <i class="bi bi-clock"></i>
                                                    <?= e(human_time($entry['stage_since'] ?? $a->appliedAt)) ?>
                                                </span>
                                                <?php if ($user->can('recruit.pipeline.advance')): ?>
                                                    <div class="dropdown">
                                                        <button class="ra-pipeline-card-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-three-dots"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><h6 class="dropdown-header">Move to</h6></li>
                                                            <?php foreach ($stages as $target): ?>
                                                                <?php if ($target->id === $a->currentStageId) continue; ?>
                                                                <li>
                                                                    <form method="POST" action="/applications/<?= e($a->code) ?>/move" class="m-0">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="stage_id" value="<?= (int) $target->id ?>">
                                                                        <button type="submit" class="dropdown-item small"><?= e($target->label) ?></button>
                                                                    </form>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($applicationsByStage['_unassigned'])): ?>
                        <div class="ra-pipeline-column">
                            <div class="ra-pipeline-column-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="ra-pipeline-column-title muted">Unassigned</span>
                                    <span class="ra-pipeline-column-count"><?= count($applicationsByStage['_unassigned']) ?></span>
                                </div>
                            </div>
                            <div class="ra-pipeline-column-body">
                                <?php foreach ($applicationsByStage['_unassigned'] as $entry): ?>
                                    <?php $a = $entry['application']; $c = $entry['candidate']; ?>
                                    <div class="ra-pipeline-card">
                                        <div class="ra-pipeline-card-header">
                                            <div class="ra-pipeline-avatar"><?= e(strtoupper(substr($c->firstName, 0, 1) . substr($c->lastName, 0, 1))) ?></div>
                                            <div class="ra-pipeline-card-info">
                                                <a href="/candidates/<?= e($c->code) ?>" class="ra-pipeline-card-name"><?= e($c->fullName()) ?></a>
                                                <div class="ra-pipeline-card-sub"><code style="font-size:.7rem;"><?= e($c->code) ?></code></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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

        <?= $activityHtml ?>

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
            <?php if ($user->can('recruit.vacancies.edit')): ?>
                <a href="<?= e($delBase) ?>/edit" class="ra-dropdown-item mb-1">
                    <i class="bi bi-pencil"></i><span>Edit vacancy</span>
                </a>
            <?php endif; ?>
            <a href="#" class="ra-dropdown-item disabled" onclick="return false;">
                <i class="bi bi-people"></i>
                <span>Add candidate</span>
            </a>
            <a href="#" class="ra-dropdown-item disabled" onclick="return false;">
                <i class="bi bi-diagram-3"></i>
                <span>View applications</span>
            </a>
        </div>
    </div>
</div>

<?= view_partial('partials/deletion-modal') ?>
<script src="<?= asset('js/deletion-modal.js') ?>?v=2"></script>
