<?php
use CorporateAid\Core\App;
$isParticipant = false;
if ($user !== null) {
    $repo = App::instance()->container()->get(\CorporateAid\Recruit\Repositories\InterviewRepository::class);
    $isParticipant = $repo->isParticipant($interview->id, $user->id);
}
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Interview · <code><?= e($interview->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($candidate->fullName() ?? '—') ?></h1>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="<?= $interview->statusBadgeClass() ?>"><?= e($interview->statusLabel()) ?></span>
                <span class="ra-badge"><?= e($interview->typeLabel()) ?></span>
                <span class="muted small">· <?= e($interview->scheduledAt) ?> (<?= (int) $interview->durationMinutes ?> min)</span>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/recruit/interviews" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
            <?php if ($interview->isScheduled() && $user->can('recruit.interviews.edit')): ?>
                <form method="POST" action="/interviews/<?= e($interview->code) ?>/complete" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-primary">Mark Completed</button>
                </form>
            <?php endif; ?>
            <?php if ($interview->isScheduled() && $user->can('recruit.interviews.delete')): ?>
                <form method="POST" action="/interviews/<?= e($interview->code) ?>/cancel" style="display:inline;" onsubmit="return confirm('Cancel this interview?');">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-outline text-danger">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Participants</h2>
            <?php if (empty($participants)): ?>
                <div class="muted small">No participants.</div>
            <?php else: ?>
                <ul class="mb-0" style="padding-left:1.2rem;">
                    <?php foreach ($participants as $p): ?>
                        <li>
                            <strong><?= e($p['user']->name) ?></strong>
                            <span class="muted small">· <?= e($p['participant']->role) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($interview->location): ?>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Location / Link</h2>
            <div><?= e($interview->location) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($interview->notes): ?>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Notes</h2>
            <div><?= nl2br(e($interview->notes)) ?></div>
        </div>
        <?php endif; ?>

        <div class="ra-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Scorecards</h2>
                <?php if ($isParticipant && $user->can('recruit.scorecards.submit')): ?>
                    <a href="/interviews/<?= e($interview->code) ?>/scorecard" class="ra-btn ra-btn-primary ra-btn-sm">
                        <i class="bi bi-pencil-square"></i> Submit scorecard
                    </a>
                <?php endif; ?>
            </div>
            <?php if (empty($scorecards)): ?>
                <div class="muted small">No scorecards submitted yet.</div>
            <?php else: ?>
                <?php foreach ($scorecards as $scData): ?>
                    <?php $sc = $scData['scorecard']; ?>
                    <?php if (!$sc->isSubmitted() && ($user === null || $sc->interviewerId !== $user->id)) continue; ?>
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= e($scData['user']->name ?? 'Unknown') ?></strong>
                                <?php if ($sc->isSubmitted()): ?>
                                    <span class="<?= $sc->recommendationBadgeClass() ?> ms-2"><?= e($sc->recommendationLabel()) ?></span>
                                <?php else: ?>
                                    <span class="ra-badge ms-2">Draft</span>
                                <?php endif; ?>
                            </div>
                            <div class="muted small"><?= $sc->isSubmitted() ? 'Submitted ' . e(human_time($sc->submittedAt ?? '')) : '' ?></div>
                        </div>
                        <?php if (!empty($scData['attributes'])): ?>
                            <table class="ra-table" style="font-size:.85rem;">
                                <thead><tr><th>Competency</th><th style="width:60px;">Score</th><th>Evidence</th></tr></thead>
                                <tbody>
                                <?php foreach ($scData['attributes'] as $attr): ?>
                                    <tr>
                                        <td><?= e($attr->competencyName) ?></td>
                                        <td><strong><?= (int) $attr->score ?></strong> / 5</td>
                                        <td class="muted small"><?= e($attr->evidence ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if ($sc->notes): ?>
                            <div class="mt-2"><strong>Notes:</strong> <?= nl2br(e($sc->notes)) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Candidate</h2>
            <a href="/candidates/<?= e($candidate->code ?? '') ?>" class="text-decoration-none"><strong><?= e($candidate->fullName() ?? '—') ?></strong></a>
            <div class="muted small"><?= e($candidate->email ?? '') ?></div>
        </div>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Vacancy</h2>
            <a href="/vacancies/<?= e($vacancy->code ?? '') ?>" class="text-decoration-none"><strong><?= e($vacancy->title ?? '—') ?></strong></a>
            <div class="muted small"><?= e($vacancy->code ?? '') ?></div>
        </div>
        <div class="ra-card">
            <h2 class="h6 mb-3">Application</h2>
            <code><?= e($application->code ?? '') ?></code>
        </div>
    </div>
</div>
