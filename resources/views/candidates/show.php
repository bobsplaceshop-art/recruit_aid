<?php
/**
 * Candidate detail page.
 *
 * $candidate — Candidate
 * $skills    — CandidateSkill[]
 * $experience — CandidateExperience[]
 * $education — CandidateEducation[]
 * $tags      — CandidateTag[]
 */
use CorporateAid\Core\App;
use CorporateAid\Core\Row;

$activityHtml = App::instance()->container()->get(\CorporateAid\Platform\Services\ActivityTimelineService::class)->render('candidate', $candidate->id);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Recruitment · <a href="/candidates" class="text-decoration-none">Candidates</a> · <code><?= e($candidate->code) ?></code>
            </div>
            <h1 class="ra-page-band-title"><?= e($candidate->fullName()) ?></h1>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <span class="<?= $candidate->statusBadgeClass() ?>"><?= e($candidate->statusLabel()) ?></span>
                <?php if ($candidate->seniority): ?>
                    <span class="ra-badge"><?= e($candidate->seniorityLabel()) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ra-page-band-actions">
            <a href="/candidates" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <?php if ($user->can('recruit.candidates.edit')): ?>
                <a href="/candidates/<?= e($candidate->code) ?>/edit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left Column -->
    <div class="col-lg-8">
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Profile</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Email</div>
                    <div><a href="mailto:<?= e($candidate->email) ?>"><?= e($candidate->email) ?></a></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Phone</div>
                    <div><?= e($candidate->phone ?? '—') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Location</div>
                    <div><?= e(trim(($candidate->city ?? '') . ', ' . ($candidate->countryCode ?? ''), ', ')) ?: '—' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Current Role</div>
                    <div><?= e($candidate->currentJobTitle ?? '—') ?> <?= $candidate->currentCompany ? 'at ' . e($candidate->currentCompany) : '' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Experience</div>
                    <div><?= $candidate->yearsExperience !== null ? (int) $candidate->yearsExperience . ' years' : '—' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="muted small">Source</div>
                    <div><?= e($candidate->source ?? '—') ?></div>
                </div>
            </div>
            <?php if ($candidate->summary): ?>
                <hr>
                <div class="muted small">Summary</div>
                <div><?= nl2br(e($candidate->summary)) ?></div>
            <?php endif; ?>
        </div>

        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Skills</h2>
            <?php if (empty($skills)): ?>
                <div class="muted small">No skills listed.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($skills as $s): ?>
                        <span class="ra-badge"><?= e($s->skillName) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Experience</h2>
            <?php if (empty($experience)): ?>
                <div class="muted small">No experience listed.</div>
            <?php else: ?>
                <div class="ra-timeline">
                    <?php foreach ($experience as $e): ?>
                        <div class="ra-timeline-item">
                            <div class="ra-timeline-dot"></div>
                            <div class="ra-timeline-body">
                                <div class="fw-bold"><?= e($e->jobTitle) ?> <span class="muted fw-normal">at <?= e($e->company) ?></span></div>
                                <div class="muted small">
                                    <?= e($e->startDate ?? '?') ?> – <?= $e->isCurrent ? 'Present' : e($e->endDate ?? '?') ?>
                                </div>
                                <?php if ($e->description): ?>
                                    <div class="mt-1"><?= nl2br(e($e->description)) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Education</h2>
            <?php if (empty($education)): ?>
                <div class="muted small">No education listed.</div>
            <?php else: ?>
                <?php foreach ($education as $e): ?>
                    <div class="mb-3">
                        <div class="fw-bold"><?= e($e->institution) ?></div>
                        <div class="muted small">
                            <?= e($e->degree ?? '') ?> <?= $e->fieldOfStudy ? 'in ' . e($e->fieldOfStudy) : '' ?>
                            <?= $e->endYear ? '(' . (int) $e->endYear . ')' : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        
        <!-- Applications Card -->
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Applications</h2>
            <?php
                $appRepo = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Recruit\Repositories\ApplicationRepository::class);
                $vacRepo = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Recruit\Repositories\VacancyRepository::class);
                $candApps = $appRepo->forCandidate($candidate->id);
            ?>
            <?php if (empty($candApps)): ?>
                <div class="muted small">No applications yet.</div>
            <?php else: ?>
                <div class="ra-timeline">
                    <?php foreach ($candApps as $a): ?>
                        <?php $v = $vacRepo->findById($a->vacancyId); ?>
                        <?php if ($v === null) continue; ?>
                        <div class="ra-timeline-item">
                            <div class="ra-timeline-dot"></div>
                            <div class="ra-timeline-body">
                                <div class="ra-timeline-text">
                                    <a href="/vacancies/<?= e($v->code) ?>" class="fw-bold text-decoration-none"><?= e($v->title) ?></a>
                                    <span class="muted">·</span>
                                    <span class="ra-badge"><?= e($a->statusLabel()) ?></span>
                                </div>
                                <div class="ra-timeline-time muted small">
                                    Applied <?= e(human_time($a->appliedAt)) ?> · <code style="font-size:.7rem;"><?= e($a->code) ?></code>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

<?= $activityHtml ?>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Links</h2>
            <div class="mb-2">
                <div class="muted small">LinkedIn</div>
                <?php if ($candidate->linkedinUrl): ?>
                    <a href="<?= e($candidate->linkedinUrl) ?>" target="_blank" rel="noopener"><?= e($candidate->linkedinUrl) ?></a>
                <?php else: ?>—<?php endif; ?>
            </div>
            <div class="mb-2">
                <div class="muted small">Portfolio</div>
                <?php if ($candidate->portfolioUrl): ?>
                    <a href="<?= e($candidate->portfolioUrl) ?>" target="_blank" rel="noopener"><?= e($candidate->portfolioUrl) ?></a>
                <?php else: ?>—<?php endif; ?>
            </div>
        </div>

        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Tags</h2>
            <?php if (empty($tags)): ?>
                <div class="muted small">No tags.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tags as $t): ?>
                        <span class="ra-badge"><?= e($t->tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($candidate->internalNotes && $user->can('recruit.candidates.edit')): ?>
        <div class="ra-card mb-3">
            <h2 class="h6 mb-3">Internal Notes</h2>
            <div><?= nl2br(e($candidate->internalNotes)) ?></div>
        </div>
        <?php endif; ?>
        
        <div class="ra-card">
            <h2 class="h6 mb-3">Actions</h2>
            <?php if ($user->can('recruit.candidates.delete')): ?>
                <?php if ($candidate->isDeleted()): ?>
                    <form method="POST" action="/candidates/<?= e($candidate->code) ?>/restore">
                        <?= csrf_field() ?>
                        <button class="ra-btn ra-btn-outline w-100">Restore Candidate</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="/candidates/<?= e($candidate->code) ?>/archive" onsubmit="return confirm('Archive this candidate?');">
                        <?= csrf_field() ?>
                        <button class="ra-btn ra-btn-outline text-danger w-100">Archive Candidate</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
