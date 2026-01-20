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
        alert('Please enter a valid phone number');
        return;
    }
    
    // Validate date is not in the past
    const selectedDate = new Date(formData.date);
    const currentDate = new Date();
    currentDate.setHours(0, 0, 0, 0);
    
    if (selectedDate < currentDate) {
        alert('Please select a future date');
        return;
    }
    
    // Simulate form submission
    console.log('Booking submitted:', formData);
    
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
    // Basic phone validation - accepts various formats
    const phoneRegex = /^[\d\s\-\+\(\)]+$/;
    return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10;
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
        alert('Please select a time between 11:00 AM and 10:00 PM');
        e.target.value = '';
    }
});
