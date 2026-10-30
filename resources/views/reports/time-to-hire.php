<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Insights · Time to Hire</div>
            <h1 class="ra-page-band-title">Time to Hire</h1>
            <p class="ra-page-sub">Average days from application to hired, per vacancy.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/reports" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
            <a href="/reports/time-to-hire.csv" class="ra-btn ra-btn-primary"><i class="bi bi-file-earmark-arrow-down"></i> Export CSV</a>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">No hires recorded yet. Time-to-hire will appear once the first offer is accepted.</div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr><th>Vacancy</th><th>Hired</th><th>Avg Days to Hire</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['vacancy_title']) ?></strong>
                            <div class="muted small"><code><?= e($r['vacancy_code']) ?></code></div>
                        </td>
                        <td><?= (int) $r['hired_count'] ?></td>
                        <td><?= $r['avg_days'] > 0 ? (string) $r['avg_days'] . ' d' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
