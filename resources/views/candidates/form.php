<?php
/**
 * Candidate create/edit form.
 *
 * $candidate  — Candidate|null (null on create)
 * $staffUsers — array (for recruiter assignment)
 * $old        — array of old input (for repopulation)
 * $errors     — string[] of error messages
 */
$isEdit = $candidate !== null;
$action = $isEdit ? '/candidates/' . $candidate->code : '/candidates';
$method = $isEdit ? 'PUT' : 'POST';

// Helper to get old input or fallback to model value
$val = function(string $key, $default = '') use ($old, $candidate) {
    if (isset($old[$key])) return $old[$key];
    if ($candidate !== null) return $candidate->$key ?? $default;
    return $default;
};
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment · Candidates</div>
            <h1 class="ra-page-band-title"><?= $isEdit ? 'Edit ' . e($candidate->fullName()) : 'New Candidate' ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="<?= $isEdit ? '/candidates/' . e($candidate->code) : '/candidates' ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-danger mb-3">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $action ?>">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <!-- CV Upload (honest scoping: stores file, no auto-fill yet) -->
    <div class="ra-card mb-3">
        <h2 class="h6 mb-3">CV Upload</h2>
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="ra-label">Upload CV (PDF, DOC, DOCX)</label>
                <input type="file" name="cv_file" class="ra-input" accept=".pdf,.doc,.docx">
            </div>
            <div class="col-md-4">
                <div class="muted small">Auto-fill from CV is coming in a future release. Please enter details manually for now.</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Identity -->
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Identity</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="ra-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="ra-input" value="<?= e($val('first_name')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="ra-input" value="<?= e($val('last_name')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="ra-input" value="<?= e($val('email')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Phone</label>
                        <input type="text" name="phone" class="ra-input" value="<?= e($val('phone')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">Country (ISO)</label>
                        <input type="text" name="country_code" class="ra-input" maxlength="2" placeholder="e.g. KE" value="<?= e($val('country_code')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">City</label>
                        <input type="text" name="city" class="ra-input" value="<?= e($val('city')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">Source</label>
                        <input type="text" name="source" class="ra-input" placeholder="e.g. LinkedIn, Referral" value="<?= e($val('source')) ?>">
                    </div>
                </div>
            </div>

            <!-- Current Role -->
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Current Role</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="ra-label">Job Title</label>
                        <input type="text" name="current_job_title" class="ra-input" value="<?= e($val('current_job_title')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Company</label>
                        <input type="text" name="current_company" class="ra-input" value="<?= e($val('current_company')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">Years Experience</label>
                        <input type="number" name="years_experience" class="ra-input" min="0" max="60" value="<?= e($val('years_experience')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">Seniority</label>
                        <select name="seniority" class="ra-input">
                            <option value="">—</option>
                            <?php foreach (['junior','mid','senior','lead','manager','director','executive'] as $s): ?>
                                <option value="<?= $s ?>" <?= $val('seniority') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="ra-label">Status</label>
                        <select name="status" class="ra-input">
                            <?php foreach (['active','passive','hired','blacklisted','archived'] as $s): ?>
                                <option value="<?= $s ?>" <?= $val('status') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Experience -->
            <div class="ra-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Experience</h2>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm add-exp-btn">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <div id="experience-container"></div>
            </div>

            <!-- Education -->
            <div class="ra-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Education</h2>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm add-edu-btn">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <div id="education-container"></div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Links & Tags</h2>
                <div class="mb-3">
                    <label class="ra-label">LinkedIn URL</label>
                    <input type="url" name="linkedin_url" class="ra-input" value="<?= e($val('linkedin_url')) ?>">
                </div>
                <div class="mb-3">
                    <label class="ra-label">Portfolio URL</label>
                    <input type="url" name="portfolio_url" class="ra-input" value="<?= e($val('portfolio_url')) ?>">
                </div>
                <div class="mb-3">
                    <label class="ra-label">Tags (comma separated)</label>
                    <input type="text" name="tags" class="ra-input" value="<?= e($val('tags')) ?>" placeholder="e.g. remote, senior, finance">
                </div>
                <div class="mb-3">
                    <label class="ra-label">Skills (one per line)</label>
                    <textarea name="skills_flat" class="ra-input" rows="4"><?= e($val('skills')) ?></textarea>
                </div>
            </div>

            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Summary</h2>
                <div class="mb-3">
                    <label class="ra-label">Profile Summary</label>
                    <textarea name="summary" class="ra-input" rows="4"><?= e($val('summary')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="ra-label">Internal Notes</label>
                    <textarea name="internal_notes" class="ra-input" rows="3"><?= e($val('internal_notes')) ?></textarea>
                </div>
            </div>

            <button type="submit" class="ra-btn ra-btn-primary w-100">
                <i class="bi bi-save"></i> Save Candidate
            </button>
        </div>
    </div>
</form>

<script>
let expIndex = 0;
let eduIndex = 0;

// Normalize: ensure it's always an array of {id, label}
const eduLevelsRaw = <?= json_encode($educationLevels ?? []) ?>;
const eduLevels = Array.isArray(eduLevelsRaw)
    ? eduLevelsRaw
    : Object.values(eduLevelsRaw).map(v => typeof v === 'object' ? v : { id: v, label: v });

function initDatepickersIn(container) {
    container.querySelectorAll('[data-datepicker]').forEach(function(el) {
        if (el._airDatepicker) return;
        new AirDatepicker(el, { dateFormat: 'yyyy-MM-dd', autoClose: true });
    });
}

function addExperienceRow(data = {}) {
    const container = document.getElementById('experience-container');
    const id = expIndex++;
    const html = `
        <div class="row g-2 mb-2 align-items-end border-bottom pb-2" id="exp-row-${id}">
            <div class="col-md-3"><input type="text" name="experience[${id}][job_title]" class="ra-input" placeholder="Job Title" value="${data.job_title || ''}"></div>
            <div class="col-md-3"><input type="text" name="experience[${id}][company]" class="ra-input" placeholder="Company" value="${data.company || ''}"></div>
            <div class="col-md-2"><input type="text" name="experience[${id}][company_type]" class="ra-input" placeholder="Company Type" value="${data.company_type || ''}"></div>
            <div class="col-md-2"><input type="text" name="experience[${id}][location]" class="ra-input" placeholder="Location" value="${data.location || ''}"></div>
            <div class="col-md-2"><input type="text" data-datepicker name="experience[${id}][start_date]" class="ra-input" placeholder="yyyy-mm-dd" value="${data.start_date || ''}"></div>
            <div class="col-md-2"><input type="text" data-datepicker name="experience[${id}][end_date]" class="ra-input" placeholder="yyyy-mm-dd" value="${data.end_date || ''}"></div>
            <div class="col-md-1"><label class="small"><input type="checkbox" name="experience[${id}][is_current]" value="1" ${data.is_current ? 'checked' : ''}> Current</label></div>
            <div class="col-md-1"><button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger remove-exp"><i class="bi bi-x"></i></button></div>
            <div class="col-12"><textarea name="experience[${id}][description]" class="ra-input" rows="2" placeholder="Description">${data.description || ''}</textarea></div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    initDatepickersIn(container);
}

function addEducationRow(data = {}) {
    const container = document.getElementById('education-container');
    const id = eduIndex++;
    const options = eduLevels.map(l => `<option value="${l.id}" ${data.education_level_id == l.id ? 'selected' : ''}>${l.label}</option>`).join('');
    const html = `
        <div class="row g-2 mb-2 align-items-end border-bottom pb-2" id="edu-row-${id}">
            <div class="col-md-2"><select name="education[${id}][education_level_id]" class="ra-input"><option value="">Level</option>${options}</select></div>
            <div class="col-md-3"><input type="text" name="education[${id}][institution]" class="ra-input" placeholder="Institution" value="${data.institution || ''}"></div>
            <div class="col-md-2"><input type="text" name="education[${id}][school_location]" class="ra-input" placeholder="School Location" value="${data.school_location || ''}"></div>
            <div class="col-md-2"><input type="text" name="education[${id}][degree]" class="ra-input" placeholder="Degree" value="${data.degree || ''}"></div>
            <div class="col-md-2"><input type="text" name="education[${id}][field_of_study]" class="ra-input" placeholder="Field of Study" value="${data.field_of_study || ''}"></div>
            <div class="col-md-2"><input type="text" name="education[${id}][minor]" class="ra-input" placeholder="Minor" value="${data.minor || ''}"></div>
            <div class="col-md-2"><input type="number" name="education[${id}][start_year]" class="ra-input" placeholder="Start Year" value="${data.start_year || ''}"></div>
            <div class="col-md-2"><input type="number" name="education[${id}][end_year]" class="ra-input" placeholder="End Year" value="${data.end_year || ''}"></div>
            <div class="col-md-2"><input type="text" name="education[${id}][grade]" class="ra-input" placeholder="Grade" value="${data.grade || ''}"></div>
            <div class="col-md-1"><button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger remove-edu"><i class="bi bi-x"></i></button></div>
            <div class="col-12"><textarea name="education[${id}][description]" class="ra-input" rows="2" placeholder="Description">${data.description || ''}</textarea></div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

// Event delegation for Add/Remove buttons
document.addEventListener('click', function(e) {
    if (e.target.closest('.add-exp-btn')) { e.preventDefault(); addExperienceRow(); }
    if (e.target.closest('.add-edu-btn')) { e.preventDefault(); addEducationRow(); }
    if (e.target.closest('.remove-exp')) { e.preventDefault(); e.target.closest('[id^="exp-row-"]').remove(); }
    if (e.target.closest('.remove-edu')) { e.preventDefault(); e.target.closest('[id^="edu-row-"]').remove(); }
});

// Initial rows
document.addEventListener('DOMContentLoaded', function() {
    const existingExp = <?= json_encode($old['experience'] ?? []) ?>;
    if (existingExp.length > 0) { existingExp.forEach(exp => addExperienceRow(exp)); } else { addExperienceRow(); }

    const existingEdu = <?= json_encode($old['education'] ?? []) ?>;
    if (existingEdu.length > 0) { existingEdu.forEach(edu => addEducationRow(edu)); } else { addEducationRow(); }
});
</script>
