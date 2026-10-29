<?php
/** @var array<string, mixed> $row */
$e = \CorporateAid\Recruit\Models\Engagement::fromRow($row);
$orgName = (string) ($row['organisation_name'] ?? '');
$orgCode = (string) ($row['organisation_code'] ?? '');
$user = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Auth::class)->user();
?>
<tr>
    <td><a href="/engagements/<?= e($e->code) ?>" class="text-decoration-none"><code><?= e($e->code) ?></code></a></td>
    <td><a href="/engagements/<?= e($e->code) ?>" class="text-decoration-none"><strong><?= e($e->name) ?></strong></a></td>
    <td class="muted small">
        <?php if ($orgCode !== ''): ?>
            <a href="/organisations/<?= e($orgCode) ?>"><?= e($orgName) ?></a>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td class="muted small"><?= e($e->serviceTypeLabel()) ?></td>
    <td class="muted small">
        <?= e($e->startDate ?? '—') ?>
        <?php if ($e->endDate): ?> → <?= e($e->endDate) ?><?php endif; ?>
    </td>
    <td><span class="<?= $e->statusBadgeClass() ?>"><?= e($e->statusLabel()) ?></span></td>
    <td>
        <div class="d-flex gap-1">
            <a href="/engagements/<?= e($e->code) ?>" class="ra-row-actions" title="View"><i class="bi bi-eye"></i></a>
            <?php if ($user !== null && $user->can('recruit.engagements.edit')): ?>
                <a href="/engagements/<?= e($e->code) ?>/edit" class="ra-row-actions" title="Edit"><i class="bi bi-pencil"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
