<?php
/**
 * Engagement detail page.
 *
 * $engagement   — Engagement
 * $organisation — Organisation
 * $recruiters   — array of {user_id, role, name, email}
 * $capacity     — {capacity, used, available, exceeded}
 */
use CorporateAid\Core\App;

$renderer = App::instance()
    ->container()
    ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

$customFieldsHtml = $renderer->renderDetail('engagement', $engagement->id);
$activityHtml = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Platform\Services\ActivityTimelineService::class)->render('engagement', $engagement->id);

$delBase = '/engagements/' . $engagement->code;

// Usage percent for the capacity meter
$pct = $capacity['capacity'] > 0
    ? min(100, (int) round(($capacity['used'] / $capacity['capacity']) * 100))
    : 0;
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · <?php if ($organisation): ?>
                    <a href="/organisations/<?= e($organisation->code) ?>" class="text-decoration-none">
                        <?= e($organisation->name) ?>
                    </a>
                <?php else: ?>Unknown client<?php endif; ?>
                · <code><?= e($engagement->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($engagement->name) ?></h1>
            <div class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                <span class="<?= $engagement->statusBadgeClass() ?>"><?= e($engagement->statusLabel()) ?></span>
                <span class="muted small">· <?= e($engagement->serviceTypeLabel()) ?></span>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/engagements" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i><span>Back</span>
            </a>
            <?php if ($user->can('recruit.engagements.edit')): ?>
                <a href="<?= e($delBase) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i><span>Edit</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.engagements.delete')): ?>
                <button type="button" class="ra-btn ra-btn-outline"
                        style="color:var(--ra-error-fg);border-color:var(--ra-error-bd);"
                        onclick="RA_DeletionModal.open({
                            entityLabel: '<?= e(addslashes($engagement->name)) ?> (<?= e($engagement->code) ?>)',
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
                <div class="col-md-6">
                    <div class="muted small">Service</div>
                    <div><?= e($engagement->serviceTypeLabel()) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Start</div>
                    <div><?= e($engagement->startDate ?? '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">End</div>
                    <div><?= e($engagement->endDate ?? '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Reporting</div>
                    <div><?= e(ucfirst($engagement->reportingFrequency)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Countries</div>
                    <div>
                        <?php if (empty($engagement->countries)): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <?php foreach ($engagement->countries as $c): ?>
                                <span class="ra-badge me-1"><?= e($c) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($engagement->scopeDescription): ?>
                    <div class="col-12">
                        <div class="muted small">Scope</div>
                        <div style="white-space:pre-line;"><?= e($engagement->scopeDescription) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($engagement->stagesIncluded)): ?>
                    <div class="col-12">
                        <div class="muted small">Stages included</div>
                        <div>
                            <?php foreach ($engagement->stagesIncluded as $s): ?>
                                <span class="ra-badge me-1"><?= e($s) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Capacity meter -->
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Capacity</h2>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <span style="font-size:1.5rem;font-weight:700;color:var(--ra-text-strong);"><?= (int) $capacity['used'] ?></span>
                    <span class="muted"> / <?= (int) $capacity['capacity'] ?> active roles</span>
                </div>
                <div>
                    <?php if ($capacity['exceeded']): ?>
                        <span class="ra-badge ra-badge-danger">At capacity</span>
                    <?php else: ?>
                        <span class="ra-badge ra-badge-success"><?= (int) $capacity['available'] ?> slot<?= $capacity['available'] === 1 ? '' : 's' ?> available</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="background:var(--ra-surface-3);border-radius:999px;height:12px;overflow:hidden;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $capacity['exceeded'] ? 'var(--ra-error-fg)' : 'var(--ra-primary)' ?>;transition:width .3s ease;"></div>
            </div>
            <div class="muted small mt-2">
                Tracks active vacancies. Full count will activate once the Vacancies module ships.
            </div>
        </div>

        <?php if ($customFieldsHtml): ?><?= $customFieldsHtml ?><?php endif; ?>

        <?= $activityHtml ?>

        <?php if ($engagement->notes): ?>
            <div class="ra-card mt-3">
                <h2 class="h5 mb-3">Notes</h2>
                <div style="white-space:pre-line;"><?= e($engagement->notes) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ra-card">
            <h2 class="h6 mb-3 muted">Record info</h2>
            <div class="mb-2">
                <div class="ra-section-label">Code</div>
                <div><code><?= e($engagement->code) ?></code></div>
            </div>
            <div class="mb-2">
                <div class="ra-section-label">Created</div>
                <div class="small"><?= e($engagement->createdAt ?? '—') ?></div>
            </div>
            <div class="mb-0">
                <div class="ra-section-label">Last updated</div>
                <div class="small"><?= e($engagement->updatedAt ?? '—') ?></div>
            </div>
        </div>

        <!-- Recruiters -->
        <div class="ra-card mt-3">
            <h2 class="h6 mb-3 muted">Assigned recruiters</h2>
            <?php if (empty($recruiters)): ?>
                <div class="muted small mb-3">None assigned yet.</div>
            <?php else: ?>
                <div class="mb-3">
                    <?php foreach ($recruiters as $r): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="small"><?= e($r['name']) ?></div>
                                <div class="muted" style="font-size:.72rem;"><?= e($r['role']) ?></div>
                            </div>
                            <?php if ($user->can('recruit.engagements.edit')): ?>
                                <form method="POST" action="<?= e($delBase) ?>/recruiters/unassign" style="margin:0;"
                                      onsubmit="return confirm('Unassign this recruiter?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                                    <button type="submit" class="ra-row-actions" title="Unassign">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($user->can('recruit.engagements.edit')): ?>
                <form method="POST" action="<?= e($delBase) ?>/recruiters/assign" style="margin:0;">
                    <?= csrf_field() ?>
                    <?php
                        $assignedIds = array_map('intval', array_column($recruiters, 'user_id'));
                        $availableUsers = array_filter($staffUsers, fn ($u) => !in_array((int) $u['id'], $assignedIds, true));
                    ?>
                    <select name="user_id" class="ra-input ra-select mb-2" style="width:100%;" required>
                        <option value="">— Choose a user —</option>
                        <?php foreach ($availableUsers as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?><?php if (!empty($u['role_slugs'])): ?> (<?= e(str_replace('_', ' ', $u['role_slugs'])) ?>)<?php endif; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($availableUsers)): ?>
                        <div class="muted small mb-2">All users are already assigned.</div>
                    <?php endif; ?>
                    <select name="role" class="ra-input ra-select mb-2" style="width:100%;">
                        <option value="lead">Lead</option>
                        <option value="recruiter" selected>Recruiter</option>
                        <option value="researcher">Researcher</option>
                    </select>
                    <button type="submit" class="ra-btn ra-btn-primary ra-btn-sm" style="width:100%;">
                        <i class="bi bi-plus-lg"></i><span>Assign</span>
                    </button>
                </form>
            <?php endif
; ?>
        </div>

        <div class="ra-card mt-3">
            <h2 class="h6 mb-3 muted">Quick actions</h2>
            <?php if ($user->can('recruit.vacancies.create')): ?>
                <a href="/vacancies/create?engagement=<?= e($engagement->code) ?>" class="ra-dropdown-item mb-1">
                    <i class="bi bi-briefcase"></i>
                    <span>Add vacancy</span>
                </a>
            <?php endif; ?>
            <a href="<?= e($delBase) ?>/edit" class="ra-dropdown-item mb-1">
                <i class="bi bi-pencil"></i>
                <span>Edit engagement</span>
            </a>
        </div>
    </div>
</div>

<?= view_partial('partials/deletion-modal') ?>
<script src="<?= asset('js/deletion-modal.js') ?>?v=2"></script>
