<?php
/** @var array<string, mixed> $row */
/** @var \CorporateAid\Recruit\Models\Candidate $c */
$c = \CorporateAid\Recruit\Models\Candidate::fromRow($row);
$user = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Auth::class)->user();
?>
<tr>
    <td>
        <a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none">
            <code style="font-size:.85rem;"><?= e($c->code) ?></code>
        </a>
    </td>
    <td>
        <a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none">
            <strong><?= e($c->fullName()) ?></strong>
        </a>
    </td>
    <td class="muted small"><?= e($c->email) ?></td>
    <td class="muted small">
        <?php if ($c->currentJobTitle): ?>
            <?= e($c->currentJobTitle) ?><?php if ($c->currentCompany): ?><span class="muted"> · <?= e($c->currentCompany) ?></span><?php endif; ?>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td class="muted small"><?= $c->seniority ? e($c->seniorityLabel()) : '—' ?></td>
    <td>
        <span class="<?= $c->statusBadgeClass() ?>"><?= e($c->statusLabel()) ?></span>
    </td>
    <td>
        <div class="d-flex gap-1">
            <a href="/candidates/<?= e($c->code) ?>" class="ra-row-actions" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <?php if ($user !== null && $user->can('recruit.candidates.edit')): ?>
                <a href="/candidates/<?= e($c->code) ?>/edit" class="ra-row-actions" title="Edit">
                    <i class="bi bi-pencil"></i>
                </a>
            <?php endif; ?>
        </div>
    </td>
</tr>
