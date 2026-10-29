<?php
/**
 * Organisation detail page.
 *
 * $organisation — Organisation
 *
 * Tabs: Overview (active), Contacts, Engagements, Documents (all placeholders).
 */
use CorporateAid\Core\App;

$renderer = App::instance()
    ->container()
    ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

$customFieldsHtml = $renderer->renderDetail('organisation', $organisation->id);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · Organisations · <code><?= e($organisation->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($organisation->name) ?></h1>
            <div class="d-flex gap-2 align-items-center mt-2">
                <span class="<?= $organisation->statusBadgeClass() ?>">
                    <?= e($organisation->statusLabel()) ?>
                </span>
                <?php if ($organisation->legalName): ?>
                    <span class="muted small">· <?= e($organisation->legalName) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/organisations" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <?php if ($user->can('recruit.organisations.edit')): ?>
                <a href="/organisations/<?= e($organisation->code) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i>
                    <span>Edit</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.organisations.delete')): ?>
                <form method="POST" action="/organisations/<?= e($organisation->code) ?>/archive" style="margin:0;"
                      onsubmit="return confirm('Archive this organisation? It will disappear from the list but can be restored.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline">
                        <i class="bi bi-archive"></i>
                        <span>Archive</span>
                    </button>
                </form>
                <button type="button" class="ra-btn ra-btn-outline"
                        style="color:var(--ra-error-fg);border-color:var(--ra-error-bd);"
                        onclick="RA_DeletionModal.open({
                            entityLabel: '<?= e(addslashes($organisation->name)) ?> (<?= e($organisation->code) ?>)',
                            infoUrl: '/organisations/<?= e($organisation->code) ?>/delete-info',
                            submitUrl: '/organisations/<?= e($organisation->code) ?>/delete',
                            csrfToken: '<?= e(csrf_token()) ?>'
                        })">
                    <i class="bi bi-trash"></i>
                    <span>Delete</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="d-flex gap-1 mb-3 border-bottom" style="border-color:var(--ra-border);">
    <a href="/organisations/<?= e($organisation->code) ?>" class="ra-tab is-active">
        Overview
    </a>
    <a href="/organisations/<?= e($organisation->code) ?>/contacts" class="ra-tab">
        Contacts
    </a>
    <a href="/engagements?q=<?= e($organisation->name) ?>" class="ra-tab">
        Engagements
    </a>
    <a href="/vacancies?q=<?= urlencode($organisation->name) ?>" class="ra-tab">
        Vacancies
    </a>
    <a href="#" class="ra-tab disabled" onclick="return false;">
        Documents <span class="muted small ms-1">(soon)</span>
    </a>
</div>

<div class="row g-3">
    <!-- Left column: main info -->
    <div class="col-lg-8">
        <div class="ra-card">
            <h2 class="h5 mb-3">Basic information</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Name</div>
                    <div><?= e($organisation->name) ?></div>
                </div>

                <?php if ($organisation->legalName): ?>
                <div class="col-md-6">
                    <div class="muted small">Legal name</div>
                    <div><?= e($organisation->legalName) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->industry): ?>
                <div class="col-md-6">
                    <div class="muted small">Industry</div>
                    <div><?= e($organisation->industry) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->website): ?>
                <div class="col-md-6">
                    <div class="muted small">Website</div>
                    <div>
                        <a href="<?= e($organisation->website) ?>" target="_blank" rel="noopener noreferrer">
                            <?= e($organisation->website) ?>
                            <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.7rem;"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->countryCode): ?>
                <div class="col-md-6">
                    <div class="muted small">Primary country</div>
                    <div><?= e($organisation->countryCode) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($organisation->countriesOperation)): ?>
                <div class="col-md-6">
                    <div class="muted small">Countries of operation</div>
                    <div>
                        <?php foreach ($organisation->countriesOperation as $c): ?>
                            <span class="ra-badge me-1"><?= e($c) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->phone): ?>
                <div class="col-md-6">
                    <div class="muted small">Phone</div>
                    <div><?= e($organisation->phone) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->email): ?>
                <div class="col-md-6">
                    <div class="muted small">Email</div>
                    <div>
                        <a href="mailto:<?= e($organisation->email) ?>"><?= e($organisation->email) ?></a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($organisation->address): ?>
                <div class="col-12">
                    <div class="muted small">Address</div>
                    <div style="white-space:pre-line;"><?= e($organisation->address) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($customFieldsHtml): ?>
            <?= $customFieldsHtml ?>
        <?php endif; ?>

        <?php if ($organisation->notes): ?>
        <div class="ra-card mt-3">
            <h2 class="h5 mb-3">Notes</h2>
            <div style="white-space:pre-line;"><?= e($organisation->notes) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column: metadata sidebar -->
    <div class="col-lg-4">
        <div class="ra-card">
            <h2 class="h6 mb-3 muted">Record info</h2>

            <div class="mb-2">
                <div class="ra-section-label">Code</div>
                <div><code><?= e($organisation->code) ?></code></div>
            </div>

            <div class="mb-2">
                <div class="ra-section-label">Created</div>
                <div class="small"><?= e($organisation->createdAt ?? '—') ?></div>
            </div>

            <div class="mb-0">
                <div class="ra-section-label">Last updated</div>
                <div class="small"><?= e($organisation->updatedAt ?? '—') ?></div>
            </div>
        </div>

        <div class="ra-card mt-3">
            <h2 class="h6 mb-3 muted">Quick actions</h2>

            <?php if ($user->can('recruit.organisations.edit')): ?>
                <a href="/organisations/<?= e($organisation->code) ?>/edit" class="ra-dropdown-item mb-1">
                    <i class="bi bi-pencil"></i>
                    <span>Edit details</span>
                </a>
            <?php endif; ?>

            <?php if ($user->can('recruit.contacts.create')): ?>
                <a href="/organisations/<?= e($organisation->code) ?>/contacts/create" class="ra-dropdown-item mb-1">
                    <i class="bi bi-person-plus"></i>
                    <span>Add contact</span>
                </a>
            <?php endif; ?>

            <?php if ($user->can('recruit.engagements.create')): ?>
                <a href="/engagements/create?organisation=<?= e($organisation->code) ?>" class="ra-dropdown-item mb-1">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Create engagement</span>
                </a>
            <?php endif; ?>

            <a href="#" class="ra-dropdown-item disabled" onclick="return false;">
                <i class="bi bi-briefcase"></i>
                <span>Add vacancy <span class="muted small">(soon)</span></span>
            </a>
        </div>
    </div>
</div>

<?= view_partial('partials/deletion-modal') ?>
<script src="<?= asset('js/deletion-modal.js') ?>?v=2"></script>
