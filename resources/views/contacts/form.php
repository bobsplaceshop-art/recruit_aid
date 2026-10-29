<?php
/**
 * Contact create/edit form.
 *
 * $organisation — Organisation (parent)
 * $contact      — Contact|null (null = create)
 * $old          — submitted values on validation failure
 * $errors       — flat array of error messages
 */
$isEdit = $contact !== null;

$get = function (string $key, $default = '') use ($contact, $old) {
    if (!empty($old) && array_key_exists($key, $old)) return $old[$key];
    if ($contact !== null) {
        return match ($key) {
            'first_name'              => $contact->firstName,
            'last_name'               => $contact->lastName,
            'position'                => $contact->position ?? '',
            'department'              => $contact->department ?? '',
            'email'                   => $contact->email ?? '',
            'phone'                   => $contact->phone ?? '',
            'alt_phone'               => $contact->altPhone ?? '',
            'country_code'            => $contact->countryCode ?? '',
            'preferred_communication' => $contact->preferredCommunication,
            'is_primary'              => $contact->isPrimary,
            'is_hiring_manager'       => $contact->isHiringManager,
            'is_finance'              => $contact->isFinance,
            'is_hr'                   => $contact->isHr,
            'is_decision_maker'       => $contact->isDecisionMaker,
            'notes'                   => $contact->notes ?? '',
            default                   => $default,
        };
    }
    return $default;
};

$firstName   = (string) $get('first_name');
$lastName    = (string) $get('last_name');
$position    = (string) $get('position');
$department  = (string) $get('department');
$email       = (string) $get('email');
$phone       = (string) $get('phone');
$altPhone    = (string) $get('alt_phone');
$countryCode = (string) $get('country_code');
$preferredComm = (string) $get('preferred_communication', 'email');
// Build current roles[] from submitted data (old input) or from the contact's
// pivot roles if hydrated, falling back to boolean flags for backward compat.
$currentRoles = [];
if (!empty($old) && isset($old['roles']) && is_array($old['roles'])) {
    $currentRoles = $old['roles'];
} elseif ($contact !== null && !empty($contact->roles)) {
    $currentRoles = $contact->roles;
} else {
    // Backward compat: build from booleans
    if ($contact !== null) {
        if ($contact->isPrimary) $currentRoles[] = 'is_primary';
        if ($contact->isHiringManager) $currentRoles[] = 'is_hiring_manager';
        if ($contact->isFinance) $currentRoles[] = 'is_finance';
        if ($contact->isHr) $currentRoles[] = 'is_hr';
        if ($contact->isDecisionMaker) $currentRoles[] = 'is_decision_maker';
    }
}

// Load all roles from lookups
$allRoles = [];
try {
    $lookups = \CorporateAid\Core\App::instance()
        ->container()
        ->get(\CorporateAid\Platform\Services\LookupService::class);
    $allRoles = $lookups->allFor('contact_role');
} catch (\Throwable) {
    // Fallback to legacy hardcoded roles if LookupService fails
    $allRoles = [
        'is_primary'        => 'Primary Contact',
        'is_hiring_manager' => 'Hiring Manager',
        'is_finance'        => 'Finance',
        'is_hr'             => 'HR',
        'is_decision_maker' => 'Decision Maker',
    ];
}

$notes           = (string) $get('notes');

$submittedCf = $old['cf'] ?? [];

$formAction = $isEdit
    ? '/organisations/' . $organisation->code . '/contacts/' . $contact->code
    : '/organisations/' . $organisation->code . '/contacts';

$commOptions = [
    'email'    => 'Email',
    'phone'    => 'Phone',
    'whatsapp' => 'WhatsApp',
    'sms'      => 'SMS',
    'any'      => 'Any / No preference',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · <?= e($organisation->name) ?> · Contacts<?= $isEdit ? ' · ' . e($contact->code) : '' ?>
            </div>
            <h1 class="ra-page-band-title">
                <?= $isEdit ? 'Edit Contact' : 'New Contact' ?>
            </h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= $isEdit
                ? '/organisations/' . e($organisation->code) . '/contacts/' . e($contact->code)
                : '/organisations/' . e($organisation->code) . '/contacts' ?>"
               class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i>
                <span>Cancel</span>
            </a>
            <button type="submit" form="contact-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span><?= $isEdit ? 'Save changes' : 'Create contact' ?></span>
            </button>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= e((string) $err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="contact-form" method="POST" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>

    <div class="ra-card">
        <h2 class="h5 mb-3">Personal details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="c-first">First name <span style="color:var(--ra-error-fg)">*</span></label>
                <input type="text" id="c-first" name="first_name" required maxlength="100"
                       class="ra-input" value="<?= e($firstName) ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="c-last">Last name <span style="color:var(--ra-error-fg)">*</span></label>
                <input type="text" id="c-last" name="last_name" required maxlength="100"
                       class="ra-input" value="<?= e($lastName) ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="c-position">Position</label>
                <input type="text" id="c-position" name="position" maxlength="150"
                       class="ra-input" value="<?= e($position) ?>"
                       placeholder="e.g. CEO, HR Manager">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="c-department">Department</label>
                <input type="text" id="c-department" name="department" maxlength="150"
                       class="ra-input" value="<?= e($department) ?>"
                       placeholder="e.g. Operations, Finance">
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Contact details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="c-email">Email</label>
                <input type="email" id="c-email" name="email" maxlength="190"
                       class="ra-input" value="<?= e($email) ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="c-phone">Phone</label>
                <input type="tel" id="c-phone" name="phone" maxlength="50"
                       class="ra-input" value="<?= e($phone) ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="c-alt-phone">Alternative phone</label>
                <input type="tel" id="c-alt-phone" name="alt_phone" maxlength="50"
                       class="ra-input" value="<?= e($altPhone) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="c-country">Country</label>
                <input type="text" id="c-country" name="country_code" maxlength="2"
                       class="ra-input" value="<?= e($countryCode) ?>"
                       placeholder="KE"
                       style="text-transform:uppercase;">
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="c-pref">Preferred contact</label>
                <select id="c-pref" name="preferred_communication" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($commOptions as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $preferredComm === $k ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Roles</h2>
        <p class="muted small mb-3">Tick any that apply. Roles help the team know who to contact for what.</p>

        <div class="row g-3">
            <?php if (empty($allRoles)): ?>
                <div class="col-12 muted small">No roles defined. Add roles in Admin → Lookups.</div>
            <?php else: ?>
                <?php foreach ($allRoles as $slug => $label): ?>
                    <div class="col-md-6">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                            <input type="checkbox" name="roles[]" value="<?= e($slug) ?>" <?= in_array($slug, $currentRoles, true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CUSTOM FIELDS -->
    <?php
        $renderer = \CorporateAid\Core\App::instance()
            ->container()
            ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

        echo $renderer->renderForm(
            'contact',
            $isEdit ? $contact->id : null,
            $submittedCf,
            []
        );
    ?>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Notes</h2>
        <textarea name="notes" rows="4" class="ra-input"
                  placeholder="Anything to remember about this contact..."><?= e($notes) ?></textarea>
    </div>
</form>
