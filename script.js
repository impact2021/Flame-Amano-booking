// Get form elements
const bookingForm = document.getElementById('bookingForm');
const successMessage = document.getElementById('successMessage');

// Set minimum date to today
const dateInput = document.getElementById('date');
const today = new Date().toISOString().split('T')[0];
dateInput.setAttribute('min', today);

// Form submission handler
bookingForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        date: document.getElementById('date').value,
        time: document.getElementById('time').value,
        guests: document.getElementById('guests').value,
        notes: document.getElementById('notes').value
    };
    
    // Validate phone number
    if (!validatePhone(formData.phone)) {
        alert('Please enter a valid phone number (at least 10 digits)');
        return;
    }
    
    // Validate date is not in the past
    const selectedDate = new Date(formData.date);
    const currentDate = new Date();
    currentDate.setHours(0, 0, 0, 0);
    
    if (selectedDate < currentDate) {
        alert('Please select today\'s date or a future date');
        return;
    }
    
    // Simulate form submission
    console.log('Booking submitted successfully');
    
    // Hide form and show success message
    bookingForm.style.display = 'none';
    successMessage.style.display = 'block';
    
    // Optional: Send data to server
    // fetch('/api/bookings', {
    //     method: 'POST',
    //     headers: { 'Content-Type': 'application/json' },
    //     body: JSON.stringify(formData)
    // });
});

// Phone validation function
function validatePhone(phone) {
    // Remove all non-digit characters to count actual digits
    const digitsOnly = phone.replace(/\D/g, '');
    
    // Must have at least 10 digits
    if (digitsOnly.length < 10) {
        return false;
    }
    
    // Check if the phone contains only valid characters
    const phoneRegex = /^[\d\s\-\+\(\)]+$/;
    if (!phoneRegex.test(phone)) {
        return false;
    }
    
    // Ensure there's at least one digit (prevents all symbols like "+++++")
    return /\d/.test(phone);
}

// Add input formatting for phone number
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', function(e) {
    // Remove any non-numeric characters except +, -, (, ), and space
    let value = e.target.value.replace(/[^\d\s\-\+\(\)]/g, '');
    e.target.value = value;
});

// Time slot validation (optional - restrict to business hours)
const timeInput = document.getElementById('time');
timeInput.addEventListener('change', function(e) {
    const selectedTime = e.target.value;
    const [hours, minutes] = selectedTime.split(':').map(Number);
    
    // Business hours: 11:00 AM to 10:00 PM
    if (hours < 11 || hours >= 22) {
        alert('Restaurant hours are 11:00 AM to 10:00 PM. Please select a time within these hours.');
        e.target.value = '';
    }
});
