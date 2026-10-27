/* ============================================================
   Recruit Aid — Field builder behavior
   - Shows the correct config inputs based on the selected input type
   - Shows the options editor only for select / multi-select
   - Renders a live preview of the field
   - Handles add/remove option rows
   ============================================================ */

(function () {
    'use strict';

    // Configuration per input type. Mirrors InputType::configFields() in PHP.
    var CONFIG = {
        text:         [{ key: 'max_length', label: 'Maximum length', type: 'number', placeholder: '255' }],
        long_text:    [{ key: 'max_length', label: 'Maximum length', type: 'number', placeholder: '5000' }],
        number:       [
            { key: 'min',      label: 'Minimum value',    type: 'number' },
            { key: 'max',      label: 'Maximum value',    type: 'number' },
            { key: 'decimals', label: 'Decimal places (0-10)', type: 'number', placeholder: '0' }
        ],
        date:         [
            { key: 'earliest', label: 'Earliest allowed (YYYY-MM-DD)', type: 'date' },
            { key: 'latest',   label: 'Latest allowed (YYYY-MM-DD)',   type: 'date' },
            { key: 'min_age',  label: 'Minimum age (years)',           type: 'number', placeholder: 'e.g. 18' },
            { key: 'max_age',  label: 'Maximum age (years)',           type: 'number', placeholder: 'e.g. 65' }
        ],
        email:        [],
        phone:        [],
        url:          [],
        select:       [],
        multi_select: [],
        checkbox:     [],
        yes_no:       [],
        file:         [
            { key: 'extensions', label: 'Allowed extensions (comma separated)', type: 'text', placeholder: 'pdf,doc,docx,png,jpg' },
            { key: 'max_size_mb', label: 'Maximum size (MB)', type: 'number', placeholder: '8' }
        ]
    };

    var OPTION_TYPES = ['select', 'multi_select'];

    function init() {
        var typeSelect     = document.getElementById('cf-input-type');
        var configSection  = document.getElementById('cf-config-section');
        var configFields   = document.getElementById('cf-config-fields');
        var optionsSection = document.getElementById('cf-options-section');
        var optionsList    = document.getElementById('cf-options-list');
        var addOptionBtn   = document.getElementById('cf-add-option');
        var previewBox     = document.getElementById('cf-preview');
        var labelInput     = document.getElementById('cf-label');
        var requiredInput  = document.querySelector('input[name="is_required"]');
        var helpInput      = document.getElementById('cf-help-text');

        // Not the field builder page — bail
        if (!typeSelect || !previewBox) return;

        function currentType() {
            return typeSelect.value;
        }

        function escapeHtml(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function readOptions() {
            if (!optionsList) return [];
            return Array.prototype.slice
                .call(optionsList.querySelectorAll('input[name="options[]"]'))
                .map(function (i) { return i.value.trim(); })
                .filter(function (v) { return v !== ''; });
        }

        // ---------- Config section ----------
        function renderConfig() {
            var type = currentType();
            var cfgs = CONFIG[type] || [];

            if (cfgs.length === 0) {
                configSection.style.display = 'none';
                configFields.innerHTML = '';
                return;
            }

            var html = '';
            cfgs.forEach(function (f) {
                html += '<div class="mb-3">'
                      + '<label class="ra-label" for="cf-cfg-' + f.key + '">' + escapeHtml(f.label) + '</label>'
                      + '<input type="' + f.type + '" id="cf-cfg-' + f.key + '"'
                      + ' name="constraints[' + f.key + ']"'
                      + ' class="ra-input"'
                      + ' placeholder="' + escapeHtml(f.placeholder || '') + '">'
                      + '</div>';
            });
            configFields.innerHTML = html;
            configSection.style.display = '';
        }

        // ---------- Options section ----------
        function renderOptionsVisibility() {
            var type = currentType();
            optionsSection.style.display = OPTION_TYPES.indexOf(type) !== -1 ? '' : 'none';
        }

        // ---------- Preview ----------
        function renderPreview() {
            var type = currentType();
            var label = (labelInput && labelInput.value.trim()) || 'Field label';
            var required = requiredInput && requiredInput.checked;
            var help = (helpInput && helpInput.value.trim()) || '';
            var star = required ? ' <span style="color:var(--ra-error-fg)">*</span>' : '';

            if (!type) {
                previewBox.innerHTML = '<div class="muted small">Choose an input type to see the preview.</div>';
                return;
            }

            var inputHtml = '';

            switch (type) {
                case 'text':
                    inputHtml = '<input type="text" class="ra-input" disabled>';
                    break;
                case 'long_text':
                    inputHtml = '<textarea class="ra-input" rows="3" disabled></textarea>';
                    break;
                case 'number':
                    inputHtml = '<input type="number" class="ra-input" disabled>';
                    break;
                case 'date':
                    inputHtml = '<input type="date" class="ra-input" disabled>';
                    break;
                case 'email':
                    inputHtml = '<input type="email" class="ra-input" disabled>';
                    break;
                case 'phone':
                    inputHtml = '<input type="tel" class="ra-input" disabled>';
                    break;
                case 'url':
                    inputHtml = '<input type="url" class="ra-input" disabled placeholder="https://">';
                    break;
                case 'select': {
                    var opts = readOptions();
                    inputHtml = '<select class="ra-input ra-select" style="width:100%;" disabled><option>— Select —</option>';
                    opts.forEach(function (o) { inputHtml += '<option>' + escapeHtml(o) + '</option>'; });
                    inputHtml += '</select>';
                    break;
                }
                case 'multi_select': {
                    var opts2 = readOptions();
                    inputHtml = '<div style="display:flex;flex-wrap:wrap;gap:.75rem;padding:.5rem 0;">';
                    var list = opts2.length ? opts2 : ['Option 1', 'Option 2'];
                    list.forEach(function (o) {
                        inputHtml += '<label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;">'
                                  + '<input type="checkbox" disabled> <span>' + escapeHtml(o) + '</span></label>';
                    });
                    inputHtml += '</div>';
                    break;
                }
                case 'checkbox':
                    inputHtml = '<label style="display:flex;align-items:center;gap:.5rem;">'
                              + '<input type="checkbox" disabled>'
                              + ' <span class="ra-label" style="margin:0;">' + escapeHtml(label) + star + '</span>'
                              + '</label>';
                    break;
                case 'yes_no':
                    inputHtml = '<div style="display:flex;gap:1.25rem;padding:.25rem 0;">'
                              + '<label style="display:flex;align-items:center;gap:.4rem;"><input type="radio" disabled> Yes</label>'
                              + '<label style="display:flex;align-items:center;gap:.4rem;"><input type="radio" disabled> No</label>'
                              + '</div>';
                    break;
                case 'file':
                    inputHtml = '<input type="file" class="ra-input" disabled>';
                    break;
                default:
                    inputHtml = '<input type="text" class="ra-input" disabled>';
            }

            var fullHtml = '<div class="ra-field">';
            if (type === 'checkbox') {
                fullHtml += inputHtml;
            } else {
                fullHtml += '<div class="ra-label">' + escapeHtml(label) + star + '</div>' + inputHtml;
            }
            if (help) fullHtml += '<div class="muted small mt-1">' + escapeHtml(help) + '</div>';
            fullHtml += '</div>';

            previewBox.innerHTML = fullHtml;
        }

        // ---------- Option rows ----------
        function addOptionRow() {
            var row = document.createElement('div');
            row.className = 'cf-option-row d-flex gap-2 mb-2';
            row.innerHTML = '<input type="text" name="options[]" class="ra-input" placeholder="Option text">'
                          + '<button type="button" class="ra-btn ra-btn-outline cf-remove-option" title="Remove">'
                          + '<i class="bi bi-x"></i></button>';
            optionsList.appendChild(row);
            var input = row.querySelector('input');
            if (input) input.focus();
        }

        // ---------- Wiring ----------
        typeSelect.addEventListener('change', function () {
            renderConfig();
            renderOptionsVisibility();
            renderPreview();
        });

        if (labelInput) labelInput.addEventListener('input', renderPreview);
        if (requiredInput) requiredInput.addEventListener('change', renderPreview);
        if (helpInput) helpInput.addEventListener('input', renderPreview);

        if (addOptionBtn) {
            addOptionBtn.addEventListener('click', function () {
                addOptionRow();
                renderPreview();
            });
        }

        if (optionsList) {
            optionsList.addEventListener('click', function (e) {
                var btn = e.target.closest('.cf-remove-option');
                if (btn) {
                    var row = btn.closest('.cf-option-row');
                    if (row) {
                        row.remove();
                        renderPreview();
                    }
                }
            });
            optionsList.addEventListener('input', renderPreview);
        }

        // Initial render (in case the form loaded with a pre-selected type)
        renderConfig();
        renderOptionsVisibility();
        renderPreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
