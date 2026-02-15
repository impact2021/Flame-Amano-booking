# Cloudflare Turnstile Integration Flow

## How It Works

This document explains how the Turnstile integration protects the booking form.

## Form Display Flow

```
User visits page
       ↓
WordPress shortcode [flame_amano_booking_form] renders
       ↓
Check if FLAME_TURNSTILE_SITE_KEY is configured
       ↓
   ┌────────────────────┬─────────────────────┐
   │ Keys Configured    │ Keys Not Configured │
   ↓                    ↓                     
Form with Turnstile    Form without Turnstile
widget displayed       (normal form)
```

## Form Submission Flow (with Turnstile)

```
User fills form and clicks Submit
       ↓
Turnstile validates challenge
       ↓
   ┌──────────────────┬─────────────────┐
   │ Challenge Pass   │ Challenge Fail  │
   ↓                  ↓                 
Form submits with    Form doesn't submit
cf-turnstile-response (handled by Cloudflare)
token
       ↓
Server receives POST
       ↓
WordPress nonce verification
       ↓
Turnstile verification
  - Extract cf-turnstile-response
  - Get client IP
  - Call Cloudflare API
  - Validate JSON response
       ↓
   ┌──────────────────┬─────────────────┐
   │ Valid Response   │ Invalid/Failed  │
   ↓                  ↓                 
Process booking      Show error
  - Validate fields   "Security verification
  - Send emails       failed"
  - Redirect          User can retry
```

## Security Layers

The booking form now has multiple security layers:

1. **WordPress Nonce** (existing)
   - Prevents CSRF attacks
   - Validates request origin

2. **Cloudflare Turnstile** (new)
   - Prevents bot submissions
   - Rate limiting
   - Challenge-based verification

3. **Field Validation** (existing)
   - Required field checks
   - Date validation
   - Email validation

4. **Server-side Sanitization** (existing)
   - All inputs sanitized
   - SQL injection prevention
   - XSS prevention

## API Communication

When a booking is submitted:

```
WordPress Server  ←→  Cloudflare API
     │
     │ POST https://challenges.cloudflare.com/turnstile/v0/siteverify
     ├─ secret: FLAME_TURNSTILE_SECRET_KEY
     ├─ response: cf-turnstile-response (from form)
     └─ remoteip: Client IP address
     │
     ↓ Response
     {
       "success": true/false,
       "error-codes": []
     }
```

## Configuration Precedence

The plugin checks for keys in this order:

1. **wp-config.php** (checked first)
   - If `FLAME_TURNSTILE_SITE_KEY` is already defined
   - Uses that value

2. **Plugin file** (fallback)
   - If not defined in wp-config.php
   - Uses value from plugin file

This allows wp-config.php to override plugin file settings.

## Error Handling

The implementation handles various error scenarios:

| Scenario | User Experience | Technical Details |
|----------|----------------|-------------------|
| Turnstile not completed | Error: "Please complete the security verification." | cf-turnstile-response is empty |
| Network error to Cloudflare | Error: "Security verification failed. Please try again." | wp_remote_post() returns WP_Error |
| Invalid API response | Error: "Security verification failed. Please try again." | JSON decode fails or success=false |
| Keys not configured | Form works normally | Turnstile check is skipped entirely |

## Performance Impact

- **Page Load**: Minimal (~50KB Cloudflare JS, async loaded)
- **Form Submission**: Adds ~200-500ms for API verification
- **User Experience**: Challenge typically completes in <1 second

## Browser Compatibility

Cloudflare Turnstile works on:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- Accessibility: Screen reader compatible

## Privacy Considerations

Cloudflare Turnstile:
- Does NOT use cookies
- Does NOT track users across sites
- More privacy-friendly than reCAPTCHA
- Minimal data collection (only for challenge verification)

## Maintenance

**Plugin Updates**:
- If keys are in wp-config.php → Safe, no action needed
- If keys are in plugin file → Re-add after update

**Monitoring**:
- Check form submissions are being received
- Monitor for false positives (legitimate users blocked)
- Review Cloudflare Turnstile dashboard for statistics

**Adjusting Security**:
- Cloudflare Dashboard allows changing challenge difficulty
- Can switch between managed, non-interactive, or invisible modes
- No code changes needed for mode adjustments
