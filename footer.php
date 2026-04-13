<!-- Footer -->
<footer class="footer">
    <p>&copy; <?php echo date("Y"); ?> Boy Scout Management System. All Rights Reserved.</p>
    <p>Crafted with <i class="bi bi-heart-fill text-danger"></i> by Giren Nard Dawn</p>
</footer>

<style>
    /* Ensure body and html take full height */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
    }
    
    /* Wrapper should be flex container */
    .wrapper {
        display: flex;
        min-height: 100vh;
    }
    
    /* Main content area should be flex column */
    .main, .main-content {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        flex: 1;
    }
    
    /* Footer styling - will stick to bottom */
    .footer {
        color: rgba(255, 255, 255, 0.7);
        text-align: center;
        padding: 30px 20px;
        margin-top: auto;
        width: 100%;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }
    
    .footer p {
        margin: 5px 0;
        font-size: 14px;
    }
    
    .footer .bi-heart-fill {
        animation: heartbeat 1.5s ease-in-out infinite;
    }
    
    @keyframes heartbeat {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* Glass containers spacing */
    .glass {
        margin-bottom: 20px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Apply transition for sink effect globally
    const mainContent = document.querySelector('.main') || document.querySelector('.main-content');
    if (mainContent) {
        // This ensures the transform, border-radius, etc. are animated.
        mainContent.style.transition = 'all 0.3s ease-in-out';
    }

    const body = document.body;
    
    const sidebar = document.querySelector('.sidebar');

    // Create overlay for mobile view
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    body.appendChild(overlay);

    // --- Tooltip logic for collapsed desktop sidebar ---
    const sidebarLinks = document.querySelectorAll('.sidebar a[data-bs-toggle="tooltip"]');
    const tooltipInstances = [...sidebarLinks].map(tooltipTriggerEl => {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover',
            customClass: 'sidebar-tooltip'
        });
    });

    const updateTooltips = () => {
        if (body.classList.contains('sidebar-collapsed')) {
            tooltipInstances.forEach(tooltip => tooltip.enable());
        } else {
            tooltipInstances.forEach(tooltip => tooltip.disable());
        }
    };

    // Check for saved state in localStorage on page load
    if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 992) {
        body.classList.add('sidebar-collapsed');
    }
    updateTooltips();

    // --- Combined click handler for mobile and desktop ---
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth <= 992) {
                // Mobile view: slide-in
                sidebar.classList.toggle('mobile-show');
                overlay.classList.toggle('show');
            } else {
                // Desktop view: collapse
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
                updateTooltips();
            }
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-show');
            overlay.classList.remove('show');
        });
    }
});
</script>
