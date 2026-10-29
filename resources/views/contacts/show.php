<?php
/**
 * Contact detail page.
 *
 * $organisation — Organisation (parent)
 * $contact      — Contact
 */
use CorporateAid\Core\App;

$renderer = App::instance()
    ->container()
    ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

$customFieldsHtml = $renderer->renderDetail('contact', $contact->id);
$activityHtml = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Platform\Services\ActivityTimelineService::class)->render('contact', $contact->id);
$contactDeleteUrl = '/organisations/' . $organisation->code . '/contacts/' . $contact->code;

// Context-aware back URL
$returnTo = $returnTo ?? '';
$backUrl = ($returnTo === 'contacts') 
    ? '/contacts' 
    : '/organisations/' . $organisation->code . '/contacts';
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · <a href="/organisations/<?= e($organisation->code) ?>" class="text-decoration-none">
                    <?= e($organisation->name) ?>
                </a> · <code><?= e($contact->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($contact->fullName()) ?></h1>
            <?php if (!empty($contact->position) || !empty($contact->department)): ?>
                <p class="ra-page-sub">
                    <?= e($contact->position ?? '') ?>
                    <?php if (!empty($contact->position) && !empty($contact->department)): ?> · <?php endif; ?>
                    <?= e($contact->department ?? '') ?>
                </p>
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <?php foreach ($contact->roleLabels() as $label): ?>
                    <span class="ra-badge"><?= e($label) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= e($backUrl) ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <?php if ($user->can('recruit.contacts.edit')): ?>
                <a href="<?= e($contactDeleteUrl) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i>
                    <span>Edit</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.contacts.delete')): ?>
                <button type="button" class="ra-btn ra-btn-outline"
                        style="color:var(--ra-error-fg);border-color:var(--ra-error-bd);"
                        onclick="RA_DeletionModal.open({
                            entityLabel: '<?= e(addslashes($contact->fullName())) ?> (<?= e($contact->code) ?>)',
                            infoUrl: '<?= e($contactDeleteUrl) ?>/delete-info',
                            submitUrl: '<?= e($contactDeleteUrl) ?>/delete',
                            csrfToken: '<?= e(csrf_token()) ?>'
                        })">
                    <i class="bi bi-trash"></i>
                    <span>Delete</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="ra-card">
            <h2 class="h5 mb-3">Contact details</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Email</div>
                    <div>
                        <?php if ($contact->email): ?>
                            <a href="mailto:<?= e($contact->email) ?>"><?= e($contact->email) ?></a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Phone</div>
                    <div><?= e($contact->phone ?? '—') ?></div>
                </div>
                <?php if ($contact->altPhone): ?>
                <div class="col-md-6">
                    <div class="muted small">Alternative phone</div>
                    <div><?= e($contact->altPhone) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact->countryCode): ?>
                <div class="col-md-6">
                    <div class="muted small">Country</div>
                    <div><?= e($contact->countryCode) ?></div>
                </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <div class="muted small">Preferred contact</div>
                    <div><?= e(ucfirst($contact->preferredCommunication)) ?></div>
                </div>
            </div>
        </div>

        <?php if ($customFieldsHtml): ?>
            <?= $customFieldsHtml ?>
        <?php endif; ?>

        <?= $activityHtml ?>

        <?php if ($contact->notes): ?>
            <div class="ra-card mt-3">
                <h2 class="h5 mb-3">Notes</h2>
                <div style="white-space:pre-line;"><?= e($contact->notes) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="ra-card">
            <h2 class="h6 mb-3 muted">Record info</h2>
            <div class="mb-2">
                <div class="ra-section-label">Code</div>
                <div><code><?= e($contact->code) ?></code></div>
            </div>
            <div class="mb-2">
                <div class="ra-section-label">Added</div>
                <div class="small"><?= e($contact->createdAt ?? '—') ?></div>
            </div>
            <div class="mb-0">
                <div class="ra-section-label">Last updated</div>
                <div class="small"><?= e($contact->updatedAt ?? '—') ?></div>
            </div>
        </div>

        <div class="ra-card mt-3">
            <h2 class="h6 mb-3 muted">Belongs to</h2>
            <a href="/organisations/<?= e($organisation->code) ?>" class="ra-dropdown-item">
                <i class="bi bi-buildings"></i>
                <span><?= e($organisation->name) ?></span>
            </a>
        </div>
    </div>
</div>

<?= view_partial('partials/deletion-modal') ?>
<script src="<?= asset('js/deletion-modal.js') ?>?v=2"></script>
