<?php
$isEdit = $engagement !== null;

$get = function (string $key, $default = '') use ($engagement, $old) {
    if (!empty($old) && array_key_exists($key, $old)) return $old[$key];
    if ($engagement !== null) {
        return match ($key) {
            'package_id'                  => $engagement->packageId ?? '',
            'name'                        => $engagement->name,
            'service_type'                => $engagement->serviceType,
            'status'                      => $engagement->status,
            'start_date'                  => $engagement->startDate ?? '',
            'end_date'                    => $engagement->endDate ?? '',
            'agreed_active_role_capacity' => $engagement->agreedActiveRoleCapacity,
            'reporting_frequency'         => $engagement->reportingFrequency,
            'countries'                   => $engagement->countries,
            'scope_description'           => $engagement->scopeDescription ?? '',
            'stages_included'             => $engagement->stagesIncluded,
            'notes'                       => $engagement->notes ?? '',
            default                       => $default,
        };
    }
    return $default;
};

$packageId   = (string) $get('package_id');
$name        = (string) $get('name');
$serviceType = (string) $get('service_type', '3_month');
$status      = (string) $get('status', 'draft');
$startDate   = (string) $get('start_date');
$endDate     = (string) $get('end_date');
$capacity    = (string) $get('agreed_active_role_capacity', '1');
$frequency   = (string) $get('reporting_frequency', 'monthly');
$countries   = (array) $get('countries', []);
$scope       = (string) $get('scope_description');
$stages      = (array) $get('stages_included', []);
$notes       = (string) $get('notes');

$selectedOrgCode = '';
if ($preselectedOrg) $selectedOrgCode = $preselectedOrg->code;
elseif (!empty($old['organisation_code'])) $selectedOrgCode = (string) $old['organisation_code'];

$serviceTypes = [
    '3_month' => '3-Month Hiring Support',
    '6_month' => '6-Month Embedded Recruitment',
    '12_month'=> '12-Month Recruitment Partnership',
    'focused_search' => 'Focused Search',
    'assessment' => 'Applicant Assessment',
];
$statuses = [
    'draft' => 'Draft', 'proposal' => 'Proposal', 'pending_agreement' => 'Pending Agreement',
    'active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
];
$frequencies = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'custom' => 'Custom'];

$formAction = $isEdit ? '/engagements/' . $engagement->code : '/engagements';

