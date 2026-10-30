<?php
use CorporateAid\Core\Row;
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Offer · <code><?= e($offer->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($offer->salaryFormatted()) ?></h1>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="<?= $offer->statusBadgeClass() ?>"><?= e($offer->statusLabel()) ?></span>
                <?php if ($candidate !== null): ?>
                    <span class="muted small">· <?= e($candidate->fullName()) ?></span>
                <?php endif; ?>
                <?php if ($vacancy !== null): ?>
                    <span class="muted small">· <?= e($vacancy->title) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/offers" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back</a>
            <?php if ($offer->isDraft() && $user->can('recruit.offers.send')): ?>
                <form method="POST" action="/offers/<?= e($offer->code) ?>/send" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-primary"><i class="bi bi-send"></i> Send to candidate</button>
                </form>
            <?php endif; ?>
            <?php if ($offer->status === 'sent' && $user->can('recruit.offers.respond')): ?>
                <form method="POST" action="/offers/<?= e($offer->code) ?>/accept" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-primary"><i class="bi bi-check2-circle"></i> Accept</button>
                </form>
                <form method="POST" action="/offers/<?= e($offer->code) ?>/decline" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-outline text-danger"><i class="bi bi-x-circle"></i> Decline</button>
                </form>
            <?php endif; ?>
            <?php if (!$offer->isTerminal() && $user->can('recruit.offers.edit')): ?>
                <form method="POST" action="/offers/<?= e($offer->code) ?>/withdraw" style="display:inline;" onsubmit="return confirm('Withdraw this offer?');">
                    <?= csrf_field() ?>
                    <button class="ra-btn ra-btn-outline text-danger"><i class="bi bi-slash-circle"></i> Withdraw</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Offer details</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Offered salary</div>
                    <div><strong><?= e($offer->salaryFormatted()) ?></strong></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Status</div>
                    <div><span class="<?= $offer->statusBadgeClass() ?>"><?= e($offer->statusLabel()) ?></span></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Start date</div>
                    <div><?= e($offer->startDate) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Expires at</div>
                    <div><?= e($offer->expiresAt) ?></div>
                </div>
                <?php if ($offer->sentAt): ?>
                <div class="col-md-6">
                    <div class="muted small">Sent at</div>
                    <div><?= e($offer->sentAt) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($offer->respondedAt): ?>
                <div class="col-md-6">
                    <div class="muted small">Responded at</div>
                    <div><?= e($offer->respondedAt) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($offer->notes): ?>
                <hr>
                <div class="muted small">Notes</div>
                <div><?= nl2br(e($offer->notes)) ?></div>
           
<?php endif; ?>
            <?php if ($offer->responseNotes): ?>
                <hr>
                <div class="muted small">Response notes</div>
                <div><?= nl2br(e($offer->responseNotes)) ?></div>
            <?php endif; ?>
        </div>

        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">History</h2>
            <?php if (empty($history)): ?>
                <div class="muted small">No history.</div>
            <?php else: ?>
                <div class="ra-timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="ra-timeline-item">
                            <div class="ra-timeline-dot"></div>
                            <div class="ra-timeline-body">
                                <div class="ra-timeline-text">
                                    <strong><?= e($h->fromStatus ?? 'new') ?></strong>
                                    <span class="muted">→</span>
                                    <strong><?= e($h->toStatus) ?></strong>
                                </div>
                                <div class="ra-timeline-time muted small">
                                    <?= e(human_time($h->changedAt)) ?>
                                    <?php if ($h->notes): ?> · <?= e($h->notes) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($candidate !== null): ?>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Candidate</h2>
            <a href="/candidates/<?= e($candidate->code) ?>" class="text-decoration-none"><strong><?= e($candidate->fullName()) ?></strong></a>
            <div class="muted small"><?= e($candidate->email) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($vacancy !== null): ?>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Vacancy</h2>
            <a href="/vacancies/<?= e($vacancy->code) ?>" class="text-decoration-none"><strong><?= e($vacancy->title) ?></strong></a>
            <div class="muted small"><?= e($vacancy->code) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($application !== null): ?>
        <div class="ra-card">
            <h2 class="h6 mb-3">Application</h2>
            <code><?= e($application->code) ?></code>
        </div>
        <?php endif; ?>
    </div>
</div>
