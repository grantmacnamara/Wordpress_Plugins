# WhiskyAI Plugin - Troubleshooting Guide

## 503 Service Unavailable Error

### What This Means
The server is temporarily unable to handle the request. This is typically a hosting/server issue, not an API problem.

### Common Causes

1. **Server is Busy**
   - Too many requests at once
   - High resource usage on shared hosting
   - Solution: Try again later or contact your host

2. **PHP Timeout**
   - Request is taking too long
   - Increased by this plugin to 20 seconds
   - Check with your host if they have longer timeout limits

3. **Memory Limit Exceeded**
   - WordPress is running out of PHP memory
   - Default is usually 256MB
   - Solution: Check `wp-config.php` - increase `WP_MEMORY_LIMIT` to `512M`

4. **Hosting Restrictions**
   - Some shared hosts block external API requests
   - Check your hosting panel for API restrictions
   - Contact your host to whitelist the Gemini API domain

### How to Debug

#### Step 1: Check WordPress Error Logs

Your hosting provider's error log is the first place to check:

1. Log into your hosting control panel (cPanel, Plesk, etc.)
2. Find "Error Logs" or "Raw Logs"
3. Look for errors containing `[WhiskyAI]` - these are our detailed logs
4. Look for PHP fatal errors, memory limit errors, or timeout errors

#### Step 2: Check What's Logged

Our plugin now logs detailed information to help diagnose issues:

```
[WhiskyAI] Gemini API Request: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=...
[WhiskyAI] Request body size: 234 bytes
[WhiskyAI] Gemini API Response Code: 200
[WhiskyAI] Success: Generated 445 chars
```

If you see connection errors, timeouts, or 500+ status codes in the logs, report these to your host.

#### Step 3: Enable WordPress Debug Mode

In `wp-config.php`, add:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

This creates `/wp-content/debug.log` where every error is logged. Look for `[WhiskyAI]` entries.

#### Step 4: Test Memory Usage

Add this temporary code to check memory:

In your WordPress admin, run this in a custom plugin or in `functions.php`:

```php
if (current_user_can('manage_options')) {
    error_log('[WhiskyAI] Current memory usage: ' . size_format(memory_get_usage(true)));
    error_log('[WhiskyAI] Memory limit: ' . ini_get('memory_limit'));
}
```

### Solutions

#### Increase PHP Memory Limit

Edit `/wp-config.php` (before `/* That's all, stop editing! */`):

```php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '1024M');
```

#### Check Hosting Restrictions

Contact your hosting provider and ask:

1. "What is the PHP timeout limit?"
2. "Can external HTTPS API calls be made?"
3. "Is there a rate limit on outbound connections?"
4. "What is the PHP memory limit?"
5. "Are there any restrictions on making API calls?"

#### Increase PHP Timeout (if you have access)

In `.htaccess` or ask your host to increase:

```
php_value max_execution_time 60
```

#### Reduce Batch Size

Instead of generating for 100 products at once, try 5-10 products. This uses less memory and is less likely to timeout.

### The 503 Error Response

Our enhanced logging now shows:

```
[WhiskyAI] Server Error: 503 Service Unavailable
```

When you see this, check:

1. WordPress error log (`/wp-content/debug.log`)
2. Hosting error log (via control panel)
3. If the 503 is from Google Gemini API or your host:
   - Gemini timeouts show as: "API Connection Error: ..."
   - Host 503 shows as: "Server Error (HTTP 503)"

### Contact Your Host With This Information

When contacting support, tell them:

1. "My WordPress plugin is making API calls to `generativelanguage.googleapis.com`"
2. "We're getting 503 errors when making these calls"
3. "The requests are POST requests with JSON payloads"
4. "They're taking 15-20 seconds to complete"
5. "We're on shared hosting and see the error happens intermittently"

Then provide them with the error log entries from WordPress (without your API key).

### Quick Fixes to Try First

1. **Wait and retry** - Server might just be busy
2. **Try fewer products** - Instead of "Generate All", try 1-2 products
3. **Clear cache** - Some caching plugins interfere
4. **Disable other plugins** - See if another plugin is conflicting
5. **Contact your host** - They can check server logs you can't access

---

## Other Common Issues

### "API Key is not set or invalid"

- Make sure you've saved your Gemini API key in settings
- The key should be verified (green checkmark)
- Check that the key hasn't expired or been revoked in your Google account

### "No description generated"

- Check the browser console (F12) for detailed errors
- Look in WordPress error log
- Make sure the product name is valid and not too long

### "Categories not matching"

- Check the category prompt in settings
- Make sure the allowed categories are correct
- The API might need the prompt adjusted

---

## If Problem Persists

1. Save all error log entries
2. Note the exact time the error occurred
3. Check your hosting status page for outages
4. Contact hosting support with:
   - Error logs
   - Time of error
   - Number of products being processed
   - Your plugin version (1.3.0)
   - Your WordPress version
