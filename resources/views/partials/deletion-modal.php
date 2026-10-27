<?php
/**
 * Reusable deletion confirmation modal.
 *
 * Opened via JS: RA_DeletionModal.open({...})
 * Handles its own submit event via addEventListener.
 */
?>
<div id="ra-delete-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:2rem;">
    <div class="ra-card" style="max-width:560px;width:100%;">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h2 class="h5 mb-0" style="color:var(--ra-error-fg);">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Confirm permanent deletion
            </h2>
            <button type="button" class="ra-icon-btn" onclick="RA_DeletionModal.close()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <p class="muted small mb-3">
            You are about to <strong>permanently delete</strong>:
        </p>
        <div style="background:var(--ra-surface-3);border:1px solid var(--ra-border);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;">
            <strong id="ra-delete-modal-label">Loading…</strong>
        </div>

        <div id="ra-delete-modal-deps" style="display:none;margin-bottom:1rem;">
            <div class="ra-alert ra-alert-warn" style="margin:0;">
                <div class="small mb-1"><strong>This will also delete:</strong></div>
                <ul id="ra-delete-modal-deps-list" class="mb-0" style="padding-left:1.2rem;font-size:.85rem;"></ul>
            </div>
        </div>

        <div style="background:var(--ra-error-bg);border:1px solid var(--ra-error-bd);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;">
            <div class="small mb-1" style="color:var(--ra-error-fg);">
                To confirm, type this phrase exactly:
            </div>
            <code id="ra-delete-modal-phrase" style="font-size:1rem;color:var(--ra-error-fg);user-select:all;">…</code>
        </div>

        <form id="ra-delete-modal-form" method="POST">
            <input type="hidden" name="_csrf" id="ra-delete-modal-csrf" value="">

            <div class="mb-3">
                <label class="ra-label">Confirmation phrase</label>
                <input type="text" id="ra-delete-modal-input" name="confirmation" class="ra-input"
                       autocomplete="off" autocorrect="off" autocapitalize="off"
                       spellcheck="false" data-lpignore="true" data-form-type="other"
                       placeholder="Type the phrase above">
            </div>

            <div class="mb-3">
                <label class="ra-label">Reason (optional)</label>
                <input type="text" name="reason" class="ra-input" maxlength="500"
                       autocomplete="off" data-lpignore="true"
                       placeholder="Why is this being deleted?">
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeletionModal.close()">
                    Cancel
                </button>
                <button type="submit" id="ra-delete-modal-submit" class="ra-btn ra-btn-danger" disabled>
                    <i class="bi bi-trash"></i>
                    <span>Delete permanently</span>
                </button>
            </div>
        </form>
    </div>
</div>