$packagesJson = json_encode(array_map(fn ($p) => [
    'id' => (int) $p['id'], 'name' => $p['name'], 'service_type' => $p['service_type'],
    'default_duration_days' => (int) $p['default_duration_days'],
    'default_active_role_capacity' => (int) $p['default_active_role_capacity'],
    'default_reporting_frequency' => $p['default_reporting_frequency'],
], $packages), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Clients · Engagements<?= $isEdit ? ' · ' . e($engagement->code) : '' ?></div>
            <h1 class="ra-page-band-title"><?= $isEdit ? 'Edit Engagement' : 'New Engagement' ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= $isEdit ? '/engagements/' . e($engagement->code) : '/engagements' ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i><span>Cancel</span>
            </a>
            <button type="submit" form="eng-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span><?= $isEdit ? 'Save changes' : 'Create engagement' ?></span>
            </button>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error">
        <?php foreach ($errors as $err): ?><div><?= e((string) $err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="eng-form" method="POST" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="ra-card">
        <h2 class="h5 mb-3">Client</h2>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="ra-label" for="eng-org">Organisation <span style="color:var(--ra-error-fg)">*</span></label>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="organisation_code" value="<?= e($selectedOrgCode) ?>">
                    <input type="text" class="ra-input" value="<?= e($preselectedOrg->name ?? '') ?>" disabled>
                <?php else: ?>
                    <select id="eng-org" name="organisation_code" required class="ra-input ra-select" style="width:100%;">
                        <option value="">— Choose a client —</option>
                        <?php foreach ($organisations as $o): ?>
                            <option value="<?= e($o->code) ?>" <?= $selectedOrgCode === $o->code ? 'selected' : '' ?>>
                                <?= e($o->name) ?> (<?= e($o->code) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="eng-pkg">Start from package</label>
                <select id="eng-pkg" name="package_id" class="ra-input ra-select" style="width:100%;">
                    <option value="">— Custom —</option>
                    <?php foreach ($packages as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $packageId ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="muted small mt-1">Pre-fills defaults below.</div>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Engagement</h2>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="ra-label" for="eng-name">Name <span style="color:var(--ra-error-fg)">*</span></label>
                <input type="text" id="eng-name" name="name" required maxlength="200"
                       class="ra-input" value="<?= e($name) ?>"
                       placeholder="e.g. Q1 2027 Leadership Hiring">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="eng-status">Status</label>
                <select id="eng-status" name="status" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($statuses as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="eng-service">Service type</label>
                <select id="eng-service" name="service_type" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($serviceTypes as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $serviceType === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="eng-freq">Reporting frequency</label>
                <select id="eng-freq" name="reporting_frequency" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($frequencies as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $frequency === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Scope &amp; dates</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="ra-label" for="eng-start">Start date</label>
                <input type="date" id="eng-start" name="start_date" class="ra-input" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="eng-end">End date</label>
                <input type="date" id="eng-end" name="end_date" class="ra-input" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="eng-cap">Active role capacity</label>
                <input type="number" id="eng-cap" name="agreed_active_role_capacity"
                       min="1" max="500" class="ra-input" value="<?= e($capacity) ?>">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="eng-countries">Countries / markets</label>
                <input type="text" id="eng-countries" name="countries[]"
                       class="ra-input"
                       value="<?= e(implode(', ', $countries)) ?>"
                       placeholder="KE, UG, TZ">
            </div>
            <div class="col-md-8">
                <label class="ra-label" for="eng-scope">Scope description</label>
                <textarea id="eng-scope" name="scope_description" rows="3" class="ra-input"
                          placeholder="What is included in this engagement?"><?= e($scope) ?></textarea>
            </div>
            <div class="col-12">
                <label class="ra-label" for="eng-stages">Recruitment stages included</label>
                <input type="text" id="eng-stages" name="stages_included[]"
                       class="ra-input"
                       value="<?= e(implode(', ', $stages)) ?>"
                       placeholder="e.g. sourcing, screening, assessment, shortlisting, interview">
            </div>
        </div>
    </div>

    <?php
        $renderer = \CorporateAid\Core\App::instance()
            ->container()
            ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);
        echo $renderer->renderForm('engagement', $isEdit ? $engagement->id : null, $old['cf'] ?? [], []);
    ?>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Notes</h2>
        <textarea name="notes" rows="4" class="ra-input"
                  placeholder="Internal notes about this engagement..."><?= e($notes) ?></textarea>
    </div>
</form>

<script>
window.__engPackages = <?= $packagesJson ?>;
(function(){
    var sel = document.getElementById('eng-pkg');
    if (!sel) return;
    function applyPackage() {
        var pkgId = parseInt(sel.value, 10);
        if (!pkgId) return;
        var pkg = (window.__engPackages || []).find(function(p){ return p.id === pkgId; });
        if (!pkg) return;
        document.getElementById('eng-service').value = pkg.service_type;
        document.getElementById('eng-cap').value = pkg.default_active_role_capacity;
        document.getElementById('eng-freq').value = pkg.default_reporting_frequency;
        var start = document.getElementById('eng-start').value;
        if (start && pkg.default_duration_days) {
            var d = new Date(start + 'T00:00:00Z');
            d.setUTCDate(d.getUTCDate() + pkg.default_duration_days);
            document.getElementById('eng-end').value = d.toISOString().slice(0, 10);
        }
    }
    sel.addEventListener('change', applyPackage);
    document.getElementById('eng-start').addEventListener('change', applyPackage);
})();
</script>
