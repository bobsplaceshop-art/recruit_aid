<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Recruitment</div>
            <h1 class="ra-page-band-title">Interviews</h1>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <?php if (empty($rows)): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">No upcoming interviews.</div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Candidate</th>
                    <th>Vacancy</th>
                    <th>Scheduled</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="width:80px;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $i = $row['interview']; $c = $row['candidate']; $v = $row['vacancy']; ?>
                    <tr>
                        <td><code style="font-size:.85rem;"><?= e($i->code) ?></code></td>
                        <td><a href="/candidates/<?= e($c->code) ?>" class="text-decoration-none"><strong><?= e($c->fullName()) ?></strong></a></td>
                        <td><a href="/vacancies/<?= e($v->code) ?>" class="text-decoration-none"><?= e($v->title) ?></a></td>
                        <td class="muted small"><?= e($i->scheduledAt) ?></td>
                        <td class="muted small"><?= e($i->typeLabel()) ?></td>
                        <td><span class="<?= $i->statusBadgeClass() ?>"><?= e($i->statusLabel()) ?></span></td>
                        <td>
                            <a href="/interviews/<?= e($i->code) ?>" class="ra-row-actions"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
