<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Insights · Funnel</div>
            <h1 class="ra-page-band-title">Pipeline Funnel</h1>
            <p class="ra-page-sub">How many candidates entered, are currently in, and spent time in each stage.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/reports" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<form method="GET" action="/reports/funnel" class="mb-3">
    <div class="d-flex gap-2 align-items-center">
        <select name="vacancy_id" class="ra-input" style="max-width: 500px;" onchange="this.form.submit()">
            <option value="">— Choose a vacancy —</option>
            <?php foreach ($vacancies as $v): ?>
                <option value="<?= (int) $v->id ?>" <?= ($selected && $selected->id === $v->id) ? 'selected' : '' ?>>
                    <?= e($v->code) ?> — <?= e($v->title) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if (!$selected): ?>
    <div class="ra-card text-center muted" style="padding:3rem 1.5rem;">Pick a vacancy above to see its funnel.</div>
<?php else: ?>
    <div class="ra-card">
        <h2 class="h6 mb-3"><?= e($selected->title) ?> (<?= e($selected->code) ?>)</h2>
        <div class="ra-table-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Entered</th>
                        <th>Currently in</th>
                        <th>Avg Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funnel as $row): ?>
                        <tr>
                            <td><strong><?= e($row['label']) ?></strong></td>
                            <td><?= (int) $row['entered'] ?></td>
                            <td><?= (int) $row['current'] ?></td>
                            <td><?= $row['avg_days'] > 0 ? (string) $row['avg_days'] . ' d' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
