<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Insights · Productivity</div>
            <h1 class="ra-page-band-title">Recruiter Productivity</h1>
            <p class="ra-page-sub">Activity per recruiter over the last <?= (int) $days ?> days.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/reports" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<form method="GET" action="/reports/productivity" class="mb-3">
    <div class="d-flex gap-2 align-items-center">
        <label class="ra-label mb-0">Window:</label>
        <select name="days" class="ra-input" style="max-width: 200px;" onchange="this.form.submit()">
            <option value="7"   <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30"  <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
            <option value="90"  <?= $days === 90 ? 'selected' : '' ?>>Last 90 days</option>
            <option value="365" <?= $days === 365 ? 'selected' : '' ?>>Last year</option>
        </select>
    </div>
</form>

<div class="ra-table-card">
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">No activity in the selected window.</div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr><th>Recruiter</th><th>Candidates</th><th>Applications</th><th>Interviews</th><th>Offers</th><th>Hires</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= (int) $r['candidates'] ?></td>
                        <td><?= (int) $r['applications'] ?></td>
                        <td><?= (int) $r['interviews'] ?></td>
                        <td><?= (int) $r['offers'] ?></td>
                        <td><strong><?= (int) $r['hires'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
