<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Offer · <?= e($candidate->fullName() ?? '—') ?> · <?= e($vacancy->title ?? '—') ?>
            </div>
            <h1 class="ra-page-band-title">Create Offer</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/candidates/<?= e($candidate->code ?? '') ?>" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-3"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" action="/applications/<?= e($application->code) ?>/make-offer">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Offer terms</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="ra-label">Offered salary <span class="text-danger">*</span></label>
                        <input type="number" name="offered_salary" class="ra-input" step="0.01" min="0"
                               value="<?= e($old['offered_salary'] ?? $suggestedSalary) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Currency <span class="text-danger">*</span></label>
                        <select name="currency" class="ra-input">
                            <?php foreach ($currencies as $c): ?>
                                <?php
                                    $code = \CorporateAid\Core\Row::str($c, 'code');
                                    $selected = ($old['currency'] ?? $suggestedCurrency) === $code;
                                ?>
                                <option value="<?= e($code) ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= e($code) ?> — <?= e(\CorporateAid\Core\Row::str($c, 'name')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Start date <span class="text-danger">*</span></label>
                        <input type="text" name="start_date" class="ra-input" data-datepicker
                               value="<?= e($old['start_date'] ?? '') ?>" placeholder="yyyy-mm-dd" required>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Expires at <span class="text-danger">*</span></label>
                        <input type="text" name="expires_at" class="ra-input" data-datepicker
                               value="<?= e($old['expires_at'] ?? '') ?>" placeholder="yyyy-mm-dd" required>
                        <div class="muted small mt-1">The offer is considered expired after this date.</div>
                    </div>
                </div>
            </div>

            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Notes for the candidate</h2>
                <textarea name="notes" class="ra-input" rows="4" placeholder="Optional notes about the offer"><?= e($old['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Candidate</h2>
                <div><strong><?= e($candidate->fullName() ?? '—') ?></strong></div>
                <div class="muted small"><?= e($candidate->email ?? '') ?></div>
            </div>
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Vacancy</h2>
                <div><strong><?= e($vacancy->title ?? '—') ?></strong></div>
                <div class="muted small"><?= e($vacancy->code ?? '') ?></div>
                <?php if ($vacancy !== null && $vacancy->salaryRange() !== null): ?>
                    <div class="muted small mt-1">Vacancy salary: <?= e($vacancy->salaryRange()) ?></div>
                <?php endif; ?>
            </div>
            <button type="submit" class="ra-btn ra-btn-primary w-100">
                <i class="bi bi-file-earmark-plus"></i> Create draft offer
            </button>
        </div>
    </div>
</form>
