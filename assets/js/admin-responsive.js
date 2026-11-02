/**
 * 📱 Admin Responsive Helper for ThumWadThaiHub
 * Manages mobile off-canvas drawer, backdrop, and touch interactions.
 */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // 1. Ensure backdrop element exists
    let backdrop = document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(backdrop);
    }

    // 2. Setup close button inside sidebar header if not present
    const sidebarHeader = sidebar.querySelector('.sidebar-header');
    if (sidebarHeader && !sidebarHeader.querySelector('.sidebar-close-btn')) {
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'sidebar-close-btn';
        closeBtn.setAttribute('aria-label', 'ปิดเมนู');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.addEventListener('click', closeSidebar);
        sidebarHeader.appendChild(closeBtn);
    }

    // 3. Find or enhance the sidebar toggle button in topbar
    const topbar = document.querySelector('.topbar');
    if (topbar) {
        let toggleBtn = topbar.querySelector('.sidebar-toggle-btn');
        if (!toggleBtn) {
            // Check if there is an existing div with fa-bars icon
            const barsIcon = topbar.querySelector('.fa-bars');
            if (barsIcon) {
                const parentDiv = barsIcon.parentElement;
                toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'sidebar-toggle-btn';
                toggleBtn.setAttribute('aria-label', 'เปิดเมนูนำทาง');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                parentDiv.replaceWith(toggleBtn);
            } else {
                toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'sidebar-toggle-btn';
                toggleBtn.setAttribute('aria-label', 'เปิดเมนูนำทาง');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                topbar.prepend(toggleBtn);
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }
    }

    // 4. Backdrop click closes sidebar
    backdrop.addEventListener('click', closeSidebar);

    // 5. Escape key closes sidebar
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });

    // 6. Close sidebar when clicking links on mobile
    const sidebarLinks = sidebar.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        });
    });

    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function openSidebar() {
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
    }
});
