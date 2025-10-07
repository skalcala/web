// Updated JavaScript - Database Version (app.js)
// Replace localStorage with API calls

// API Configuration
const API_BASE = 'api/';

// API Helper Functions
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include'
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(API_BASE + endpoint, options);
        const text = await response.text();
        
        // Log the raw response for debugging
        console.log('API Response:', text.substring(0, 200));
        
        try {
            return JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text);
            return { 
                success: false, 
                message: 'Server returned invalid response. Check console for details.' 
            };
        }
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Connection error: ' + error.message };
    }
}

// Authentication functions
async function handleLogin(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const result = await apiCall('auth.php?action=login', 'POST', {
        email: formData.get('email'),
        password: formData.get('password')
    });
    
    if (result.success) {
        await updateAuthUI();
        bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
        showAlert('success', 'Login successful! Welcome back, ' + result.data.name);
        e.target.reset();
        
        // Open profile modal after successful login
        setTimeout(() => {
            showProfile();
            new bootstrap.Modal(document.getElementById('profileModal')).show();
        }, 500);
    } else {
        showAlert('danger', result.message);
    }
}

async function handleRegister(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const password = formData.get('password');
    const cpassword = formData.get('cpassword');
    
    if (password !== cpassword) {
        showAlert('danger', 'Passwords do not match!');
        return;
    }
    
    const result = await apiCall('auth.php?action=register', 'POST', {
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        dob: formData.get('dob'),
        address: formData.get('address'),
        password: password
    });
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
        showAlert('success', 'Registration successful! Please login.');
        e.target.reset();
    } else {
        showAlert('danger', result.message);
    }
}

async function logout() {
    const result = await apiCall('auth.php?action=logout', 'POST');
    
    if (result.success) {
        await updateAuthUI();
        showAlert('info', 'Logged out successfully');
        
        const bookingModal = document.getElementById('bookingModal');
        if (bookingModal) {
            const modal = bootstrap.Modal.getInstance(bookingModal);
            if (modal) modal.hide();
        }
    }
}

async function updateAuthUI() {
    const result = await apiCall('auth.php?action=getCurrentUser');
    
    const authButtons = document.getElementById('authButtons');
    const userDropdown = document.getElementById('userDropdown');
    const navUserName = document.getElementById('navUserName');
    
    if (result.success && result.data) {
        authButtons.classList.add('d-none');
        userDropdown.classList.remove('d-none');
        navUserName.textContent = result.data.name;
        window.currentUser = result.data;
    } else {
        authButtons.classList.remove('d-none');
        userDropdown.classList.add('d-none');
        window.currentUser = null;
    }
}

async function showProfile() {
    if (!window.currentUser) return;
    
    document.getElementById('profileName').textContent = window.currentUser.name;
    document.getElementById('profileEmail').textContent = window.currentUser.email;
    document.getElementById('profilePhone').textContent = window.currentUser.phone;
    document.getElementById('profileAddress').textContent = window.currentUser.address;
    document.getElementById('profileDOB').textContent = window.currentUser.dob;
}

