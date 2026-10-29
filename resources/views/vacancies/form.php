<?php
/**
 * Vacancy create/edit form.
 *
 * $vacancy, $organisations, $preselectedOrg, $preselectedEngagement
 * $staffUsers, $requirements, $old, $errors
 */
$isEdit = $vacancy !== null;

$get = function (string $key, $default = '') use ($vacancy, $old) {
    if (!empty($old) && array_key_exists($key, $old)) return $old[$key];
    if ($vacancy !== null) {
        return match ($key) {
            'engagement_id'             => $vacancy->engagementId ?? '',
            'title'                     => $vacancy->title,
            'department'                => $vacancy->department ?? '',
            'country_code'              => $vacancy->countryCode ?? '',
            'city'                      => $vacancy->city ?? '',
            'work_mode'                 => $vacancy->workMode,
            'employment_type'           => $vacancy->employmentType,
            'seniority'                 => $vacancy->seniority,
            'positions_count'           => $vacancy->positionsCount,
            'opening_date'              => $vacancy->openingDate ?? '',
            'target_hire_date'          => $vacancy->targetHireDate ?? '',
            'recruiter_id'              => $vacancy->recruiterId ?? '',
            'hiring_manager_contact_id' => $vacancy->hiringManagerContactId ?? '',
            'salary_min'                => $vacancy->salaryMin ?? '',
            'salary_max'                => $vacancy->salaryMax ?? '',
            'currency'                  => $vacancy->currency ?? '',
            'description'               => $vacancy->description ?? '',
            'responsibilities'          => $vacancy->responsibilities ?? '',
            'requirements'              => $vacancy->requirements ?? '',
            'preferred_qualifications'  => $vacancy->preferredQualifications ?? '',
            'skills'                    => $vacancy->skills,
            'languages'                 => $vacancy->languages,
            'assessment_criteria'       => $vacancy->assessmentCriteria ?? '',
            'internal_notes'            => $vacancy->internalNotes ?? '',
            'client_notes'              => $vacancy->clientNotes ?? '',
            'status'                    => $vacancy->status,
            'is_public'                 => $vacancy->isPublic,
            default                     => $default,
        };
    }
    return $default;
};

$engagementId = (string) $get('engagement_id');
$title        = (string) $get('title');
$department   = (string) $get('department');
$countryCode  = (string) $get('country_code');
$city         = (string) $get('city');
$workMode     = (string) $get('work_mode', 'onsite');
$employmentType = (string) $get('employment_type', 'permanent');
$seniority    = (string) $get('seniority', 'mid');
$positions    = (string) $get('positions_count', '1');
$openingDate  = (string) $get('opening_date');
$targetHire   = (string) $get('target_hire_date');
$recruiterId  = (string) $get('recruiter_id');
$hmContactId  = (string) $get('hiring_manager_contact_id');
$salaryMin    = (string) $get('salary_min');
$salaryMax    = (string) $get('salary_max');
$currency     = (string) $get('currency');
$description  = (string) $get('description');
$responsibilities = (string) $get('responsibilities');
$requirementsBody = (string) $get('requirements');
$preferred    = (string) $get('preferred_qualifications');
$skills       = (array) $get('skills', []);
$languages    = (array) $get('languages', []);
$assessment   = (string) $get('assessment_criteria');
$internalNotes = (string) $get('internal_notes');
$clientNotes  = (string) $get('client_notes');
$status       = (string) $get('status', 'draft');
$isPublic     = (bool) $get('is_public', false);

$selectedOrgCode = '';
if ($preselectedOrg) $selectedOrgCode = $preselectedOrg->code;
elseif (!empty($old['organisation_code'])) $selectedOrgCode = (string) $old['organisation_code'];

// Requirement lists (already normalised) for pre-fill
$reqByType = [];
foreach ($requirements as $r) {
    $reqByType[$r->type][] = $r->value;
}
$respList = implode("\n", $reqByType['responsibility'] ?? []);
$reqList  = implode("\n", $reqByType['requirement'] ?? []);
$prefList = implode("\n", $reqByType['preferred'] ?? []);

