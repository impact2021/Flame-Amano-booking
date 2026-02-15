# Quick Start Guide

## For the Site Administrator

After receiving your Cloudflare Turnstile keys, follow these simple steps:

### Option 1: Using wp-config.php (Recommended - More Secure)

1. Open your WordPress site's `wp-config.php` file
2. Find the line that says `/* That's all, stop editing! Happy publishing. */`
3. **Before that line**, add:

```php
// Cloudflare Turnstile Configuration
define( 'FLAME_TURNSTILE_SITE_KEY', 'your-actual-site-key-here' );
define( 'FLAME_TURNSTILE_SECRET_KEY', 'your-actual-secret-key-here' );
```

4. Save the file
5. The booking form will now show the Turnstile challenge

### Option 2: Direct Plugin Configuration

1. Open `flame-amano-booking.php`
2. Find these lines (around line 18-21):

```php
if ( ! defined( 'FLAME_TURNSTILE_SITE_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SITE_KEY', '' ); // Add your site key here
}
if ( ! defined( 'FLAME_TURNSTILE_SECRET_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SECRET_KEY', '' ); // Add your secret key here
}
```

3. Replace the empty strings with your keys:

```php
if ( ! defined( 'FLAME_TURNSTILE_SITE_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SITE_KEY', 'your-actual-site-key-here' );
}
if ( ! defined( 'FLAME_TURNSTILE_SECRET_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SECRET_KEY', 'your-actual-secret-key-here' );
}
```

4. Save and upload the file to your server

## What Users Will See

When Turnstile is enabled, users filling out the booking form will see:
- All the normal form fields (people count, date, time, name, contact, email, details)
- A Turnstile verification widget (typically a checkbox or automatic verification)
- The submit button

The verification is quick and user-friendly, typically requiring just a checkbox click or happening automatically.

## Testing

After configuration:
1. Visit the page with the booking form
2. You should see the Turnstile widget appear
3. Try submitting without completing the challenge - it should be blocked
4. Complete the challenge and submit - it should work normally

## Troubleshooting

**Widget not showing?**
- Check that both keys are configured correctly
- Ensure there are no typos in the keys
- Check browser console for JavaScript errors

**Form blocked even with valid completion?**
- Verify the secret key is correct
- Check that your server can reach Cloudflare's API (https://challenges.cloudflare.com)
- Review server error logs for API communication issues

**Need to disable temporarily?**
- Simply remove the keys or set them to empty strings
- The form will work normally without Turnstile

## Support

For Cloudflare Turnstile setup and key generation:
- Visit: https://dash.cloudflare.com/
- Documentation: https://developers.cloudflare.com/turnstile/

For plugin-specific issues, contact the plugin developer.
