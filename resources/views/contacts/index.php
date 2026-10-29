<?php
/**
 * Contacts list for one organisation.
 *
 * $organisation — Organisation
 * $contacts     — Contact[]
 */
$total = count($contacts);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · Organisations · <code><?= e($organisation->code) ?></code> · Contacts
            </div>
            <h1 class="ra-page-band-title">Contacts</h1>
            <p class="ra-page-sub">
                People at <strong><?= e($organisation->name) ?></strong>
            </p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/organisations/<?= e($organisation->code) ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back to organisation</span>
            </a>
            <?php if ($user->can('recruit.contacts.create')): ?>
                <a href="/organisations/<?= e($organisation->code) ?>/contacts/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Contact</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label">
                <?= $total ?> contact<?= $total === 1 ? '' : 's' ?>
            </span>
        </div>
    </div>

    <?php if ($total === 0): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <div class="mb-3">No contacts yet for <?= e($organisation->name) ?>.</div>
            <?php if ($user->can('recruit.contacts.create')): ?>
                <a href="/organisations/<?= e($organisation->code) ?>/contacts/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add the first contact</span>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Roles</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($contacts as $c): ?>
                <tr>
                    <td>
                        <a href="/organisations/<?= e($organisation->code) ?>/contacts/<?= e($c->code) ?>" class="text-decoration-none">
                            <strong><?= e($c->fullName()) ?></strong>
                        </a>
                        <?php if ($c->department): ?>
                            <div class="muted small"><?= e($c->department) ?></div>
                        <?php endif; ?>
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
                        <div class="d-flex gap-1">
                            <a href="/organisations/<?= e($organisation->code) ?>/contacts/<?= e($c->code) ?>" class="ra-row-actions" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($user->can('recruit.contacts.edit')): ?>
                                <a href="/organisations/<?= e($organisation->code) ?>/contacts/<?= e($c->code) ?>/edit" class="ra-row-actions" title="Edit">
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