if (!empty($old['responsibilities_list'])) {
    $respList = is_array($old['responsibilities_list']) ? implode("\n", $old['responsibilities_list']) : (string) $old['responsibilities_list'];
}
if (!empty($old['requirements_list'])) {
    $reqList = is_array($old['requirements_list']) ? implode("\n", $old['requirements_list']) : (string) $old['requirements_list'];
}
if (!empty($old['preferred_list'])) {
    $prefList = is_array($old['preferred_list']) ? implode("\n", $old['preferred_list']) : (string) $old['preferred_list'];
}

$statuses = [
    'draft' => 'Draft', 'pending_approval' => 'Pending Approval', 'open' => 'Open',
    'sourcing' => 'Sourcing', 'screening' => 'Screening', 'shortlisting' => 'Shortlisting',
    'client_review' => 'Client Review', 'interviewing' => 'Interviewing',
    'reference_check' => 'Reference Check', 'offer' => 'Offer', 'filled' => 'Filled',
    'on_hold' => 'On Hold', 'cancelled' => 'Cancelled', 'closed' => 'Closed',
];
// Load from lookups — admin-configurable, no hardcoding
$lookupService = \CorporateAid\Core\App::instance()
    ->container()
    ->get(\CorporateAid\Platform\Services\LookupService::class);
$workModes = $lookupService->allFor('work_mode');
$employmentTypes = $lookupService->allFor('employment_type');
$seniorities = $lookupService->allFor('seniority');

