<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Interview · <?= e($candidate->fullName() ?? '—') ?> · <?= e($vacancy->title ?? '—') ?>
            </div>
            <h1 class="ra-page-band-title">Schedule Interview</h1>
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

<form method="POST" action="/applications/<?= e($application->code) ?>/schedule-interview">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">When</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="ra-label">Date & Time <span class="text-danger">*</span></label>
                        <input type="text" name="scheduled_at" class="ra-input" data-datepicker value="<?= e($old['scheduled_at'] ?? '') ?>" placeholder="yyyy-mm-dd" required>
                    </div>
                    <div class="col-md-3">
                        <label class="ra-label">Duration (minutes)</label>
                        <input type="number" name="duration_minutes" class="ra-input" value="<?= e($old['duration_minutes'] ?? 60) ?>" min="15" max="480">
                    </div>
                    <div class="col-md-3">
                        <label class="ra-label">Type</label>
                        <select name="type" class="ra-input">
                            <?php foreach (['phone','video','onsite','technical','panel','final'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($old['type'] ?? 'video') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="ra-label">Location / Meeting link</label>
                        <input type="text" name="location" class="ra-input" value="<?= e($old['location'] ?? '') ?>" placeholder="Zoom link, office address, or phone number">
                    </div>
                </div>
            </div>

            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Participants</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="ra-label">Lead interviewer</label>
                        <select name="lead_user_id" class="ra-input">
                            <option value="">—</option>
                            <?php foreach ($staffUsers as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= ((int) ($old['lead_user_id'] ?? 0)) === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="ra-label">Other interviewers</label>

                        <?php
                            $preSelected = [];
                            if (is_array($old['participants'] ?? null)) {
                                foreach ($old['participants'] as $pid) {
                                    if (is_numeric($pid)) $preSelected[] = (int) $pid;
                                }
                            }
                        ?>

                        <!-- Chips of selected users -->
                        <div id="participant-chips" class="ra-chip-area mb-2">
                            <?php foreach ($staffUsers as $u): ?>
                                <?php if (in_array((int) $u['id'], $preSelected, true)): ?>
                                    <span class="ra-chip" data-user-id="<?= (int) $u['id'] ?>">
                                        <?= e($u['name']) ?>
                                        <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>
                                    </span>
                                    <input type="hidden" name="participants[]" value="<?= (int) $u['id'] ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Searchable dropdown -->
                        <div class="ra-participant-picker">
                            <input
                                type="text"
                                id="participant-search"
                                class="ra-input"
                                placeholder="Search users to add as interviewers…"
                                autocomplete="off"
                            >
                            <div id="participant-dropdown" class="ra-participant-dropdown" hidden>
                                <?php foreach ($staffUsers as $u): ?>
                                    <?php
                                        $id = (int) $u['id'];
                                        $isSelected = in_array($id, $preSelected, true);
                                    ?>
                                    <button
                                        type="button"
                                        class="ra-participant-option <?= $isSelected ? 'is-disabled' : '' ?>"
                                        data-user-id="<?= $id ?>"
                                        data-user-name="<?= e($u['name']) ?>"
                                        <?= $isSelected ? 'disabled' : '' ?>
                                    >
                                        <span class="ra-participant-name"><?= e($u['name']) ?></span>
                                        <span class="ra-participant-email muted small"><?= e($u['email']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="muted small mt-1">Type to search, click to add. Added users appear as chips above.</div>
                    </div>
                </div>
            </div>

            <div class="ra-card mb-3">
                <h2 class="h6 mb-3">Notes</h2>
                <textarea name="notes" class="ra-input" rows="3" placeholder="Anything the interviewers should know"><?= e($old['notes'] ?? '') ?></textarea>
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
            </div>
            <button type="submit" class="ra-btn ra-btn-primary w-100">
                <i class="bi bi-calendar-plus"></i> Schedule Interview
            </button>
        </div>
    </div>
</form>


<script>
(function() {
    const search = document.getElementById('participant-search');
    const dropdown = document.getElementById('participant-dropdown');
    const chips = document.getElementById('participant-chips');
    const options = Array.from(dropdown.querySelectorAll('.ra-participant-option'));

    function refreshOptionStates() {
        const selectedIds = Array.from(chips.querySelectorAll('.ra-chip')).map(c => c.dataset.userId);
        options.forEach(function(opt) {
            if (selectedIds.includes(opt.dataset.userId)) {
                opt.classList.add('is-disabled');
                opt.disabled = true;
            } else {
                opt.classList.remove('is-disabled');
                opt.disabled = false;
            }
        });
    }

    function showDropdown() {
        dropdown.hidden = false;
    }
    function hideDropdown() {
        dropdown.hidden = true;
        search.value = '';
        filterOptions('');
    }

    function filterOptions(term) {
        const q = term.toLowerCase();
        options.forEach(function(opt) {
            const name = (opt.dataset.userName || '').toLowerCase();
            opt.style.display = name.includes(q) ? '' : 'none';
        });
    }

    search.addEventListener('focus', function() {
        showDropdown();
        filterOptions(search.value);
    });
    search.addEventListener('input', function() {
        filterOptions(search.value);
        showDropdown();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ra-participant-picker') && !e.target.closest('#participant-chips')) {
            hideDropdown();
        }
    });

    dropdown.addEventListener('click', function(e) {
        const opt = e.target.closest('.ra-participant-option');
        if (!opt || opt.disabled) return;
        const id = opt.dataset.userId;
        const name = opt.dataset.userName;

        const chip = document.createElement('span');
        chip.className = 'ra-chip';
        chip.dataset.userId = id;
        chip.innerHTML = name + ' <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>';
        chips.appendChild(chip);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'participants[]';
        hidden.value = id;
        hidden.dataset.forUser = id;
        chips.appendChild(hidden);

        refreshOptionStates();
        search.value = '';
        filterOptions('');
        search.focus();
    });

    chips.addEventListener('click', function(e) {
        const remove = e.target.closest('.ra-chip-remove');
        if (!remove) return;
        const chip = remove.closest('.ra-chip');
        const id = chip.dataset.userId;
        chip.remove();
        const hidden = chips.querySelector('input[type="hidden"][data-for-user="' + id + '"]');
        if (hidden) hidden.remove();
        refreshOptionStates();
    });
})();
</script>
