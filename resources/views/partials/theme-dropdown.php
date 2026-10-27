<?php
/**
 * Theme dropdown: Light / Dark / System.
 * Positioned by JS relative to the trigger button.
 */
?>
<div id="ra-theme-dropdown" class="ra-dropdown" style="min-width:200px;">
    <div class="ra-dropdown-header">
        <div class="small muted">Appearance</div>
    </div>

    <button type="button" class="ra-dropdown-item" data-ra-theme-choice="light">
        <i class="bi bi-sun-fill"></i>
        <span>Light</span>
    </button>

    <button type="button" class="ra-dropdown-item" data-ra-theme-choice="dark">
        <i class="bi bi-moon-stars-fill"></i>
        <span>Dark</span>
    </button>

    <button type="button" class="ra-dropdown-item" data-ra-theme-choice="system">
        <i class="bi bi-display"></i>
        <span>System</span>
    </button>
</div>
