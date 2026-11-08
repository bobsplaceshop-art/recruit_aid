<?php /** @var array $users @var array $tenants */ ?>
<h1>Grant Support Delegation</h1>

<form method="post" action="/cas/delegations/grant">
    <label>User
        <select name="user_id" required>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) \CorporateAid\Core\Row::int($u, 'id') ?>">
                    <?= htmlspecialchars(\CorporateAid\Core\Row::str($u, 'name'), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Tenant
        <select name="tenant_id" required>
            <?php foreach ($tenants as $t): ?>
                <option value="<?= (int) \CorporateAid\Core\Row::int($t, 'id') ?>">
                    <?= htmlspecialchars(\CorporateAid\Core\Row::str($t, 'name'), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Module
        <select name="module_key">
            <option value="recruit">recruit</option>
        </select>
    </label>

    <label>Scope
        <select name="scope">
            <option value="read_only">read_only</option>
            <option value="read_write">read_write</option>
            <option value="full">full</option>
        </select>
    </label>

    <label>Expires at <input type="datetime-local" name="expires_at" required></label>
    <label>Reason <textarea name="reason"></textarea></label>
    <label>Denied permissions (comma-separated) <input type="text" name="denied_permissions"></label>

    <button type="submit">Grant</button>
</form>
