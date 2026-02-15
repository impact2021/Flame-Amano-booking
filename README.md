# Flame-Amano-booking

WordPress plugin for Flame Amano restaurant booking form with Cloudflare Turnstile spam protection.

## Features

- Booking form for 1-14 people
- Date and time selection (5pm-8:30pm in 15-minute intervals)
- Customer information collection (name, contact, email)
- Additional details and allergy notes field
- Email notifications to restaurant and customer
- Responsive design (two columns on desktop, single column on mobile)
- **Cloudflare Turnstile spam protection** (NEW in v1.4.0)

## Setup

### Basic Installation

1. Upload the plugin to your WordPress installation
2. Activate the plugin
3. Add the shortcode `[flame_amano_booking_form]` to any page

### Enable Spam Protection

To enable Cloudflare Turnstile protection:

1. Get your Turnstile keys from [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Follow the instructions in [QUICK_START.md](QUICK_START.md)

**Quick setup**: Add to your `wp-config.php`:
```php
define( 'FLAME_TURNSTILE_SITE_KEY', 'your-site-key' );
define( 'FLAME_TURNSTILE_SECRET_KEY', 'your-secret-key' );
```

## Documentation

- [Quick Start Guide](QUICK_START.md) - Getting started with Turnstile
- [Turnstile Setup](TURNSTILE_SETUP.md) - Detailed setup instructions
- [Integration Flow](INTEGRATION_FLOW.md) - How Turnstile works
- [Implementation Summary](IMPLEMENTATION_SUMMARY.md) - Technical details

## Version History

- **1.4.0** - Added Cloudflare Turnstile spam protection
- **1.3.2** - Previous stable version

## Support

For issues or questions, please contact Impact Websites.

