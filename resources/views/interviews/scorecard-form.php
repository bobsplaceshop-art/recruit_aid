<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Interview · <code><?= e($interview->code) ?></code></div>
            <h1 class="ra-page-band-title">Your Scorecard</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/interviews/<?= e($interview->code) ?>" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<?php if ($existing !== null && $existing->isSubmitted()): ?>
    <div class="ra-alert ra-alert-warn">You've already submitted this scorecard. Submitted scorecards cannot be edited.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-3"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" action="/interviews/<?= e($interview->code) ?>/scorecard">
    <?= csrf_field() ?>

    <div class="ra-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Competency scores</h2>
            <button type="button" class="ra-btn ra-btn-outline ra-btn-sm" id="add-competency">
                <i class="bi bi-plus"></i> Add competency
            </button>
        </div>
        <div id="attributes-container">
            <?php foreach ($attributes as $attr): ?>
                <div class="row g-2 mb-2 align-items-start" data-attribute-row>
                    <div class="col-md-4"><input type="text" name="attributes[][competency_name]" class="ra-input" placeholder="Competency" value="<?= e($attr->competencyName) ?>"></div>
                    <div class="col-md-2">
                        <select name="attributes[][score]" class="ra-input">
                            <?php foreach ([1,2,3,4,5] as $n): ?>
                                <option value="<?= $n ?>" <?= $attr->score === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5"><input type="text" name="attributes[][evidence]" class="ra-input" placeholder="Evidence" value="<?= e($attr->evidence ?? '') ?>"></div>
                    <div class="col-md-1"><button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger" data-remove-row><i class="bi bi-x"></i></button></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($attributes)): ?>
                <div class="row g-2 mb-2 align-items-start" data-attribute-row>
                    <div class="col-md-4"><input type="text" name="attributes[][competency_name]" class="ra-input" placeholder="Competency (e.g. Communication)"></div>
                    <div class="col-md-2">
                        <select name="attributes[][score]" class="ra-input">
                            <?php foreach ([1,2,3,4,5] as $n): ?><option value="<?= $n ?>"><?= $n ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5"><input type="text" name="attributes[][evidence]" class="ra-input" placeholder="Evidence"></div>
                    <div class="col-md-1"><button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger" data-remove-row><i class="bi bi-x"></i></button></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ra-card mb-3">
        <h2 class="h6 mb-3">Recommendation</h2>
        <select name="recommendation" class="ra-input">
            <option value="">—</option>
            <option value="strong_yes" <?= ($existing?->recommendation === 'strong_yes') ? 'selected' : '' ?>>Strong Yes</option>
            <option value="yes"        <?= ($existing?->recommendation === 'yes') ? 'selected' : '' ?>>Yes</option>
            <option value="neutral"    <?= ($existing?->recommendation === 'neutral') ? 'selected' : '' ?>>Neutral</option>
            <option value="no"         <?= ($existing?->recommendation === 'no') ? 'selected' : '' ?>>No</option>
            <option value="strong_no"  <?= ($existing?->recommendation === 'strong_no') ? 'selected' : '' ?>>Strong No</option>
        </select>
    </div>

    <div class="ra-card mb-3">
        <h2 class="h6 mb-3">Notes</h2>
        <textarea name="notes" class="ra-input" rows="4"><?= e($existing?->notes ?? '') ?></textarea>
    </div>

    <button type="submit" class="ra-btn ra-btn-primary" <?= ($existing !== null && $existing->isSubmitted()) ? 'disabled' : '' ?>>
        <i class="bi bi-check2-circle"></i> Submit Scorecard
    </button>
</form>

<script>
document.getElementById('add-competency').addEventListener('click', function() {
    const container = document.getElementById('attributes-container');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-start';
    row.setAttribute('data-attribute-row', '');
    row.innerHTML = `
        <div class="col-md-4"><input type="text" name="attributes[][competency_name]" class="ra-input" placeholder="Competency"></div>
        <div class="col-md-2">
            <select name="attributes[][score]" class="ra-input">
                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option>
            </select>
        </div>
        <div class="col-md-5"><input type="text" name="attributes[][evidence]" class="ra-input" placeholder="Evidence"></div>
        <div class="col-md-1"><button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger" data-remove-row><i class="bi bi-x"></i></button></div>
    `;
    container.appendChild(row);
});

document.getElementById('attributes-container').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-remove-row]');
    if (!btn) return;
    const row = btn.closest('[data-attribute-row]');
    if (row) row.remove();
});
</script>
