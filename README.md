# Flame Amano Booking Form

A modern, responsive web-based booking form for table reservations at Flame Amano.

## Features

- 🎨 Beautiful gradient UI with modern design
- 📱 Fully responsive (works on desktop, tablet, and mobile)
- ✅ Client-side form validation
- 📅 Date picker with past date prevention
- ⏰ Time picker with business hours validation (11 AM - 10 PM)
- 📞 Phone number validation and formatting
- ✉️ Email validation
- 👥 Guest count selector (1-9+ guests)
- 💬 Optional special requests field
- ✔️ Success confirmation message

## How to Use

1. Open `index.html` in any modern web browser
2. Fill in the required fields:
   - Full Name
   - Email Address
   - Phone Number
   - Reservation Date
   - Reservation Time
   - Number of Guests
3. Optionally add special requests (dietary restrictions, occasions, etc.)
4. Click "Reserve Table" to submit
5. View the confirmation message

## Technical Details

### Files
- `index.html` - Main HTML structure
- `styles.css` - Styling and responsive design
- `script.js` - Form validation and interactivity

### Validation Rules
- All required fields must be filled
- Phone number must contain at least 10 digits
- Date must be today or in the future
- Time must be between 11:00 AM and 10:00 PM
- Email must be in valid format

## Local Development

Simply open `index.html` in a web browser, or use a local server:

```bash
# Using Python
python3 -m http.server 8000

# Using Node.js
npx http-server
```

Then navigate to `http://localhost:8000` in your browser.

## Future Enhancements

- Backend API integration for storing reservations
- Email confirmation sending
- Calendar integration
- Admin dashboard for managing bookings
- Multi-language support
