<?php
/** @var array<string, mixed> $row */
/** @var \CorporateAid\Recruit\Models\Organisation $org */
$org = \CorporateAid\Recruit\Models\Organisation::fromRow($row);
$user = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Auth::class)->user();
?>
<tr>
    <td>
        <a href="/organisations/<?= e($org->code) ?>" class="text-decoration-none">
            <code style="font-size:.85rem;"><?= e($org->code) ?></code>
        </a>
    </td>
    <td>
        <a href="/organisations/<?= e($org->code) ?>" class="text-decoration-none">
            <strong><?= e($org->name) ?></strong>
        </a>
        <?php if ($org->legalName): ?>
            <div class="muted small"><?= e($org->legalName) ?></div>
        <?php endif; ?>
    </td>
    <td class="muted small"><?= e($org->industry ?? '—') ?></td>
    <td class="muted small"><?= e($org->countryCode ?? '—') ?></td>
    <td>
        <span class="<?= $org->statusBadgeClass() ?>"><?= e($org->statusLabel()) ?></span>
    </td>
    <td class="muted small"><?= e($org->updatedAt ?? '—') ?></td>
    <td>
        <div class="d-flex gap-1">
            <a href="/organisations/<?= e($org->code) ?>" class="ra-row-actions" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <?php if ($user !== null && $user->can('recruit.organisations.edit')): ?>
                <a href="/organisations/<?= e($org->code) ?>/edit" class="ra-row-actions" title="Edit">
                    <i class="bi bi-pencil"></i>
                </a>
            <?php endif; ?>
        </div>
    </td>
</tr>
