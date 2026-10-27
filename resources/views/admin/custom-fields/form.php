<?php
/**
 * Custom field create/edit form.
 *
 * $field      — existing CustomField or null
 * $inputTypes — array of ['key' => 'text', 'label' => 'Text (single line)']
 * $errors     — array of error messages
 * $old        — array of previously-submitted values (used if validation failed)
 * $pageScripts — tells layout to load custom-fields.js
 */
$pageScripts = ['js/custom-fields.js'];

$isEdit = $field !== null;

$get = function (string $key, $default = '') use ($field, $old) {
    if (!empty($old) && array_key_exists($key, $old)) return $old[$key];
    if ($field !== null) {
        return match ($key) {
            'label'         => $field->label,
            'input_type'    => $field->inputType,
            'is_required'   => $field->isRequired,
            'help_text'     => $field->helpText ?? '',
            'default_value' => $field->defaultValue ?? '',
            'options'       => $field->options,
            'constraints'   => $field->constraints,
            default         => $default,
        };
    }
    return $default;
};

$label        = (string) $get('label');
$inputType    = (string) $get('input_type');
$isRequired   = (bool) $get('is_required', false);
$helpText     = (string) $get('help_text');
$defaultValue = (string) $get('default_value');
$options      = (array) $get('options', []);
$constraints  = (array) $get('constraints', []);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Custom Fields · <?= e($entityLabel) ?></div>
            <h1 class="ra-page-band-title"><?= $isEdit ? 'Edit Field' : 'New Field' ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/custom-fields/<?= e($entityKey) ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <button type="submit" form="cf-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span><?= $isEdit ? 'Save changes' : 'Create field' ?></span>
            </button>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="cf-form" method="POST"
      action="/admin/custom-fields/<?= e($entityKey) ?><?= $isEdit ? '/' . (int) $field->id : '' ?>"
      novalidate>
    <?= csrf_field() ?>

    <div class="ra-card">
        <h2 class="h5 mb-3">Field definition</h2>

        <div class="mb-3">
            <label class="ra-label" for="cf-label">Label <span style="color:var(--ra-error-fg)">*</span></label>
            <input type="text" id="cf-label" name="label" required maxlength="190"
                   class="ra-input" value="<?= e($label) ?>"
                   placeholder="e.g. Rwanda work permit status">
            <div class="muted small mt-1">This is what users see on the form.</div>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="cf-input-type">Input type <span style="color:var(--ra-error-fg)">*</span></label>
            <?php if ($isEdit): ?>
                <!-- Disabled selects don't submit. Use a hidden field to carry the value. -->
                <input type="hidden" name="input_type" value="<?= e($inputType) ?>">
                <input type="text" class="ra-input"
                       value="<?= e($inputTypes[$inputType]['label'] ?? $inputType) ?>" disabled>
                <div class="muted small mt-1">
                    <i class="bi bi-info-circle"></i>
                    Input type cannot be changed after creation (existing values would be invalid).
                </div>
            <?php else: ?>
                <select id="cf-input-type" name="input_type" required class="ra-input ra-select" style="width:100%;">
                    <option value="">— Choose a type —</option>
                    <?php foreach ($inputTypes as $type): ?>
                        <option value="<?= e($type['key']) ?>" <?= $inputType === $type['key'] ? 'selected' : '' ?>>
                            <?= e($type['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;">
                <input type="checkbox" name="is_required" value="1" <?= $isRequired ? 'checked' : '' ?>>
                <span class="ra-label" style="margin:0;">Required field</span>
            </label>
            <div class="muted small mt-1 ms-4">If ticked, users must fill this in before saving.</div>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="cf-help-text">Help text</label>
            <input type="text" id="cf-help-text" name="help_text" maxlength="500"
                   class="ra-input" value="<?= e($helpText) ?>"
                   placeholder="Optional. Appears below the field.">
        </div>

        <?php if ($isEdit): ?>
            <input type="hidden" name="sort_order" value="<?= (int) $field->sortOrder ?>">
        <?php endif; ?>
    </div>

    <!-- Dynamic section: type-specific configuration -->
    <div class="ra-card mt-3" id="cf-config-section" style="display:none;">
        <h2 class="h5 mb-3">Type-specific settings</h2>
        <div id="cf-config-fields"></div>
    </div>

    <!-- Dynamic section: options -->
    <div class="ra-card mt-3" id="cf-options-section" style="display:none;">
        <h2 class="h5 mb-3">Options</h2>
        <p class="muted small mb-3">
            List the choices the user can pick from. Each option on its own row.
        </p>

        <div id="cf-options-list">
            <?php
                $optionRows = $options ?: [''];
                foreach ($optionRows as $opt):
            ?>
                <div class="cf-option-row d-flex gap-2 mb-2">
                    <input type="text" name="options[]" class="ra-input" value="<?= e((string) $opt) ?>"
                           placeholder="Option text">
                    <button type="button" class="ra-btn ra-btn-outline cf-remove-option" title="Remove">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="cf-add-option" class="ra-btn ra-btn-outline ra-btn-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Add option</span>
        </button>
    </div>

    <!-- Live preview -->
    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Preview</h2>
        <p class="muted small mb-3">This is how the field will look on the form.</p>
        <div id="cf-preview" style="border:1px dashed var(--ra-border);border-radius:10px;padding:1rem;">
            <div class="muted small">Choose an input type to see the preview.</div>
        </div>
    </div>
</form>
