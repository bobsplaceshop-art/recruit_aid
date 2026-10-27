<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration</div>
            <h1 class="ra-page-band-title">Roles &amp; Permissions</h1>
        </div>
    </div>
</div>

<p class="muted mb-4">Manage what each type of user can do within Recruit Aid.</p>

<div class="ra-tiles">
    <a href="/admin/roles-perm/roles" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--clay">
            <i class="bi bi-diagram-3-fill"></i>
        </div>
        <div class="ra-tile-label">Roles</div>
    </a>

    <a href="/admin/roles-perm/permissions" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--sage">
            <i class="bi bi-shield-check"></i>
        </div>
        <div class="ra-tile-label">Permissions</div>
    </a>
</div>

<div class="ra-table-card">
    <div class="ra-table-title">Summary</div>
    <table class="ra-table">
        <tbody>
            <tr>
                <td style="width:220px;"><span class="ra-section-label">Total roles</span></td>
                <td><strong><?= e((string) $roleCount) ?></strong></td>
            </tr>
            <tr>
                <td><span class="ra-section-label">Total permissions</span></td>
                <td><strong><?= e((string) $permissionCount) ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>
