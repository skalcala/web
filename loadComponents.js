// loadComponents.js - Load header and footer, then initialize app

async function loadComponent(elementId, filePath) {
    try {
        const response = await fetch(filePath);
        const html = await response.text();
        document.getElementById(elementId).innerHTML = html;
    } catch (error) {
        console.error(`Error loading ${filePath}:`, error);
    }
}

// Load components when DOM is ready
document.addEventListener('DOMContentLoaded', async function() {
    // Load header first
    await loadComponent('header-placeholder', 'header.html');
    
    // Load footer (optional)
    const footerPlaceholder = document.getElementById('footer-placeholder');
    if (footerPlaceholder) {
        await loadComponent('footer-placeholder', 'footer.html');
    }
    
    // After components are loaded, initialize app.js event listeners
    initializeApp();
    
    // Set active navigation link
    setActiveNavLink();
});

// Initialize all event listeners (called after header loads)
function initializeApp() {
    // Update auth UI
    if (typeof updateAuthUI === 'function') {
        updateAuthUI();
    }
    
    // Render home rooms
    if (typeof renderHomeRooms === 'function') {
        renderHomeRooms();
    }
    
    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
        
        // Name validation (letters only)
        const nameInput = document.getElementById('regName');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                this.value = this.value.replace(/[0-9]/g, '');
            });
        }
        
        // Phone validation (numbers only)
        const phoneInput = document.getElementById('regPhone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
        }
    }
    
    // Logout button
    const logoutBtn = document.getElementById('navLogoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    }
    
    // My Bookings button
    const myBookingsBtn = document.getElementById('myBookingsBtn');
    if (myBookingsBtn) {
        myBookingsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showMyBookings();
        });
    }
    
    // Profile modal
    const profileModal = document.getElementById('profileModal');
    if (profileModal) {
        profileModal.addEventListener('show.bs.modal', showProfile);
    }
    
    // Booking date inputs
    const checkinInput = document.getElementById('bookingCheckin');
    const checkoutInput = document.getElementById('bookingCheckout');
    if (checkinInput) checkinInput.addEventListener('change', calculateBooking);
    if (checkoutInput) checkoutInput.addEventListener('change', calculateBooking);
    
    // Set min dates for quick search
    const today = new Date().toISOString().split('T')[0];
    const quickCheckin = document.getElementById('quickCheckin');
    const quickCheckout = document.getElementById('quickCheckout');
    if (quickCheckin) quickCheckin.min = today;
    if (quickCheckout) quickCheckout.min = today;
    
    // Initialize carousel
    const heroCarousel = document.getElementById('heroCarousel');
    if (heroCarousel && typeof bootstrap !== 'undefined') {
        new bootstrap.Carousel(heroCarousel, {
            interval: 3500,
            ride: 'carousel'
        });
    }
}

// Set active nav link based on current page
function setActiveNavLink() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}