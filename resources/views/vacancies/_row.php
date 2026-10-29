<?php
/** @var array<string, mixed> $row */
$v = \CorporateAid\Recruit\Models\Vacancy::fromRow($row);
$orgName = (string) ($row['organisation_name'] ?? '');
$orgCode = (string) ($row['organisation_code'] ?? '');
$user = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Auth::class)->user();
?>
<tr>
    <td><a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none"><code><?= e($v->code) ?></code></a></td>
    <td>
        <a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none">
            <strong><?= e($v->title) ?></strong>
        </a>
        <?php if ($v->department): ?>
            <div class="muted small"><?= e($v->department) ?></div>
        <?php endif; ?>
    </td>
    <td class="muted small">
        <?php if ($orgCode !== ''): ?>
            <a href="/organisations/<?= e($orgCode) ?>"><?= e($orgName) ?></a>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td class="muted small">
        <?= e($v->city ?? '—') ?>
        <?php if ($v->countryCode): ?>, <?= e($v->countryCode) ?><?php endif; ?>
        <div class="muted" style="font-size:.72rem;"><?= e($v->workModeLabel()) ?></div>
    </td>
    <td class="muted small"><?= e($v->employmentTypeLabel()) ?></td>
    <td><span class="<?= $v->statusBadgeClass() ?>"><?= e($v->statusLabel()) ?></span></td>
    <td>
        <div class="d-flex gap-1">
            <a href="/vacancies/<?= e($v->code) ?>" class="ra-row-actions" title="View"><i class="bi bi-eye"></i></a>
            <?php if ($user !== null && $user->can('recruit.vacancies.edit')): ?>
                <a href="/vacancies/<?= e($v->code) ?>/edit" class="ra-row-actions" title="Edit"><i class="bi bi-pencil"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
