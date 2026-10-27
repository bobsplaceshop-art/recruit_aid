/* ============================================================
   Recruit Aid — Application JavaScript
   Structure:
     1. Theme toggle (light / dark / system)
     2. Sidebar nav scroll persistence
     3. Dropdown menu handling (user, theme)
     4. Mobile sidebar drawer
     5. Auto-dismiss alerts

   NOTE: The scrollable element in the sidebar is .ra-nav,
   not .ra-sidebar. The sidebar itself is a fixed flex column
   whose children fit exactly. All scroll persistence targets
   .ra-nav.
   ============================================================ */

(function () {
    'use strict';

    /* ============================================================
       1. THEME
       ============================================================ */

    var THEME_KEY = 'ra-theme';
    var VALID_THEMES = ['dark', 'light', 'system'];

    function getStoredTheme() {
        try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; }
    }

    function storeTheme(theme) {
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    }

    function applyTheme(theme) {
        if (VALID_THEMES.indexOf(theme) === -1) theme = 'system';
        document.documentElement.setAttribute('data-theme', theme);
        updateThemeMenuActiveState(theme);
    }

    function updateThemeMenuActiveState(theme) {
        document.querySelectorAll('[data-ra-theme-choice]').forEach(function (el) {
            var val = el.getAttribute('data-ra-theme-choice');
            if (val === theme) el.classList.add('is-active');
            else el.classList.remove('is-active');
        });
    }

    function persistTheme(theme) {
        storeTheme(theme);
        applyTheme(theme);

        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) return;

        var form = new FormData();
        form.append('theme', theme);
        form.append('_csrf', csrf.getAttribute('content'));

        fetch('/account/theme', {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).catch(function () {});
    }

    function initTheme() {
        applyTheme(getStoredTheme() || 'system');
    }


    /* ============================================================
       2. SIDEBAR NAV SCROLL PERSISTENCE
       The scroll container is .ra-nav (inside .ra-sidebar).
       ============================================================ */

    var NAV_SCROLL_KEY = 'ra-nav-scroll';

    function getNavEl() {
        return document.querySelector('.ra-nav');
    }

    function saveNavScroll() {
        var nav = getNavEl();
        if (!nav) return;
        try {
            sessionStorage.setItem(NAV_SCROLL_KEY, String(nav.scrollTop));
        } catch (e) {}
    }

    function restoreNavScroll() {
        var nav = getNavEl();
        if (!nav) return;

        var saved = null;
        try { saved = sessionStorage.getItem(NAV_SCROLL_KEY); } catch (e) {}

        if (saved === null) {
            // First visit to this session — scroll active item into view if off-screen
            scrollActiveIntoView(nav);
            return;
        }

        var target = parseInt(saved, 10) || 0;

        // Verify the active item is visible at the saved position; if not, prefer active item
        var active = nav.querySelector('.ra-nav-item.is-active');
        if (active) {
            var navRect = nav.getBoundingClientRect();
            var activeRect = active.getBoundingClientRect();
            var bufferTop = 40;
            var bufferBottom = 40;
            var isAbove = activeRect.top < navRect.top + bufferTop;
            var isBelow = activeRect.bottom > navRect.bottom - bufferBottom;

            if (isAbove || isBelow) {
                // The saved position hides the active item — prioritize showing it
                target = Math.max(0, active.offsetTop - 100);
            }
        }

        nav.scrollTop = target;
    }

    function scrollActiveIntoView(nav) {
        var active = nav.querySelector('.ra-nav-item.is-active');
        if (!active) return;

        var navRect = nav.getBoundingClientRect();
        var activeRect = active.getBoundingClientRect();
        var isAbove = activeRect.top < navRect.top + 40;
        var isBelow = activeRect.bottom > navRect.bottom - 40;

        if (isAbove || isBelow) {
            nav.scrollTop = Math.max(0, active.offsetTop - 100);
        }
    }

    function initSidebarScroll() {
        var nav = getNavEl();
        if (!nav) return;

        // Restore as soon as possible, then again after layout settles.
        restoreNavScroll();
        requestAnimationFrame(restoreNavScroll);
        setTimeout(restoreNavScroll, 80);

        // Save on every scroll of the NAV (throttled to one write per frame)
        var scheduled = false;
        nav.addEventListener('scroll', function () {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                saveNavScroll();
                scheduled = false;
            });
        }, { passive: true });

        // Safety net: save before unload
        window.addEventListener('beforeunload', saveNavScroll);
    }


    /* ============================================================
       3. DROPDOWNS (user menu, theme menu)
       ============================================================ */

    function closeAllDropdowns() {
        document.querySelectorAll('.ra-dropdown.is-open').forEach(function (d) {
            d.classList.remove('is-open');
        });
    }

    function openDropdown(menu, trigger) {
        if (!menu) return;
        var rect = trigger.getBoundingClientRect();
        menu.style.top = (rect.bottom + 6) + 'px';
        menu.style.right = (window.innerWidth - rect.right) + 'px';
        closeAllDropdowns();
        menu.classList.add('is-open');
    }

    function initDropdowns() {
        document.addEventListener('click', function (e) {
            var themeTrigger = e.target.closest('[data-ra-theme-toggle]');
            if (themeTrigger) {
                e.preventDefault();
                var menu = document.getElementById('ra-theme-dropdown');
                if (menu && menu.classList.contains('is-open')) {
                    closeAllDropdowns();
                } else {
                    openDropdown(menu, themeTrigger);
                }
                return;
            }

            var choice = e.target.closest('[data-ra-theme-choice]');
            if (choice) {
                e.preventDefault();
                var theme = choice.getAttribute('data-ra-theme-choice');
                persistTheme(theme);
                closeAllDropdowns();
                return;
            }

            var userTrigger = e.target.closest('[data-ra-user-menu]');
            if (userTrigger) {
                e.preventDefault();
                var menu = document.getElementById('ra-user-dropdown');
                if (menu && menu.classList.contains('is-open')) {
                    closeAllDropdowns();
                } else {
                    openDropdown(menu, userTrigger);
                }
                return;
            }

            if (!e.target.closest('.ra-dropdown')) {
                closeAllDropdowns();
            }
        });
    }


    /* ============================================================
       4. MOBILE SIDEBAR DRAWER
       ============================================================ */

    function initMobileSidebar() {
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-ra-sidebar-toggle]');
            if (toggle) {
                e.preventDefault();
                var sidebar = document.querySelector('.ra-sidebar');
                if (sidebar) sidebar.classList.toggle('is-open');
                return;
            }

            var sidebar = document.querySelector('.ra-sidebar');
            if (sidebar && sidebar.classList.contains('is-open')) {
                if (!e.target.closest('.ra-sidebar')) {
                    sidebar.classList.remove('is-open');
                }
            }
        });
    }


    /* ============================================================
       5. AUTO-DISMISS ALERTS
       ============================================================ */

    function initAutoDismissAlerts() {
        document.querySelectorAll('.ra-alert-success').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity .4s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.style.display = 'none'; }, 500);
            }, 5000);
        });
    }


    /* ============================================================
       BOOTSTRAP
       ============================================================ */

    function boot() {
        initTheme();
        initSidebarScroll();
        initDropdowns();
        initMobileSidebar();
        initAutoDismissAlerts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
