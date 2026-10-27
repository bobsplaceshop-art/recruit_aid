<div class="ra-page-header">
    <div>
        <div class="ra-breadcrumbs">
            <a href="/dashboard">Home</a>
            <span>/</span>
            <span>Administration</span>
            <span>/</span>
            <span>Users</span>
        </div>
        <h1 class="ra-page-title">Users</h1>
        <p class="ra-page-sub">All accounts with access to Recruit Aid.</p>
    </div>
</div>

<div class="ra-table-wrap">
    <table class="ra-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Roles</th>
                <th>Last login</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" class="text-center muted" style="padding:2.5rem;">
                    No users yet.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?php
                        $statusClass = match ($u['status']) {
                            'active'    => 'ra-badge ra-badge-success',
                            'pending'   => 'ra-badge ra-badge-warn',
                            'suspended', 'disabled' => 'ra-badge ra-badge-danger',
                            default     => 'ra-badge',
                        };
                        ?>
                        <span class="<?= $statusClass ?>"><?= e(ucfirst($u['status'])) ?></span>
                    </td>
                    <td>
                        <?php if (empty($u['roles'])): ?>
                            <span class="muted small">No roles</span>
                        <?php else: ?>
                            <?php foreach ($u['roles'] as $slug): ?>
                                <span class="ra-badge me-1"><?= e($slug) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td class="muted small"><?= e($u['last_login_at'] ?? 'Never') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
