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
                <?php
                    // Pre-compute selected state from old input
                    $oldLeadId = isset($old['lead_user_id']) && is_numeric($old['lead_user_id']) ? (int) $old['lead_user_id'] : 0;
                    $preSelected = [];
                    if (is_array($old['participants'] ?? null)) {
                        foreach ($old['participants'] as $pid) {
                            if (is_numeric($pid)) $preSelected[] = (int) $pid;
                        }
                    }
                    // If a lead was set, ensure it's not in the preSelected list
                    if ($oldLeadId > 0) {
                        $preSelected = array_values(array_filter($preSelected, static fn(int $x): bool => $x !== $oldLeadId));
                    }
                ?>
                <div class="row g-3">
                    <!-- Lead interviewer (single chip picker) -->
                    <div class="col-md-6">
                        <label class="ra-label">Lead interviewer</label>

                        <div id="lead-chips" class="ra-chip-area mb-2">
                            <?php if ($oldLeadId > 0): ?>
                                <?php foreach ($staffUsers as $u): ?>
                                    <?php if ((int) $u['id'] === $oldLeadId): ?>
                                        <span class="ra-chip" data-user-id="<?= (int) $u['id'] ?>" data-role="lead">
                                            <?= e($u['name']) ?>
                                            <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>
                                        </span>
                                        <input type="hidden" name="lead_user_id" value="<?= (int) $u['id'] ?>" data-for-lead="1">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="ra-participant-picker" id="lead-picker">
                            <input
                                type="text"
                                id="lead-search"
                                class="ra-input"
                                placeholder="Search for the lead interviewer…"
                                autocomplete="off"
                            >
                            <div id="lead-dropdown" class="ra-participant-dropdown" hidden>
                                <?php foreach ($staffUsers as $u): ?>
                                    <?php
                                        $id = (int) $u['id'];
                                        $isLead = ($id === $oldLeadId);
                                    ?>
                                    <button
                                        type="button"
                                        class="ra-participant-option <?= $isLead ? 'is-disabled' : '' ?>"
                                        data-user-id="<?= $id ?>"
                                        data-user-name="<?= e($u['name']) ?>"
                                        data-picker="lead"
                                        <?= $isLead ? 'disabled' : '' ?>
                                    >
                                        <span class="ra-participant-name"><?= e($u['name']) ?></span>
                                        <span class="ra-participant-email muted small"><?= e($u['email']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="muted small mt-1">Search and click to choose one lead.</div>
                    </div>

                    <!-- Other interviewers (multi-chip picker) -->
                    <div class="col-md-6">
                        <label class="ra-label">Other interviewers</label>

                        <div id="participant-chips" class="ra-chip-area mb-2">
                            <?php foreach ($staffUsers as $u): ?>
                                <?php if (in_array((int) $u['id'], $preSelected, true)): ?>
                                    <span class="ra-chip" data-user-id="<?= (int) $u['id'] ?>" data-role="other">
                                        <?= e($u['name']) ?>
                                        <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>
                                    </span>
                                    <input type="hidden" name="participants[]" value="<?= (int) $u['id'] ?>" data-for-user="<?= (int) $u['id'] ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="ra-participant-picker" id="others-picker">
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
                                        $isSelected = in_array($id, $preSelected, true) || ($id === $oldLeadId);
                                    ?>
                                    <button
                                        type="button"
                                        class="ra-participant-option <?= $isSelected ? 'is-disabled' : '' ?>"
                                        data-user-id="<?= $id ?>"
                                        data-user-name="<?= e($u['name']) ?>"
                                        data-picker="others"
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
    // ---- Shared state ----
    const leadChips   = document.getElementById('lead-chips');
    const otherChips  = document.getElementById('participant-chips');

    // ---- Utility: get all currently selected user IDs (lead + others) ----
    function selectedUserIds() {
        const lead = leadChips.querySelector('.ra-chip');
        const others = Array.from(otherChips.querySelectorAll('.ra-chip'));
        const ids = others.map(c => String(c.dataset.userId));
        if (lead) ids.push(String(lead.dataset.userId));
        return ids;
    }

    // ---- Utility: refresh all picker options' disabled state ----
    function refreshAllPickerStates() {
        const ids = selectedUserIds();
        document.querySelectorAll('.ra-participant-option').forEach(function(opt) {
            const isSelected = ids.includes(String(opt.dataset.userId));
            if (isSelected) {
                opt.classList.add('is-disabled');
                opt.disabled = true;
            } else {
                opt.classList.remove('is-disabled');
                opt.disabled = false;
            }
        });
    }

    // ---- Generic single-chip picker (used for lead) ----
    function setupSinglePicker(opts) {
        const search     = document.getElementById(opts.searchId);
        const dropdown   = document.getElementById(opts.dropdownId);
        const chipsEl    = document.getElementById(opts.chipsId);
        const hiddenName = opts.hiddenName;
        const isLead     = (hiddenName === 'lead_user_id');

        function filterOptions(term) {
            const q = term.toLowerCase();
            dropdown.querySelectorAll('.ra-participant-option').forEach(function(opt) {
                const name = (opt.dataset.userName || '').toLowerCase();
                opt.style.display = name.includes(q) ? '' : 'none';
            });
        }

        function closeDropdown() {
            dropdown.hidden = true;
            search.value = '';
            filterOptions('');
        }

        search.addEventListener('focus', function() {
            dropdown.hidden = false;
            filterOptions(search.value);
        });
        search.addEventListener('input', function() {
            dropdown.hidden = false;
            filterOptions(search.value);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#' + opts.pickerId)) {
                closeDropdown();
            }
        });

        dropdown.addEventListener('click', function(e) {
            const opt = e.target.closest('.ra-participant-option');
            if (!opt || opt.disabled) return;
            const id = opt.dataset.userId;
            const name = opt.dataset.userName;

            // Clear any existing chip in this picker (single-select)
            chipsEl.innerHTML = '';

            const chip = document.createElement('span');
            chip.className = 'ra-chip';
            chip.dataset.userId = id;
            chip.dataset.role = isLead ? 'lead' : 'other';
            chip.innerHTML = name + ' <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>';
            chipsEl.appendChild(chip);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = hiddenName;
            hidden.value = id;
            if (isLead) {
                hidden.dataset.forLead = '1';
            }
            chipsEl.appendChild(hidden);

            refreshAllPickerStates();
            closeDropdown();
        });

        chipsEl.addEventListener('click', function(e) {
            const remove = e.target.closest('.ra-chip-remove');
            if (!remove) return;
            const chip = remove.closest('.ra-chip');
            chip.remove();
            const hidden = chipsEl.querySelector('input[type="hidden"]');
            if (hidden) hidden.remove();
            refreshAllPickerStates();
        });
    }

    // ---- Multi-chip picker (used for others) ----
    function setupMultiPicker(opts) {
        const search   = document.getElementById(opts.searchId);
        const dropdown = document.getElementById(opts.dropdownId);
        const chipsEl  = document.getElementById(opts.chipsId);

        function filterOptions(term) {
            const q = term.toLowerCase();
            dropdown.querySelectorAll('.ra-participant-option').forEach(function(opt) {
                const name = (opt.dataset.userName || '').toLowerCase();
                opt.style.display = name.includes(q) ? '' : 'none';
            });
        }

        function closeDropdown() {
            dropdown.hidden = true;
            search.value = '';
            filterOptions('');
        }

        search.addEventListener('focus', function() {
            dropdown.hidden = false;
            filterOptions(search.value);
        });
        search.addEventListener('input', function() {
            dropdown.hidden = false;
            filterOptions(search.value);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#' + opts.pickerId)) {
                closeDropdown();
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
            chip.dataset.role = 'other';
            chip.innerHTML = name + ' <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>';
            chipsEl.appendChild(chip);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'participants[]';
            hidden.value = id;
            hidden.dataset.forUser = id;
            chipsEl.appendChild(hidden);

            refreshAllPickerStates();
            closeDropdown();
            search.focus();
        });

        chipsEl.addEventListener('click', function(e) {
            const remove = e.target.closest('.ra-chip-remove');
            if (!remove) return;
            const chip = remove.closest('.ra-chip');
            const id = chip.dataset.userId;
            chip.remove();
            const hidden = chipsEl.querySelector('input[type="hidden"][data-for-user="' + id + '"]');
            if (hidden) hidden.remove();
            refreshAllPickerStates();
        });
    }

    // ---- Wire both pickers ----
    setupSinglePicker({
        pickerId:   'lead-picker',
        searchId:   'lead-search',
        dropdownId: 'lead-dropdown',
        chipsId:    'lead-chips',
        hiddenName: 'lead_user_id'
    });

    setupMultiPicker({
        pickerId:   'others-picker',
        searchId:   'participant-search',
        dropdownId: 'participant-dropdown',
        chipsId:    'participant-chips'
    });

    refreshAllPickerStates();
})();
</script>
