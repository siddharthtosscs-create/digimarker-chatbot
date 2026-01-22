# Quick Fix Guide - 500 Error

## 🚀 Immediate Steps

### 1. Run Diagnostic (2 minutes)
```
https://yourdomain.com/api/diagnostic.php
```
**This tells you exactly what's wrong.**

### 2. Fix Based on Diagnostic Output

#### If diagnostic shows "cURL not loaded":
```
cPanel → Select PHP Version → Extensions → Enable "curl" → Save
```

#### If diagnostic shows "API key not configured":
**Option A (Recommended):**
```
cPanel → Environment Variables → Add:
  Name: GEMINI_API_KEY
  Value: your_actual_api_key
```

**Option B:**
Create/edit `api/config.local.php`:
```php
<?php
return [
  'GEMINI_API_KEY' => 'your_actual_api_key_here',
];
```

#### If diagnostic shows "FAQ file not found":
1. Ensure `data/chatbot_faq.json` exists
2. Set permissions: `chmod 644 data/chatbot_faq.json`
3. Check diagnostic output for exact path

#### If diagnostic shows "HTTPS test failed":
- Contact hosting support to allow outbound HTTPS
- Or consider VPS/dedicated server

### 3. Test
```bash
curl -X POST https://yourdomain.com/api/chat.php \
  -H "Content-Type: application/json" \
  -d '{"question":"test"}'
```

## 📋 Common Fixes

| Error | Fix |
|-------|-----|
| cURL not enabled | Enable in cPanel PHP Extensions |
| API key missing | Set `GEMINI_API_KEY` env var or config.local.php |
| FAQ file missing | Upload `data/chatbot_faq.json` |
| HTTPS blocked | Contact hosting or use VPS |
| File permissions | `chmod 644` for files, `755` for dirs |

## 🔍 Still Not Working?

1. Check diagnostic output again
2. Enable debug: Set `DIGIMARKER_DEBUG=1` in environment
3. Check PHP error log in cPanel
4. Review `TROUBLESHOOTING.md` for detailed steps

---

**The diagnostic tool is your best friend - it shows exactly what's wrong!**

