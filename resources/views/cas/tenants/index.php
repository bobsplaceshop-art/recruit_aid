<?php
/** @var array $tenants @var array $counts @var ?string $current_status */
use CorporateAid\Core\Row;
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h1>Tenants</h1>
    <a href="/cas/tenants/create" class="ra-btn ra-btn-primary">Create tenant</a>
</div>

<p>
    Active: <?= (int) $counts['active'] ?> ·
    Suspended: <?= (int) $counts['suspended'] ?> ·
    Archived: <?= (int) $counts['archived'] ?>
</p>

<p>
    <a href="/cas/tenants">All</a>
    | <a href="/cas/tenants?status=active">Active only</a>
    | <a href="/cas/tenants?status=suspended">Suspended only</a>
    | <a href="/cas/tenants?status=archived">Archived only</a>
</p>

<?php if (empty($tenants)): ?>
    <p>No tenants found.</p>
<?php else: ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid #ccc;">
                <th style="padding:0.5rem;">Code</th>
                <th style="padding:0.5rem;">Name</th>
                <th style="padding:0.5rem;">Status</th>
                <th style="padding:0.5rem;">Created</th>
                <th style="padding:0.5rem;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenants as $t): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($t, 'code'), ENT_QUOTES) ?></td>
                    <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($t, 'name'), ENT_QUOTES) ?></td>
                    <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($t, 'status'), ENT_QUOTES) ?></td>
                    <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($t, 'created_at'), ENT_QUOTES) ?></td>
                    <td style="padding:0.5rem;">
                        <a href="/cas/tenants/<?= (int) Row::int($t, 'id') ?>">View</a>
                        | <a href="/cas/tenants/<?= (int) Row::int($t, 'id') ?>/edit">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
