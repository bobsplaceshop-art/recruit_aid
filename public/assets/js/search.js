/* ============================================================
   Recruit Aid — Global Search Autocomplete
   - Debounced fetch to /api/search as the user types
   - Dropdown with grouped results (Organisations, Contacts,
     Engagements, Vacancies)
   - Click a result → navigate
   - Enter → go to /search?q=term (full page)
   - Escape → close dropdown
   - Arrow keys → navigate
   - Click outside → close dropdown
   ============================================================ */

(function () {
    'use strict';

    var form = document.querySelector('.ra-search-wrap form');
    if (!form) return;

    var input = form.querySelector('input[name="q"]');
    var dropdown = document.querySelector('.ra-search-dropdown');
    if (!input || !dropdown) return;

    var DEBOUNCE_MS = 250;
    var MIN_CHARS = 2;
    var debounceTimer = null;
    var lastQuery = '';
    var currentIndex = -1;

    function openDropdown() {
        dropdown.hidden = false;
        dropdown.classList.add('is-open');
    }

    function closeDropdown() {
        dropdown.hidden = true;
        dropdown.classList.remove('is-open');
        dropdown.innerHTML = '';
        currentIndex = -1;
    }

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderResults(data) {
        var term = (data.term || '').trim();
        if (!term || term.length < MIN_CHARS || !data.groups || data.groups.length === 0) {
            closeDropdown();
            return;
        }

        var html = '';
        var totalShown = 0;

        data.groups.forEach(function (group) {
            if (!group.items || group.items.length === 0) return;

            html += '<div class="ra-search-group">';
            html += '<div class="ra-search-group-label">' + escapeHtml(group.label) + '</div>';

            group.items.forEach(function (item) {
                html += '<a href="' + escapeHtml(item.url) + '" class="ra-search-result" data-nav-item>';
                html += '<div class="ra-search-result-main">';
                html += '<div class="ra-search-result-title">' + escapeHtml(item.title) + '</div>';
                html += '<div class="ra-search-result-subtitle">' + escapeHtml(item.subtitle) + '</div>';
                html += '</div>';
                if (item.meta) {
                    html += '<div class="ra-search-result-meta">' + escapeHtml(item.meta) + '</div>';
                }
                html += '</a>';
                totalShown++;
            });

            html += '</div>';
        });

        if (totalShown === 0) {
            closeDropdown();
            return;
        }

        // "See all results" link at the bottom
        html += '<a href="/search?q=' + encodeURIComponent(term) + '" class="ra-search-see-all">';
        html += 'See all results for "' + escapeHtml(term) + '" &rarr;';
        html += '</a>';

        dropdown.innerHTML = html;
        openDropdown();
        currentIndex = -1;
    }

    function fetchResults(term) {
        if (term === lastQuery) return;
        lastQuery = term;

        fetch('/api/search?q=' + encodeURIComponent(term), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (term !== lastQuery) return; // stale
                renderResults(data);
            })
            .catch(function () {
                closeDropdown();
            });
    }

    function getNavigableItems() {
        return dropdown.querySelectorAll('[data-nav-item]');
    }

    input.addEventListener('input', function () {
        var term = input.value.trim();
        clearTimeout(debounceTimer);

        if (term.length < MIN_CHARS) {
            closeDropdown();
            lastQuery = '';
            return;
        }

        debounceTimer = setTimeout(function () {
            fetchResults(term);
        }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', function (e) {
        var items = getNavigableItems();

        if (e.key === 'Escape') {
            closeDropdown();
            input.blur();
            return;
        }

        if (e.key === 'ArrowDown') {
            if (items.length === 0) return;
            e.preventDefault();
            currentIndex = (currentIndex + 1) % items.length;
            items[currentIndex].classList.add('is-focused');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
            if (currentIndex > 0) items[currentIndex - 1].classList.remove('is-focused');
            return;
        }

        if (e.key === 'ArrowUp') {
            if (items.length === 0) return;
            e.preventDefault();
            currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
            items[currentIndex].classList.add('is-focused');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
            if (currentIndex < items.length - 1) items[currentIndex + 1].classList.remove('is-focused');
            return;
        }

        if (e.key === 'Enter') {
            if (currentIndex >= 0 && items[currentIndex]) {
                e.preventDefault();
                window.location.href = items[currentIndex].getAttribute('href');
                return;
            }
            // Fall through: form submits to /search
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && !form.contains(e.target)) {
            closeDropdown();
        }
    });

    // Reset on form submit (Enter with no selection)
    form.addEventListener('submit', function () {
        closeDropdown();
    });
})();
