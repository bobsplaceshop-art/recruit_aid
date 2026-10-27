/* ============================================================
   Recruit Aid — Notification bell
   - Polls /api/notifications every 30s
   - Renders the dropdown list
   - Marks individual notifications as read
   - Marks all as read
   ============================================================ */

(function () {
    'use strict';

    var POLL_INTERVAL = 30000;
    var API_URL = '/api/notifications';

    function el(id) { return document.getElementById(id); }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function updateBadge(count) {
        var badge = el('ra-notif-badge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
        var summary = el('ra-notif-summary');
        if (summary) {
            summary.textContent = count > 0
                ? count + ' unread'
                : 'All caught up';
        }
    }

    function renderList(items) {
        var list = el('ra-notif-list');
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = '<div class="text-center muted small p-4">No notifications yet.</div>';
            return;
        }

        var html = '';
        items.forEach(function (n) {
            var bg = n.is_read ? 'transparent' : 'rgba(15,76,129,.08)';
            var linkOpen = n.link ? '<a href="' + escapeHtml(n.link) + '" class="ra-dropdown-item" style="align-items:flex-start;background:' + bg + ';">' : '<div class="ra-dropdown-item" style="align-items:flex-start;background:' + bg + ';">';
            var linkClose = n.link ? '</a>' : '</div>';
            html += linkOpen
                  + '<i class="bi ' + escapeHtml(n.icon) + '" style="margin-top:2px;"></i>'
                  + '<div style="flex:1;min-width:0;">'
                  +   '<div class="small" style="line-height:1.3;">' + escapeHtml(n.title) + '</div>'
                  +   (n.body ? '<div class="muted" style="font-size:.78rem;line-height:1.3;">' + escapeHtml(n.body) + '</div>' : '')
                  +   '<div class="muted" style="font-size:.7rem;margin-top:2px;">' + escapeHtml(n.relative) + '</div>'
                  + '</div>'
                  + linkClose;
        });
        list.innerHTML = html;
    }

    function fetchNotifications() {
        fetch(API_URL, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) return;
            updateBadge(data.unread_count);
            renderList(data.items);
        })
        .catch(function () { /* silent */ });
    }

    function markAllRead() {
        var form = new FormData();
        form.append('_csrf', getCsrf());

        fetch('/notifications/read-all', {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function () { fetchNotifications(); })
        .catch(function () {});
    }

    function init() {
        var toggle = el('ra-notif-toggle');
        var dropdown = el('ra-notif-dropdown');
        if (!toggle || !dropdown) return;

        // Toggle dropdown
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = dropdown.classList.contains('is-open');
            // Close all other dropdowns
            document.querySelectorAll('.ra-dropdown.is-open').forEach(function (d) {
                d.classList.remove('is-open');
            });
            if (!isOpen) {
                var rect = toggle.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 6) + 'px';
                dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                dropdown.classList.add('is-open');
                fetchNotifications();
            }
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#ra-notif-wrap')) {
                dropdown.classList.remove('is-open');
            }
        });

        // Mark all
        var markAll = el('ra-notif-mark-all');
        if (markAll) {
            markAll.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                markAllRead();
            });
        }

        // Initial fetch + poll
        fetchNotifications();
        setInterval(fetchNotifications, POLL_INTERVAL);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
