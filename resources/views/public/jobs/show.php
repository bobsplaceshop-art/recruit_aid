<?php /** @var \CorporateAid\Recruit\Models\Vacancy $vacancy */ ?>

<a href="/jobs" class="text-muted small">&larr; All open roles</a>

<h1 class="mt-3 mb-2"><?= e($vacancy->title) ?></h1>

<p class="text-muted mb-4">
    <?= e($vacancy->city ?? '') ?><?= $vacancy->countryCode ? ', ' . e($vacancy->countryCode) : '' ?>
    · <?= e(ucfirst($vacancy->workMode)) ?>
    · <?= e(ucfirst($vacancy->employmentType)) ?>
    · <?= e(ucfirst($vacancy->seniority)) ?>
</p>

<?php if ($vacancy->salaryMin !== null || $vacancy->salaryMax !== null): ?>
    <p><strong>Salary:</strong>
        <?= $vacancy->currency ? e($vacancy->currency) . ' ' : '' ?>
        <?= $vacancy->salaryMin !== null ? number_format($vacancy->salaryMin) : '' ?>
        <?= ($vacancy->salaryMin !== null && $vacancy->salaryMax !== null) ? ' – ' : '' ?>
        <?= $vacancy->salaryMax !== null ? number_format($vacancy->salaryMax) : '' ?>
    </p>
<?php endif; ?>

<?php if ($vacancy->description): ?>
    <section class="mt-4">
        <h2 class="h5">About the role</h2>
        <div><?= nl2br(e($vacancy->description)) ?></div>
    </section>
<?php endif; ?>

<?php if ($vacancy->responsibilities): ?>
    <section class="mt-4">
        <h2 class="h5">Responsibilities</h2>
        <div><?= nl2br(e($vacancy->responsibilities)) ?></div>
    </section>
<?php endif; ?>

<?php if ($vacancy->requirements): ?>
    <section class="mt-4">
        <h2 class="h5">Requirements</h2>
        <div><?= nl2br(e($vacancy->requirements)) ?></div>
    </section>
<?php endif; ?>

<?php if (!empty($vacancy->skills)): ?>
    <section class="mt-4">
        <h2 class="h5">Skills</h2>
        <p><?php foreach ($vacancy->skills as $skill): ?>
            <span class="badge bg-light text-dark border"><?= e((string) $skill) ?></span>
        <?php endforeach; ?></p>
    </section>
<?php endif; ?>

<div class="mt-5">
    <a href="/jobs/<?= e($vacancy->code) ?>/apply" class="btn btn-brand btn-lg">
        Apply for this role
    </a>
</div>
