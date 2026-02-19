            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/sweetalert-helpers.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/admin.js"></script>
    
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const isClickInsideSidebar = sidebar && sidebar.contains(event.target);
            const isClickOnToggle = toggle && toggle.contains(event.target);
            
            if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('active') && !isClickInsideSidebar && !isClickOnToggle) {
                sidebar.classList.remove('active');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.remove('active');
            }
        });

        // Highlight active menu item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const menuLinks = document.querySelectorAll('.sidebar-menu .nav-link');
            menuLinks.forEach(link => {
                if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href').split('/').pop())) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