async function showMyBookings() {
    if (!window.currentUser) return;
    
    const result = await apiCall('bookings.php?action=getUserBookings');
    const container = document.getElementById('bookingsContent');
    
    if (!result.success || result.data.length === 0) {
        container.innerHTML = '<p class="text-center text-muted py-4">No bookings yet.</p>';
    } else {
        container.innerHTML = `
            <table class="table table-hover">
                <thead style="background-color: #003355; color: white;">
                    <tr>
                        <th>Booking ID</th>
                        <th>Room</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Nights</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(b => `
                        <tr>
                            <td><strong>${b.booking_id}</strong></td>
                            <td>${b.room_name}</td>
                            <td>${new Date(b.checkin).toLocaleDateString()}</td>
                            <td>${new Date(b.checkout).toLocaleDateString()}</td>
                            <td>${b.nights}</td>
                            <td><strong>₱${parseFloat(b.totalPrice).toLocaleString()}</strong></td>
                            <td>
                                <span class="badge ${b.status === 'Confirmed' ? 'bg-success' : 'bg-warning'}">${b.status}</span>
                                ${b.queuePosition ? `<br><small class="text-muted">Position: ${b.queuePosition}</small>` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
    
    new bootstrap.Modal(document.getElementById('bookingsModal')).show();
}

// Booking functions
async function openBooking(roomId) {
    const result = await apiCall('bookings.php?action=getRooms');
    if (!result.success) return;
    
    const room = result.data.find(r => r.id === roomId);
    if (!room) return;
    
    document.getElementById('selectedRoomId').value = roomId;
    document.getElementById('bookingRoomName').textContent = room.name;
    document.getElementById('pricePerNight').textContent = parseFloat(room.price_per_night).toLocaleString();
    
    const bookingForm = document.getElementById('bookingForm');
    const loginWarning = document.getElementById('bookingLoginWarning');
    
    if (window.currentUser) {
        loginWarning.classList.add('d-none');
        bookingForm.classList.remove('d-none');
        
        bookingForm.querySelector('[name="name"]').value = window.currentUser.name;
        bookingForm.querySelector('[name="phone"]').value = window.currentUser.phone;
        bookingForm.querySelector('[name="address"]').value = window.currentUser.address;
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('bookingCheckin').min = today;
        document.getElementById('bookingCheckout').min = today;
    } else {
        loginWarning.classList.remove('d-none');
        bookingForm.classList.add('d-none');
    }
    
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

async function calculateBooking() {
    const checkin = document.getElementById('bookingCheckin').value;
    const checkout = document.getElementById('bookingCheckout').value;
    const roomId = parseInt(document.getElementById('selectedRoomId').value);
    
    if (!checkin || !checkout || !roomId) return;
    
    const result = await apiCall('bookings.php?action=getRooms');
    const room = result.data.find(r => r.id === roomId);
    
    const nights = Math.ceil((new Date(checkout) - new Date(checkin)) / (1000*60*60*24));
    
    if (nights > 0) {
        document.getElementById('nightsCount').textContent = nights;
        document.getElementById('totalPrice').textContent = (nights * parseFloat(room.price_per_night)).toLocaleString('en-US', {minimumFractionDigits: 2});
        await checkRoomCapacity(roomId, checkin, checkout);
    } else {
        document.getElementById('nightsCount').textContent = '0';
        document.getElementById('totalPrice').textContent = '0.00';
    }
}

async function checkRoomCapacity(roomId, checkin, checkout) {
    const result = await apiCall(`bookings.php?action=checkAvailability&roomId=${roomId}&checkin=${checkin}&checkout=${checkout}`);
    const warning = document.getElementById('capacityWarning');
    
    if (result.success && result.data.isFull) {
        warning.classList.remove('d-none');
    } else {
        warning.classList.add('d-none');
    }
}

function processGCashPayment() {
    processPayment('GCash');
}

function processPayMayaPayment() {
    processPayment('PayMaya');
}

async function processPayment(provider) {
    if (!window.currentUser) {
        showAlert('warning', 'Please login first');
        return;
    }
    
    const roomId = parseInt(document.getElementById('selectedRoomId').value);
    const checkin = document.getElementById('bookingCheckin').value;
    const checkout = document.getElementById('bookingCheckout').value;
    const nights = parseInt(document.getElementById('nightsCount').textContent);
    const totalPrice = document.getElementById('totalPrice').textContent.replace(/,/g, '');
    
    if (!checkin || !checkout || nights <= 0) {
        showAlert('warning', 'Please select valid dates (at least 1 night)');
        return;
    }
    
    // Check availability first
    const availResult = await apiCall(`bookings.php?action=checkAvailability&roomId=${roomId}&checkin=${checkin}&checkout=${checkout}`);
    const isQueued = availResult.data.isFull;
    
    const confirmed = confirm(
        `Proceed with ${provider} payment?\n\n` +
        `Amount: ₱${parseFloat(totalPrice).toLocaleString()}\n` +
        (isQueued ? '\nNote: Room is fully booked. You will be added to queue.' : '')
    );
    
    if (!confirmed) return;
    
    // Create booking
    const result = await apiCall('bookings.php?action=create', 'POST', {
        roomId: roomId,
        checkin: checkin,
        checkout: checkout,
        adults: document.querySelector('[name="adults"]').value,
        children: document.querySelector('[name="children"]').value,
        provider: provider
    });
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
        
        const message = result.data.status === 'Queued'
            ? `Your booking has been queued!\n\nBooking ID: ${result.data.bookingId}\nQueue Position: ${result.data.queuePosition}\n\nYou will be notified when a spot becomes available.`
            : `Booking confirmed!\n\nBooking ID: ${result.data.bookingId}\nTransaction ID: ${result.data.transactionId}\nTotal: ₱${parseFloat(result.data.totalPrice).toLocaleString()}\n\nThank you for booking with Sunset Beach Resort!`;
        
        showAlert('success', message);
        
        document.getElementById('bookingForm').reset();
        document.getElementById('nightsCount').textContent = '0';
        document.getElementById('totalPrice').textContent = '0.00';
    } else {
        showAlert('danger', result.message);
    }
}


// Utility function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Event listeners - Initialize after DOM loads
document.addEventListener('DOMContentLoaded', async function() {
    // Update auth UI on page load
    await updateAuthUI();
    
    // Render home rooms if on home page
    await renderHomeRooms();
    
    // Login form handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Register form handler
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
        
        // Name input validation - letters only
        const nameInput = document.getElementById('regName');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                this.value = this.value.replace(/[0-9]/g, '');
            });
        }
        
        // Phone input validation - numbers only
        const phoneInput = document.getElementById('regPhone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
        }
    }
    
    // Logout button handler
    const logoutBtn = document.getElementById('navLogoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    }
    
    // My Bookings button handler
    const myBookingsBtn = document.getElementById('myBookingsBtn');
    if (myBookingsBtn) {
        myBookingsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showMyBookings();
        });
    }
    
    // Profile modal - load profile data when shown
    const profileModal = document.getElementById('profileModal');
    if (profileModal) {
        profileModal.addEventListener('show.bs.modal', showProfile);
    }
    
    // Booking date change handlers
    const checkinInput = document.getElementById('bookingCheckin');
    const checkoutInput = document.getElementById('bookingCheckout');
    if (checkinInput) checkinInput.addEventListener('change', calculateBooking);
    if (checkoutInput) checkoutInput.addEventListener('change', calculateBooking);
    
    // Set minimum dates for quick search
    const today = new Date().toISOString().split('T')[0];
    const quickCheckin = document.getElementById('quickCheckin');
    const quickCheckout = document.getElementById('quickCheckout');
    if (quickCheckin) quickCheckin.min = today;
    if (quickCheckout) quickCheckout.min = today;
    
    // Initialize hero carousel if exists
    const heroCarousel = document.getElementById('heroCarousel');
    if (heroCarousel && typeof bootstrap !== 'undefined') {
        new bootstrap.Carousel(heroCarousel, {
            interval: 3500,
            ride: 'carousel'
        });
    }
});

// Helper function to scroll to rooms section
function scrollToRooms() {
    const roomsSection = document.getElementById('rooms');
    if (roomsSection) {
        roomsSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        window.location.href = 'rooms.html';
    }
}