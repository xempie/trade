// header.js - Header functionality for user menu and authentication

class HeaderManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupUserMenu();
        this.setupLogout();
    }

    setupUserMenu() {
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');
        
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
    }

    setupLogout() {
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'auth/logout.php';
                }
            });
        }
    }
}

// Initialize header functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new HeaderManager();
});