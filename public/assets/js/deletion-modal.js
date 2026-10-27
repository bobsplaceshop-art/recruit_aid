/* ============================================================
   Recruit Aid — Deletion confirmation modal
   Single source of truth: the DOM.
   The form's submit event is handled here, not inline.
   ============================================================ */

window.RA_DeletionModal = (function () {
    'use strict';

    function norm(s) {
        return String(s || '')
            .toUpperCase()
            .replace(/\u00a0/g, ' ')
            .replace(/\u200b/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function phraseFromDOM() {
        var el = document.getElementById('ra-delete-modal-phrase');
        return el ? el.textContent : '';
    }

    function inputValue() {
        var el = document.getElementById('ra-delete-modal-input');
        return el ? el.value : '';
    }

    function isMatch() {
        var typed = norm(inputValue());
        var phrase = norm(phraseFromDOM());
        return phrase !== '' && typed === phrase;
    }

    function refreshButton() {
        var btn = document.getElementById('ra-delete-modal-submit');
        if (btn) btn.disabled = !isMatch();
    }

    function open(config) {
        var modal = document.getElementById('ra-delete-modal');
        if (!modal) return;

        // Reset UI
        document.getElementById('ra-delete-modal-label').textContent = config.entityLabel || '';
        document.getElementById('ra-delete-modal-phrase').textContent = '…';
        document.getElementById('ra-delete-modal-csrf').value = config.csrfToken || '';

        var input = document.getElementById('ra-delete-modal-input');
        input.value = '';
        refreshButton();

        document.getElementById('ra-delete-modal-form').action = config.submitUrl;

        modal.style.display = 'flex';
        setTimeout(function () { input.focus(); }, 100);

        fetch(config.infoUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                alert(data.message || 'Unable to load deletion info.');
                close();
                return;
            }

            document.getElementById('ra-delete-modal-phrase').textContent = data.required_phrase || 'DELETE';

            var depsBox = document.getElementById('ra-delete-modal-deps');
            var depsList = document.getElementById('ra-delete-modal-deps-list');
            depsList.innerHTML = '';
            if (data.has_dependents && data.dependents && data.dependents.length > 0) {
                data.dependents.forEach(function (d) {
                    var li = document.createElement('li');
                    li.textContent = d.count + ' ' + d.label;
                    depsList.appendChild(li);
                });
                depsBox.style.display = '';
            } else {
                depsBox.style.display = 'none';
            }

            // Re-check button now that phrase is loaded
            refreshButton();
        })
        .catch(function (err) {
            alert('Failed to load deletion info: ' + err.message);
            close();
        });
    }

    function close() {
        var modal = document.getElementById('ra-delete-modal');
        if (modal) modal.style.display = 'none';
    }

    // ---------- Attach listeners once ----------
    function attach() {
        var input = document.getElementById('ra-delete-modal-input');
        var form = document.getElementById('ra-delete-modal-form');

        if (input) {
            input.addEventListener('input', refreshButton);
            input.addEventListener('keyup', refreshButton);
            input.addEventListener('paste', function () {
                setTimeout(refreshButton, 0);
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                if (!isMatch()) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert('Confirmation phrase does not match. Please type exactly: ' + phraseFromDOM());
                    return false;
                }
                // else: let the native submit proceed
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('ra-delete-modal');
            if (modal && modal.style.display !== 'none') close();
        }
    });

    document.addEventListener('click', function (e) {
        var modal = document.getElementById('ra-delete-modal');
        if (modal && e.target === modal) close();
    });

    return { open: open, close: close };
})();
