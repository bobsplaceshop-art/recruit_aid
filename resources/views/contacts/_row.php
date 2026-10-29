<?php
/** @var array<string, mixed> $row */
$c = \CorporateAid\Recruit\Models\Contact::fromRow($row);
$roleSlugs = (string) ($row['role_slugs'] ?? '');
$c->roles = $roleSlugs === '' ? [] : explode(',', $roleSlugs);
$orgCode = (string) ($row['organisation_code'] ?? '');
$orgName = (string) ($row['organisation_name'] ?? '');
$user = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Auth::class)->user();
?>
<tr>
    <td>
        <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>" class="text-decoration-none">
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
        <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>" class="ra-row-actions" title="View">
            <i class="bi bi-eye"></i>
        </a>
        <?php if ($user !== null && $user->can('recruit.contacts.edit')): ?>
            <a href="/organisations/<?= e($orgCode) ?>/contacts/<?= e($c->code) ?>/edit" class="ra-row-actions" title="Edit">
                <i class="bi bi-pencil"></i>
            </a>
        <?php endif; ?>
    </td>
</tr>
