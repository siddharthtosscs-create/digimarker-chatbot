# DigiMarker Chat API - 500 Error Troubleshooting Guide

## Quick Diagnosis

### Step 1: Run Diagnostic Tool

Access the diagnostic endpoint to identify the exact issue:

```
https://yourdomain.com/api/diagnostic.php
```

This will show:
- PHP extensions status
- API key configuration
- FAQ file location
- File permissions
- HTTPS outbound connectivity
- All critical issues

### Step 2: Check Error Messages

The enhanced `chat.php` now provides detailed error messages. Common errors:

#### Error: "PHP cURL extension is not enabled"
**Fix:**
- **cPanel**: Go to `cPanel → Select PHP Version → Extensions → Check "curl" → Save`
- **VPS/Server**: Install `php-curl` package
- **php.ini**: Add `extension=curl`

#### Error: "Gemini API key not configured"
**Fix:**
- **Option 1 (Recommended)**: Set environment variable `GEMINI_API_KEY` in hosting control panel
  - cPanel: `cPanel → Environment Variables → Add GEMINI_API_KEY`
- **Option 2**: Create/update `api/config.local.php`:
  ```php
  <?php
  return [
    'GEMINI_API_KEY' => 'your_actual_api_key_here',
  ];
  ```
- Get API key from: https://aistudio.google.com/app/apikey

#### Error: "FAQ context file missing or not readable"
**Fix:**
1. Ensure `data/chatbot_faq.json` exists in project root
2. Set correct permissions: `chmod 644 data/chatbot_faq.json`
3. Verify directory structure matches hosting setup
4. If using `public_html`, ensure `data/` folder is at same level as `api/`

#### Error: "SSL/TLS issue" or "Connection failed"
**Fix:**
- Some shared hosts block outbound HTTPS connections
- Contact hosting support to:
  - Whitelist `generativelanguage.googleapis.com`
  - Allow outbound connections on port 443
  - Check firewall settings
- **Alternative**: Use VPS or dedicated server

## Hosting-Specific Solutions

### Shared Hosting (cPanel)

1. **Enable PHP Extensions**
   - `cPanel → Select PHP Version → Extensions`
   - Enable: `curl`, `json`, `mbstring`, `openssl`

2. **Set Environment Variables**
   - `cPanel → Environment Variables`
   - Add: `GEMINI_API_KEY` = `your_key`
   - Add: `DIGIMARKER_DEBUG` = `1` (for debugging, set to `0` in production)

3. **File Permissions**
   ```bash
   chmod 644 api/chat.php
   chmod 600 api/config.local.php
   chmod 755 data/
   chmod 644 data/chatbot_faq.json
   ```

4. **Check ModSecurity**
   - If ModSecurity blocks requests, disable it in `.htaccess` (already configured)
   - Or disable in cPanel: `cPanel → Security → ModSecurity`

5. **Check PHP Error Log**
   - `cPanel → Metrics → Errors`
   - Look for specific PHP errors

### VPS / Dedicated Server

1. **Install PHP Extensions**
   ```bash
   # Ubuntu/Debian
   sudo apt-get update
   sudo apt-get install php-curl php-json php-mbstring php-openssl
   
   # CentOS/RHEL
   sudo yum install php-curl php-json php-mbstring php-openssl
   ```

2. **Set Environment Variables**
   - Add to `/etc/environment` or PHP-FPM pool config:
     ```
     GEMINI_API_KEY=your_key_here
     ```

3. **Test HTTPS Outbound**
   ```bash
   curl -I https://generativelanguage.googleapis.com
   ```

### Cloud Hosting (AWS, Azure, etc.)

1. **Security Groups / Firewall**
   - Allow outbound HTTPS (port 443) to `generativelanguage.googleapis.com`

2. **Environment Variables**
   - Set via hosting platform's environment variable configuration
   - Or use `.env` file (if supported)

## Testing the API

### Test 1: Diagnostic Endpoint
```bash
curl https://yourdomain.com/api/diagnostic.php
```

### Test 2: Direct API Test
```bash
curl -X POST https://yourdomain.com/api/chat.php \
  -H "Content-Type: application/json" \
  -d '{"question":"What is DigiMarker?"}'
```

### Test 3: From Browser Console
```javascript
fetch('https://yourdomain.com/api/chat.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ question: 'What is DigiMarker?' })
})
.then(r => r.json())
.then(console.log)
.catch(console.error);
```

## Common Issues & Solutions

### Issue: "Request failed (500)" from Frontend

1. **Check Browser Console** - Look for CORS errors
2. **Check Network Tab** - See actual error response
3. **Run Diagnostic** - `https://yourdomain.com/api/diagnostic.php`
4. **Check Server Error Log** - Look for PHP fatal errors

### Issue: Works Locally but Not on Hosting

**Most Common Causes:**
1. **Missing API Key** - Environment variable not set on hosting
2. **Wrong File Paths** - Different directory structure on hosting
3. **Missing Extensions** - cURL not enabled on hosting
4. **Blocked Outbound HTTPS** - Hosting firewall blocks external API calls
5. **File Permissions** - Files not readable by web server

### Issue: SSL Certificate Errors

If you see SSL errors:
1. **Check CA Bundle** - System CA certificates may be missing
2. **Contact Hosting** - Some hosts have outdated CA bundles
3. **Temporary Workaround** (NOT RECOMMENDED for production):
   - Set `CURLOPT_SSL_VERIFYPEER => false` in `chat.php` (security risk!)

## Security Notes

1. **Never expose API keys** in frontend code
2. **Protect `config.local.php`** - Use `.htaccess` to deny direct access:
   ```apache
   <Files "config.local.php">
     Require all denied
   </Files>
   ```
3. **Remove `diagnostic.php`** in production or protect it with authentication
4. **Set `DIGIMARKER_DEBUG=0`** in production to hide error details

## File Structure

```
digimarker/
├── api/
│   ├── chat.php          (644 - main API)
│   ├── config.local.php  (600 - contains API key)
│   ├── diagnostic.php    (644 - remove in production)
│   └── .htaccess         (protects config files)
├── data/
│   └── chatbot_faq.json  (644 - FAQ data)
└── index.php
```

## Still Having Issues?

1. **Enable Debug Mode**: Set `DIGIMARKER_DEBUG=1` in environment
2. **Check Error Logs**: Look at PHP error log and Apache/Nginx logs
3. **Run Diagnostic**: Access `/api/diagnostic.php` for full system check
4. **Contact Hosting Support**: If outbound HTTPS is blocked, you may need VPS

## Production Checklist

- [ ] All PHP extensions enabled (curl, json, mbstring)
- [ ] `GEMINI_API_KEY` set in environment or config.local.php
- [ ] `data/chatbot_faq.json` exists and is readable
- [ ] File permissions correct (644 for files, 755 for directories)
- [ ] HTTPS outbound test passes
- [ ] `DIGIMARKER_DEBUG=0` in production
- [ ] `diagnostic.php` removed or protected
- [ ] CORS configured correctly
- [ ] Error logging enabled

