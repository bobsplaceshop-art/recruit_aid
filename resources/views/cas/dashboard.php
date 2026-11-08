<?php /** @var array $status_totals @var array $active_tenants @var int $effective_tenant_id @var int $home_tenant_id */ ?>
<h1>CAS Dashboard</h1>

<p>Home tenant: <?= (int) $home_tenant_id ?> · Effective tenant: <?= (int) $effective_tenant_id ?></p>

<h2>Tenant Summary</h2>
<ul>
    <li>Active: <?= (int) ($status_totals['active'] ?? 0) ?></li>
    <li>Suspended: <?= (int) ($status_totals['suspended'] ?? 0) ?></li>
    <li>Archived: <?= (int) ($status_totals['archived'] ?? 0) ?></li>
</ul>

<h2>Active Tenants</h2>
<ul>
    <?php foreach ($active_tenants as $t): ?>
        <li>
            <?= htmlspecialchars(\CorporateAid\Core\Row::str($t, 'name'), ENT_QUOTES) ?>
            (#<?= (int) \CorporateAid\Core\Row::int($t, 'id') ?>)
            <form method="post" action="/cas/context/switch" style="display:inline">
                <input type="hidden" name="tenant_id" value="<?= (int) \CorporateAid\Core\Row::int($t, 'id') ?>">
                <button type="submit">Act as this tenant</button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>

<form method="post" action="/cas/context/reset">
    <button type="submit">Reset to home tenant</button>
</form>
