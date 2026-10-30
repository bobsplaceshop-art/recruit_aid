<script>
(function () {
    'use strict';

    function initPicker(picker) {
        const pickerId   = picker.dataset.pickerId;
        const mode       = picker.dataset.mode || 'multi';
        const chipsEl    = picker.querySelector('[data-chips]');
        const search     = picker.querySelector('[data-search]');
        const dropdown   = picker.querySelector('[data-dropdown]');
        const hiddenName = picker.dataset.name || 'picker_ids[]';

        if (!chipsEl || !search || !dropdown) return;

        function currentIds() {
            return Array.from(chipsEl.querySelectorAll('.ra-chip')).map(c => String(c.dataset.id));
        }

        function refreshDisabled() {
            const ids = currentIds();
            dropdown.querySelectorAll('.ra-participant-option').forEach(function (opt) {
                const isSel = ids.includes(String(opt.dataset.id));
                opt.classList.toggle('is-disabled', isSel);
                opt.disabled = isSel;
            });
        }

        function filter(term) {
            const q = term.toLowerCase();
            dropdown.querySelectorAll('.ra-participant-option').forEach(function (opt) {
                const label = (opt.dataset.label || '').toLowerCase();
                opt.style.display = label.includes(q) ? '' : 'none';
            });
        }

        function close() {
            dropdown.hidden = true;
            search.value = '';
            filter('');
        }

        search.addEventListener('focus', function () {
            dropdown.hidden = false;
            filter(search.value);
        });
        search.addEventListener('input', function () {
            dropdown.hidden = false;
            filter(search.value);
        });

        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) close();
        });

        dropdown.addEventListener('click', function (e) {
            const opt = e.target.closest('.ra-participant-option');
            if (!opt || opt.disabled) return;
            const id    = opt.dataset.id;
            const label = opt.dataset.label;

            if (mode === 'single') {
                // Clear existing chip + hidden input before adding
                chipsEl.querySelectorAll('.ra-chip').forEach(c => c.remove());
                chipsEl.querySelectorAll('input[type="hidden"]').forEach(h => h.remove());
            }

            const chip = document.createElement('span');
            chip.className = 'ra-chip';
            chip.dataset.id = id;
            chip.innerHTML = label + ' <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>';
            chipsEl.appendChild(chip);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = hiddenName || 'picker_ids[]';
            hidden.value = id;
            hidden.dataset.forId = id;
            chipsEl.appendChild(hidden);

            refreshDisabled();
            close();
        });

        chipsEl.addEventListener('click', function (e) {
            const remove = e.target.closest('.ra-chip-remove');
            if (!remove) return;
            const chip = remove.closest('.ra-chip');
            const id = chip.dataset.id;
            chip.remove();
            const hidden = chipsEl.querySelector('input[type="hidden"][data-for-id="' + id + '"]');
            if (hidden) hidden.remove();
            refreshDisabled();
        });

        refreshDisabled();
    }

    document.querySelectorAll('.ra-chip-picker').forEach(initPicker);

    // ---- Form submit guard for required pickers ----
    // Every form containing a required picker must have at least one
    // chip before submission. On failure we prevent the submit,
    // highlight the empty picker, and show a message.
    function guardRequiredPickers() {
        const forms = new Set();
        document.querySelectorAll('.ra-chip-picker[data-required="1"]').forEach(function (p) {
            if (p.closest('form')) forms.add(p.closest('form'));
        });

        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const requiredPickers = form.querySelectorAll('.ra-chip-picker[data-required="1"]');
                let firstMissing = null;

                requiredPickers.forEach(function (p) {
                    const chips = p.querySelectorAll('[data-chips] .ra-chip');
                    p.classList.remove('ra-chip-picker--invalid');
                    if (chips.length === 0 && !firstMissing) {
                        firstMissing = p;
                    }
                });

                if (firstMissing) {
                    e.preventDefault();
                    firstMissing.classList.add('ra-chip-picker--invalid');
                    firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const search = firstMissing.querySelector('[data-search]');
                    if (search) search.focus();

                    // Clear the invalid state when the user starts picking
                    const observer = new MutationObserver(function () {
                        const chips = firstMissing.querySelectorAll('[data-chips] .ra-chip');
                        if (chips.length > 0) {
                            firstMissing.classList.remove('ra-chip-picker--invalid');
                            observer.disconnect();
                        }
                    });
                    const chipsContainer = firstMissing.querySelector('[data-chips]');
                    if (chipsContainer) {
                        observer.observe(chipsContainer, { childList: true });
                    }
                }
            });
        });
    }

    guardRequiredPickers();
})();
</script>