$formAction = $isEdit ? '/vacancies/' . $vacancy->code : '/vacancies';
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Recruitment · Vacancies<?= $isEdit ? ' · ' . e($vacancy->code) : '' ?>
            </div>
            <h1 class="ra-page-band-title"><?= $isEdit ? 'Edit Vacancy' : 'New Vacancy' ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= $isEdit ? '/vacancies/' . e($vacancy->code) : '/vacancies' ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-x-lg"></i><span>Cancel</span>
            </a>
            <button type="submit" form="vac-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span><?= $isEdit ? 'Save changes' : 'Create vacancy' ?></span>
            </button>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error">
        <?php foreach ($errors as $err): ?><div><?= e((string) $err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="vac-form" method="POST" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="ra-card">
        <h2 class="h5 mb-3">Client &amp; engagement</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="vac-org">Organisation <span style="color:var(--ra-error-fg)">*</span></label>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="organisation_code" value="<?= e($selectedOrgCode) ?>">
                    <input type="text" class="ra-input" value="<?= e($preselectedOrg->name ?? '') ?>" disabled>
                <?php else: ?>
                    <select id="vac-org" name="organisation_code" required class="ra-input ra-select" style="width:100%;">
                        <option value="">— Choose a client —</option>
                        <?php foreach ($organisations as $o): ?>
                            <option value="<?= e($o->code) ?>" data-org-id="<?= (int) $o->id ?>" <?= $selectedOrgCode === $o->code ? 'selected' : '' ?>>
                                <?= e($o->name) ?> (<?= e($o->code) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="vac-engagement-select">Engagement</label>
                <select id="vac-engagement-select" name="engagement_id"
                        class="ra-input ra-select" style="width:100%;">
                    <option value="">— None —</option>
                    <?php
                        // Build flat list: preloaded engagements grouped by org
                        $currentOrgId = $preselectedOrg ? $preselectedOrg->id : 0;
                        foreach ($engagementsByOrg as $orgId => $engList):
                            foreach ($engList as $eng):
                    ?>
                        <option value="<?= (int) $eng['id'] ?>"
                                data-org-id="<?= (int) $orgId ?>"
                                <?= (string) $eng['id'] === $engagementId ? 'selected' : '' ?>
                                <?= $orgId !== $currentOrgId ? 'style="display:none;"' : '' ?>>
                            <?= e($eng['name']) ?> (<?= e($eng['code']) ?>)
                        </option>
                    <?php
                            endforeach;
                        endforeach;
                    ?>
                </select>
                <div class="muted small mt-1">
                    Optional. Links this vacancy to a recruitment contract.
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Position details</h2>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="ra-label" for="vac-title">Job title <span style="color:var(--ra-error-fg)">*</span></label>
                <input type="text" id="vac-title" name="title" required maxlength="200"
                       class="ra-input" value="<?= e($title) ?>"
                       placeholder="e.g. Senior Finance Manager">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-status">Status</label>
                <select id="vac-status" name="status" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($statuses as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-dept">Department</label>
                <input type="text" id="vac-dept" name="department" maxlength="150"
                       class="ra-input" value="<?= e($department) ?>">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-seniority">Seniority</label>
                <select id="vac-seniority" name="seniority" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($seniorities as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $seniority === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-positions">Number of positions</label>
                <input type="number" id="vac-positions" name="positions_count" min="1" max="1000"
                       class="ra-input" value="<?= e($positions) ?>">
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-workmode">Work mode</label>
                <select id="vac-workmode" name="work_mode" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($workModes as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $workMode === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="ra-label" for="vac-emptype">Employment type</label>
                <select id="vac-emptype" name="employment_type" class="ra-input ra-select" style="width:100%;">
                    <?php foreach ($employmentTypes as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $employmentType === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Location</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="ra-label" for="vac-country">Country</label>
                <input type="text" id="vac-country" name="country_code" maxlength="2"
                       class="ra-input" value="<?= e($countryCode) ?>"
                       placeholder="KE" style="text-transform:uppercase;">
            </div>
            <div class="col-md-8">
                <label class="ra-label" for="vac-city">City / location</label>
                <input type="text" id="vac-city" name="city" maxlength="100"
                       class="ra-input" value="<?= e($city) ?>">
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Dates &amp; ownership</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="ra-label" for="vac-opening">Opening date</label>
                <input type="date" id="vac-opening" name="opening_date" class="ra-input" value="<?= e($openingDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="vac-target">Target hire date</label>
                <input type="date" id="vac-target" name="target_hire_date" class="ra-input" value="<?= e($targetHire) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="vac-recruiter">Assigned recruiter</label>
                <select id="vac-recruiter" name="recruiter_id" class="ra-input ra-select" style="width:100%;">
                    <option value="">— None —</option>
                    <?php foreach ($staffUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (string) $u['id'] === $recruiterId ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="vac-hm-select">Hiring manager</label>
                <select id="vac-hm-select" name="hiring_manager_contact_id"
                        class="ra-input ra-select" style="width:100%;">
                    <option value="">— None —</option>
                    <?php
                        $currentOrgIdForHm = $preselectedOrg ? $preselectedOrg->id : 0;
                        foreach ($contactsByOrg as $orgId => $contactList):
                            foreach ($contactList as $contact):
                    ?>
                        <option value="<?= (int) $contact['id'] ?>"
                                data-org-id="<?= (int) $orgId ?>"
                                <?= (string) $contact['id'] === $hmContactId ? 'selected' : '' ?>
                                <?= $orgId !== $currentOrgIdForHm ? 'style="display:none;"' : '' ?>>
                            <?= e($contact['name']) ?><?php if (!empty($contact['position'])): ?> · <?= e($contact['position']) ?><?php endif; ?>
                        </option>
                    <?php
                            endforeach;
                        endforeach;
                    ?>
                </select>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Compensation</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="ra-label" for="vac-smin">Salary minimum</label>
                <input type="number" id="vac-smin" name="salary_min" step="0.01"
                       class="ra-input" value="<?= e($salaryMin) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label" for="vac-smax">Salary maximum</label>
                <input type="number" id="vac-smax" name="salary_max" step="0.01"
                       class="ra-input" value="<?= e($salaryMax) ?>">
            </div>
            <div class="col-md-2">
                <label class="ra-label" for="vac-cur">Currency</label>
                <input type="text" id="vac-cur" name="currency" maxlength="3"
                       class="ra-input" value="<?= e($currency) ?>"
                       placeholder="KES" style="text-transform:uppercase;">
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Job description</h2>
        <div class="row g-3">
            <div class="col-12">
                <label class="ra-label" for="vac-description">Overview</label>
                <textarea id="vac-description" name="description" rows="3" class="ra-input"
                          placeholder="Brief overview of the role and the team..."><?= e($description) ?></textarea>
            </div>
            <div class="col-12">
                <label class="ra-label" for="vac-resp">Responsibilities (one per line)</label>
                <textarea id="vac-resp" name="responsibilities_list" rows="4" class="ra-input"
                          placeholder="Lead monthly financial reporting&#10;Manage the finance team of 5&#10;Own the annual budget process"><?= e($respList) ?></textarea>
            </div>
            <div class="col-12">
                <label class="ra-label" for="vac-req">Requirements (one per line)</label>
                <textarea id="vac-req" name="requirements_list" rows="4" class="ra-input"
                          placeholder="CPA or ACCA qualified&#10;10+ years in finance, 3+ in management&#10;Experience in banking or fintech"><?= e($reqList) ?></textarea>
            </div>
            <div class="col-12">
                <label class="ra-label" for="vac-pref">Preferred qualifications (one per line)</label>
                <textarea id="vac-pref" name="preferred_list" rows="3" class="ra-input"
                          placeholder="MBA or Master's degree&#10;Experience with IFRS 17"><?= e($prefList) ?></textarea>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Skills &amp; languages</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="vac-skills">Skills (comma-separated)</label>
                <input type="text" id="vac-skills" name="skills[]"
                       class="ra-input" value="<?= e(implode(', ', $skills)) ?>"
                       placeholder="Financial modelling, IFRS, SAP">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="vac-langs">Languages (comma-separated)</label>
                <input type="text" id="vac-langs" name="languages[]"
                       class="ra-input" value="<?= e(implode(', ', $languages)) ?>"
                       placeholder="English, Swahili">
            </div>
        </div>
    </div>

    <?php
        $renderer = \CorporateAid\Core\App::instance()
            ->container()
            ->get(\CorporateAid\Platform\CustomFields\CustomFieldRenderer::class);
        echo $renderer->renderForm('vacancy', $isEdit ? $vacancy->id : null, $old['cf'] ?? [], []);
    ?>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Assessment criteria</h2>
        <textarea name="assessment_criteria" rows="3" class="ra-input"
                  placeholder="What will you assess candidates against?"><?= e($assessment) ?></textarea>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-3">Notes</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="vac-internal">Internal notes</label>
                <textarea id="vac-internal" name="internal_notes" rows="3" class="ra-input"
                          placeholder="Notes for your team only..."><?= e($internalNotes) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="vac-client">Client notes</label>
                <textarea id="vac-client" name="client_notes" rows="3" class="ra-input"
                          placeholder="Notes visible to the client..."><?= e($clientNotes) ?></textarea>
            </div>
            <div class="col-12">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="is_public" value="1" <?= $isPublic ? 'checked' : '' ?>>
                    <span>Publish to public job board</span>
                </label>
                <div class="muted small mt-1 ms-4">When enabled, this vacancy appears on /jobs for public applications.</div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    // Filter the Engagement and Hiring Manager dropdowns when org changes.
    var orgSelect = document.getElementById('vac-org');
    var engSelect = document.getElementById('vac-engagement-select');
    var hmSelect = document.getElementById('vac-hm-select');

    if (!orgSelect || !engSelect) return;

    function filterByOrg() {
        var orgCode = orgSelect.value; // e.g. ORG-000003
        // We need the numeric org id. Look at the option's data attribute or map code→id.
        // The org option text contains the code but we need the numeric id.
        // Instead: query the option to get the data-org-id, or map from code.
        // Simpler: option value is the code, and we stored the numeric id in a data attribute on the option.
        var selectedOption = orgSelect.options[orgSelect.selectedIndex];
        var orgId = selectedOption ? selectedOption.getAttribute('data-org-id') : '';

        // Filter engagement options
        if (engSelect) {
            Array.prototype.forEach.call(engSelect.options, function (opt) {
                if (!opt.value) return; // keep "— None —"
                if (orgId === '' || opt.getAttribute('data-org-id') === orgId) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.selected) engSelect.value = ''; // reset if hidden
                }
            });
        }

        // Filter hiring manager options
        if (hmSelect) {
            Array.prototype.forEach.call(hmSelect.options, function (opt) {
                if (!opt.value) return;
                if (orgId === '' || opt.getAttribute('data-org-id') === orgId) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.selected) hmSelect.value = '';
                }
            });
        }
    }

    orgSelect.addEventListener('change', filterByOrg);

    // In create mode, auto-run once so initial state is correct
    if (!<?= $isEdit ? 'true' : 'false' ?>) {
        filterByOrg();
    }
})();
</script>
