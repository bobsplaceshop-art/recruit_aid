/* ============================================================
   Recruit Aid — Universal Live Search
   Attaches to <input data-live-search="entity-slug">
   Fetches /api/live-search?entity=...&q=...
   Replaces [data-live-results] innerHTML with returned rows
   ============================================================ */

(function () {
    'use strict';

    var DEBOUNCE_MS = 300;
    var MIN_CHARS = 2;

    document.querySelectorAll('[data-live-search]').forEach(function (input) {
        var entity = input.getAttribute('data-live-search');
        if (!entity) return;

        var resultsEl = document.querySelector('[data-live-results]');
        if (!resultsEl) return;

        var timer = null;
        var lastQuery = input.value.trim();

        function fetchAndRender(term) {
            var url = '/api/live-search?entity=' + encodeURIComponent(entity)
                    + '&q=' + encodeURIComponent(term);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.rows) return;
                    var html = '';
                    data.rows.forEach(function (row) {
                        html += row.html || '';
                    });
                    if (html === '') {
                        html = '<tr><td colspan="10" class="text-center muted" style="padding:2rem;">No results for "' + escapeHtml(term) + '"</td></tr>';
                    }
                    resultsEl.innerHTML = html;
                })
                .catch(function () { /* silent */ });
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term === lastQuery) return;
            timer = setTimeout(function () {
                lastQuery = term;
                if (term.length === 0 || term.length >= MIN_CHARS) {
                    fetchAndRender(term);
                    // Update URL without reload
                    var u = new URL(window.location.href);
                    if (term === '') u.searchParams.delete('q');
                    else u.searchParams.set('q', term);
                    window.history.replaceState({}, '', u.toString());
                }
            }, DEBOUNCE_MS);
        });
    });
})();
