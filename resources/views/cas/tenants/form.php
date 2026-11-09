<?php
/** @var ?array $tenant @var ?string $error @var array $old */
use CorporateAid\Core\Row;

$isEdit = $tenant !== null;
$action = $isEdit ? '/cas/tenants/' . (int) Row::int($tenant, 'id') : '/cas/tenants';
$field = function (string $name, string $default = '') use ($tenant, $old, $isEdit): string {
    if (!empty($old[$name])) {
        return (string) $old[$name];
    }
    if ($isEdit && $tenant !== null) {
        return Row::str($tenant, $name, $default);
    }
    return $default;
};
?>
<h1><?= $isEdit ? 'Edit Tenant' : 'Create Tenant' ?></h1>

<?php if (!empty($error)): ?>
    <div style="padding:0.75rem;background:#fee;border:1px solid #fcc;margin-bottom:1rem;">
        <?= htmlspecialchars((string) $error, ENT_QUOTES) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>">
    <?= csrf_field() ?>
    <div style="margin-bottom:1rem;">
        <label>Code <?= $isEdit ? '(immutable after creation)' : '' ?>
            <input type="text" name="code" value="<?= htmlspecialchars($field('code'), ENT_QUOTES) ?>"
                   <?= $isEdit ? 'readonly' : 'required' ?> maxlength="20" style="display:block;width:100%;">
        </label>
    </div>
    <div style="margin-bottom:1rem;">
        <label>Name
            <input type="text" name="name" value="<?= htmlspecialchars($field('name'), ENT_QUOTES) ?>"
                   required maxlength="255" style="display:block;width:100%;">
        </label>
    </div>
    <div style="margin-bottom:1rem;">
        <label>Primary color
            <input type="text" name="primary_color" value="<?= htmlspecialchars($field('primary_color', '#0d6efd'), ENT_QUOTES) ?>"
                   maxlength="7" style="display:block;width:100%;">
        </label>
    </div>
    <div style="margin-bottom:1rem;">
        <label>Secondary color
            <input type="text" name="secondary_color" value="<?= htmlspecialchars($field('secondary_color', '#6c757d'), ENT_QUOTES) ?>"
                   maxlength="7" style="display:block;width:100%;">
        </label>
    </div>
    <div style="margin-bottom:1rem;">
        <label>Login message (optional)
            <textarea name="login_message" rows="3" style="display:block;width:100%;"><?= htmlspecialchars($field('login_message'), ENT_QUOTES) ?></textarea>
        </label>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <button type="submit"><?= $isEdit ? 'Save changes' : 'Create tenant' ?></button>
        <a href="<?= $isEdit ? '/cas/tenants/' . (int) Row::int($tenant, 'id') : '/cas/tenants' ?>">Cancel</a>
    </div>
</form>
