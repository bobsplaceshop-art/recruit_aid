<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Users</div>
            <h1 class="ra-page-band-title">Users</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/users/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Create User</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <table class="ra-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Roles</th>
                <th>Last login</th>
                <th style="width:100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="6" class="text-center muted" style="padding:2.5rem;">
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
                    <td>
                        <a href="/admin/users/<?= (int) $u['id'] ?>/edit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
