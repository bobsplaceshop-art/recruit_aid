<?php
/** @var array $tenant */
use CorporateAid\Core\Row;

$id = (int) Row::int($tenant, 'id');
$status = Row::str($tenant, 'status');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h1><?= htmlspecialchars(Row::str($tenant, 'name'), ENT_QUOTES) ?></h1>
    <div style="display:flex;gap:0.5rem;">
        <a href="/cas/tenants/<?= $id ?>/edit" class="ra-btn ra-btn-outline">Edit</a>
        <a href="/cas/tenants" class="ra-btn ra-btn-outline">Back to list</a>
    </div>
</div>

<table style="width:100%;border-collapse:collapse;">
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;width:200px;">Code</th>
        <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($tenant, 'code'), ENT_QUOTES) ?></td>
    </tr>
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;">Status</th>
        <td style="padding:0.5rem;"><?= htmlspecialchars($status, ENT_QUOTES) ?></td>
    </tr>
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;">Created</th>
        <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($tenant, 'created_at'), ENT_QUOTES) ?></td>
    </tr>
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;">Primary color</th>
        <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($tenant, 'primary_color'), ENT_QUOTES) ?></td>
    </tr>
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;">Secondary color</th>
        <td style="padding:0.5rem;"><?= htmlspecialchars(Row::str($tenant, 'secondary_color'), ENT_QUOTES) ?></td>
    </tr>
    <?php if (Row::nullableStr($tenant, 'login_message') !== null): ?>
    <tr style="border-bottom:1px solid #eee;">
        <th style="text-align:left;padding:0.5rem;">Login message</th>
        <td style="padding:0.5rem;"><?= nl2br(htmlspecialchars(Row::str($tenant, 'login_message'), ENT_QUOTES)) ?></td>
    </tr>
    <?php endif; ?>
</table>

<h2 style="margin-top:2rem;">Status actions</h2>

<?php if ($status === 'active'): ?>
    <form method="post" action="/cas/tenants/<?= $id ?>/suspend" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" onclick="return confirm('Suspend this tenant? Users will lose access.')">Suspend</button>
    </form>
    <form method="post" action="/cas/tenants/<?= $id ?>/archive" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" onclick="return confirm('Archive this tenant?')">Archive</button>
    </form>
<?php elseif ($status === 'suspended'): ?>
    <form method="post" action="/cas/tenants/<?= $id ?>/archive" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" onclick="return confirm('Archive this tenant?')">Archive</button>
    </form>
    <form method="post" action="/cas/tenants/<?= $id ?>/restore" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit">Restore to active</button>
    </form>
<?php elseif ($status === 'archived'): ?>
    <form method="post" action="/cas/tenants/<?= $id ?>/restore" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" onclick="return confirm('Restore this tenant to active?')">Restore</button>
    </form>
<?php endif; ?>
