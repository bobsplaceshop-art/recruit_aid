<?php /** @var array $delegations */ ?>
<h1>Support Delegations</h1>

<p><a href="/cas/delegations/grant">Grant new delegation</a></p>

<table>
    <thead>
        <tr>
            <th>ID</th><th>User</th><th>Tenant</th><th>Module</th>
            <th>Scope</th><th>State</th><th>Expires</th><th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($delegations as $d): ?>
            <tr>
                <td><?= (int) \CorporateAid\Core\Row::int($d, 'id') ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'user_name'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'tenant_name'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'module_key'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'scope'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'revocation_state'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars(\CorporateAid\Core\Row::str($d, 'expires_at'), ENT_QUOTES) ?></td>
                <td>
                    <form method="post" action="/cas/delegations/<?= (int) \CorporateAid\Core\Row::int($d, 'id') ?>/revoke" style="display:inline">
                        <button type="submit">Revoke</button>
                    </form>
                    <?php if (\CorporateAid\Core\Row::str($d, 'revocation_state') === 'revocation_pending'): ?>
                        <form method="post" action="/cas/delegations/<?= (int) \CorporateAid\Core\Row::int($d, 'id') ?>/cancel" style="display:inline">
                            <button type="submit">Cancel pending revocation</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
