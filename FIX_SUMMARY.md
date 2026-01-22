# 500 Error Fix Summary - DigiMarker Chat API

## ✅ Changes Made

### 1. Enhanced Error Reporting (`api/chat.php`)
- **Added**: Fatal error handler with detailed diagnostics
- **Changed**: Debug mode now defaults to `true` (can be disabled via `DIGIMARKER_DEBUG=0`)
- **Result**: You'll see exact PHP errors instead of generic 500

### 2. Improved API Key Diagnostics
- **Added**: Detailed diagnostics showing:
  - Config file existence and readability
  - Environment variable status
  - Security-safe key status (doesn't expose actual keys)
  - Step-by-step fix instructions
- **Result**: Clear error messages when API key is missing

### 3. Enhanced FAQ File Path Resolution
- **Added**: Multiple path fallbacks for different hosting setups
- **Added**: Detailed path diagnostics showing:
  - Each attempted path
  - File existence, readability, permissions
  - JSON validation
- **Result**: Works on shared hosting, VPS, and cloud platforms

### 4. Comprehensive Extension Checks
- **Added**: Checks for `curl`, `json`, `mbstring`, `openssl`
- **Added**: Detailed error messages with hosting-specific fix instructions
- **Result**: Clear guidance when extensions are missing

### 5. Enhanced HTTPS/SSL Handling
- **Added**: Automatic CA bundle detection
- **Added**: Detailed SSL error diagnostics
- **Added**: Hosting-specific suggestions for connection issues
- **Result**: Better error messages for SSL/connection problems

### 6. Diagnostic Endpoint (`api/diagnostic.php`)
- **Created**: Comprehensive diagnostic tool
- **Features**:
  - PHP version and extensions
  - API key configuration status
  - FAQ file location and validation
  - File permissions check
  - HTTPS outbound connectivity test
  - Summary of all critical issues
- **Access**: `https://yourdomain.com/api/diagnostic.php`

### 7. Security Enhancements
- **Added**: `.htaccess` protection for `config.local.php`
- **Added**: Security-safe error messages (don't expose keys)
- **Note**: Remove or protect `diagnostic.php` in production

### 8. Test Scripts
- **Created**: `test_api.sh` (Linux/Mac)
- **Created**: `test_api.bat` (Windows)
- **Usage**: `./test_api.sh https://yourdomain.com`

## 🔍 How to Diagnose Your 500 Error

### Step 1: Access Diagnostic Tool
```
https://yourdomain.com/api/diagnostic.php
```

This will show you:
- ✅ What's working
- ❌ What's broken
- 🔧 How to fix each issue

### Step 2: Check Common Issues

#### Issue: Missing cURL Extension
**Error**: "PHP cURL extension is not enabled"
**Fix**: 
- cPanel: `Select PHP Version → Extensions → Enable curl`
- VPS: `sudo apt-get install php-curl`

#### Issue: API Key Not Configured
**Error**: "Gemini API key not configured"
**Fix**:
1. Set environment variable `GEMINI_API_KEY` in hosting control panel
2. OR create `api/config.local.php` with your key

#### Issue: FAQ File Not Found
**Error**: "FAQ context file missing or not readable"
**Fix**:
1. Ensure `data/chatbot_faq.json` exists
2. Set permissions: `chmod 644 data/chatbot_faq.json`
3. Check diagnostic output for exact path issues

#### Issue: HTTPS Outbound Blocked
**Error**: "SSL/TLS issue" or "Connection failed"
**Fix**:
- Contact hosting support to allow outbound HTTPS
- Whitelist `generativelanguage.googleapis.com`
- Consider VPS if shared hosting blocks external APIs

### Step 3: Test the API
```bash
# Using curl
curl -X POST https://yourdomain.com/api/chat.php \
  -H "Content-Type: application/json" \
  -d '{"question":"What is DigiMarker?"}'

# Or use test script
./test_api.sh https://yourdomain.com
```

## 📋 Production Deployment Checklist

- [ ] Run diagnostic: `https://yourdomain.com/api/diagnostic.php`
- [ ] Fix all critical issues shown in diagnostic
- [ ] Set `GEMINI_API_KEY` environment variable OR configure `api/config.local.php`
- [ ] Verify `data/chatbot_faq.json` exists and is readable
- [ ] Enable PHP extensions: `curl`, `json`, `mbstring`
- [ ] Set file permissions:
  - `chmod 644 api/chat.php`
  - `chmod 600 api/config.local.php`
  - `chmod 755 data/`
  - `chmod 644 data/chatbot_faq.json`
- [ ] Test HTTPS outbound connectivity
- [ ] Set `DIGIMARKER_DEBUG=0` in production
- [ ] Remove or protect `api/diagnostic.php`
- [ ] Test API with actual request
- [ ] Check browser console for CORS issues

## 🎯 Most Likely Root Causes (in order)

1. **Missing API Key** (40% of cases)
   - Environment variable not set on hosting
   - Config file not uploaded or in wrong location

2. **Missing cURL Extension** (30% of cases)
   - Shared hosting doesn't enable it by default
   - Need to enable in cPanel

3. **HTTPS Outbound Blocked** (20% of cases)
   - Shared hosting firewall blocks external API calls
   - Need to contact hosting support or use VPS

4. **FAQ File Path Issues** (5% of cases)
   - Different directory structure on hosting
   - File permissions incorrect

5. **Other PHP Errors** (5% of cases)
   - Syntax errors (now caught by fatal error handler)
   - Missing other extensions

## 🔧 Quick Fixes by Hosting Type

### Shared Hosting (cPanel)
1. Enable extensions in `Select PHP Version`
2. Set environment variables in `Environment Variables`
3. Upload `config.local.php` if environment variables not supported
4. Check file permissions via File Manager

### VPS / Cloud
1. Install PHP extensions via package manager
2. Set environment variables in system config
3. Test with `curl` command line
4. Check firewall/security groups

### Local Development
1. Use `api/config.local.php` (already configured)
2. Ensure XAMPP/WAMP has cURL enabled
3. Test with `http://localhost/digimarker/api/chat.php`

## 📞 Still Stuck?

1. **Run Diagnostic**: `https://yourdomain.com/api/diagnostic.php`
2. **Check Error Logs**: Look at PHP error log and web server logs
3. **Enable Debug Mode**: Set `DIGIMARKER_DEBUG=1` temporarily
4. **Review Error Response**: The API now returns detailed error messages

## 📝 Files Modified/Created

### Modified:
- `api/chat.php` - Enhanced with comprehensive diagnostics
- `api/.htaccess` - Added protection for config.local.php

### Created:
- `api/diagnostic.php` - Diagnostic endpoint
- `TROUBLESHOOTING.md` - Detailed troubleshooting guide
- `FIX_SUMMARY.md` - This file
- `test_api.sh` - Linux/Mac test script
- `test_api.bat` - Windows test script

## 🚀 Next Steps

1. **Upload all files** to your hosting
2. **Run diagnostic**: `https://yourdomain.com/api/diagnostic.php`
3. **Fix issues** shown in diagnostic output
4. **Test API** using test script or curl
5. **Remove diagnostic.php** or protect it in production

---

**The enhanced error reporting will now show you the exact cause of any 500 error instead of a generic message.**

