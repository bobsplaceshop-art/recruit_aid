<div class="text-center">
    <div style="font-size: 3rem; color: var(--ra-primary); margin-bottom: 1rem;">
        <i class="bi bi-check-circle-fill"></i>
    </div>
    <h2 class="h3 mb-3" style="color: var(--ra-text-strong);">You have been signed out</h2>
    <p class="muted mb-4">Your session has been securely terminated.</p>
    
    <div class="ra-alert ra-alert-warn text-start mb-4" style="font-size: .85rem;">
        <strong><i class="bi bi-shield-lock me-1"></i> Security Reminder:</strong>
        <ul class="mb-0 mt-1" style="padding-left: 1.2rem;">
            <li>If you are on a shared or public computer, please close your browser now.</li>
            <li>Clear your browser history if you accessed sensitive information.</li>
        </ul>
    </div>

    <a href="/login" class="btn btn-primary w-100 py-2">
        <i class="bi bi-box-arrow-in-right me-1"></i> Return to Sign In
    </a>
</div>

<script>
    // Industry standard: clear local theme preference on logout for shared computers
    try { localStorage.removeItem('ra-theme'); } catch (e) {}
</script>
