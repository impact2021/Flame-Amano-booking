# Cloudflare Turnstile Setup Instructions

This plugin now includes Cloudflare Turnstile spam protection for the booking form.

## Configuration

To enable Turnstile protection, you need to add your Cloudflare Turnstile site key and secret key to the plugin file.

### Step 1: Get Your Turnstile Keys

If you don't already have Turnstile keys:

1. Log in to your [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Navigate to Turnstile in the left sidebar
3. Click "Add Site" to create a new site
4. Copy your **Site Key** and **Secret Key**

### Step 2: Add Keys to the Plugin

1. Open the file `flame-amano-booking.php`
2. Find these lines near the top of the file (around line 18-19):

```php
define( 'FLAME_TURNSTILE_SITE_KEY', '' ); // Add your site key here
define( 'FLAME_TURNSTILE_SECRET_KEY', '' ); // Add your secret key here
```

3. Replace the empty strings with your actual keys:

```php
define( 'FLAME_TURNSTILE_SITE_KEY', 'your-site-key-here' );
define( 'FLAME_TURNSTILE_SECRET_KEY', 'your-secret-key-here' );
```

### Step 3: Save and Upload

1. Save the file
2. Upload it to your WordPress installation (replace the existing file)
3. The Turnstile widget will now appear on the booking form

## How It Works

- When both keys are configured, a Turnstile challenge widget will appear on the booking form before the submit button
- Users must complete the challenge before submitting the form
- The server validates the Turnstile response with Cloudflare before processing the booking
- If validation fails, the user will see an error message and can try again

## Disabling Turnstile

If you need to temporarily disable Turnstile:

1. Simply leave the key definitions empty (empty strings)
2. The form will work normally without the Turnstile challenge

## Security Note

**Important:** Never commit your secret key to a public repository. The keys should be added directly on your server or in a secure configuration file.
