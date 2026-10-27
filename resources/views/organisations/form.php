<?php
/**
 * Organisation create/edit form.
 *
 * $organisation  — Organisation|null  (null = create mode)
 * $old           — submitted values (from validation failure)
 * $errors        — flat array of error messages
 * $renderer      — resolves custom field renderer from the container
 */
$isEdit = $organisation !== null;

// Resolve form values: submitted > existing > default
$get = function (string $key, $default = '') use ($organisation, $old) {
    if (!empty($old) && array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($organisation !== null) {
        return match ($key) {
            'name'                => $organisation->name,
            'legal_name'          => $organisation->legalName ?? '',
            'industry'            => $organisation->industry ?? '',
            'website'             => $organisation->website ?? '',
            'country_code'        => $organisation->countryCode ?? '',
            'countries_operation' => $organisation->countriesOperation,
            'address'             => $organisation->address ?? '',
            'phone'               => $organisation->phone ?? '',
            'email'               => $organisation->email ?? '',
            'status'              => $organisation->status,
            'notes'               => $organisation->notes ?? '',
            default               => $default,
        };
    }
    return $default;
};

$name        = (string) $get('name');
$legalName   = (string) $get('legal_name');
$industry    = (string) $get('industry');
$website     = (string) $get('website');
$countryCode = (string) $get('country_code');
$countriesOperation = (array) $get('countries_operation', []);
$address     = (string) $get('address');
$phone       = (string) $get('phone');
$email       = (string) $get('email');
$status      = (string) $get('status', 'prospect');
$notes       = (string) $get('notes');

$submittedCf = $old['cf'] ?? [];
$formAction = $isEdit
    ? '/organisations/' . $organisation->code
    : '/organisations';

$statusOptions = [
    'prospect'      => 'Prospect',
    'active'        => 'Active',
    'inactive'      => 'Inactive',
    'former_client' => 'Former Client',
];
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Clients · Organisations<?= $isEdit ? ' · ' . e($organisation->code) : '' ?>
            </div>
            <h1 class="ra-page-band-title">
                <?= $isEdit ? 'Edit Organisation' : 'New Organisation' ?>
            </h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= $isEdit ? '/organisations/' . e($organisation->code) : '/organisations' ?>"
               class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i>
                <span>Cancel</span>
            </a>
            <button type="submit" form="org-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span><?= $isEdit ? 'Save changes' : 'Create organisation' ?></span>
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

<form id="org-form" method="POST" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>

    <div class="ra-card">
        <h2 class="h5 mb-3">Basic information</h2>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="org-name">
                    Name <span style="color:var(--ra-error-fg)">*</span>
                </label>
                <input type="text" id="org-name" name="name" required maxlength="255"
                       class="ra-input" value="<?= e($name) ?>"
                       placeholder="e.g. Acme Corporation">
                <div class="muted small mt-1">Trading name shown on lists and reports.</div>
            </div>

            <div class="col-md-6">
                <label class="ra-label" for="org-legal-name">Legal name</label>
                <input type="text" id="org-legal-name" name="legal_name" maxlength="255"
                       class="ra-input" value="<?= e($legalName) ?>"
                       placeholder="Optional. Registered name if different.">
            </div>

            <div class="col-md-6">
                <label class="ra-label" for="org-industry">Industry</label>
                <input type="text" id="org-industry" name="industry" maxlength="100"
                       class="ra-input" value="<?= e($industry) ?>"
                       placeholder="e.g. Financial Services, Technology, Healthcare">
            </div>

            <div class="col-md-6">
                <label class="ra-label" for="org-website">Website</label>
                <input type="url" id="org-website" name="website" maxlength="255"
                       class="ra-input" value="<?= e($website) ?>"
                       placeholder="https://">
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Location</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="ra-label" for="org-country">Primary country</label>
                <input type="text" id="org-country" name="country_code" maxlength="2"
                       class="ra-input" value="<?= e($countryCode) ?>"
                       placeholder="e.g. KE, UG, TZ"
                       style="text-transform:uppercase;">
                <div class="muted small mt-1">2-letter ISO code.</div>
            </div>

            <div class="col-md-8">
                <label class="ra-label" for="org-countries-op">
                    Countries of operation
                </label>
                <input type="text" id="org-countries-op" name="countries_operation[]"
                       class="ra-input"
                       value="<?= e(implode(', ', $countriesOperation)) ?>"
                       placeholder="Comma-separated, e.g. KE, UG, TZ, NG">
                <div class="muted small mt-1">
                    Optional. List all countries this client operates in.
                </div>
            </div>

            <div class="col-12">
                <label class="ra-label" for="org-address">Address</label>
                <textarea id="org-address" name="address" rows="2"
                          class="ra-input"
                          placeholder="Street, city, postal code"><?= e($address) ?></textarea>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Contact</h2>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="org-phone">Phone</label>
                <input type="tel" id="org-phone" name="phone" maxlength="50"
                       class="ra-input" value="<?= e($phone) ?>"
                       placeholder="+254 700 000 000">
            </div>

            <div class="col-md-6">
                <label class="ra-label" for="org-email">Email</label>
                <input type="email" id="org-email" name="email" maxlength="190"
                       class="ra-input" value="<?= e($email) ?>"
                       placeholder="contact@example.com">
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- CUSTOM FIELDS — automatically rendered for this entity -->
    <!-- ===================================================== -->
    <?php
        /** @var \CorporateAid\Platform\CustomFields\CustomFieldRenderer $renderer */
        $renderer = \CorporateAid\Core\App::instance()
            ->container()
            ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);

        echo $renderer->renderForm(
            'organisation',
            $isEdit ? $organisation->id : null,
            $submittedCf,
            [] // per-field errors — for now shown together at the top
        );
    ?>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Status &amp; notes</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="ra-label" for="org-status">Status</label>
                <select id="org-status" name="status" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-8">
                <label class="ra-label" for="org-notes">Internal notes</label>
                <textarea id="org-notes" name="notes" rows="3"
                          class="ra-input"
                          placeholder="Anything the team should know about this client..."><?= e($notes) ?></textarea>
            </div>
        </div>
    </div>
</form>
