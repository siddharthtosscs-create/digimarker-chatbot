# Admin/Agent Panel Setup Guide

## 🔑 Setting Up the Agent API Key

The Agent API Key is required to access the admin panel. You need to set it in **two places**:

### 1. Backend Configuration (Server-Side)

**Option A: Config File (Recommended for XAMPP/Local Development)**

Edit `config/config.local.php`:

```php
return [
  'GEMINI_API_KEY' => 'your_gemini_key',
  'DIGIMARKER_AGENT_API_KEY' => 'your_agent_api_key_here',  // ← Add this line
  // ... other config
];
```

**Option B: Environment Variable (Recommended for Production)**

Set the environment variable on your server:

**For XAMPP (Windows):**
1. Edit `C:\xampp\apache\conf\httpd.conf`
2. Add: `SetEnv DIGIMARKER_AGENT_API_KEY "your_agent_api_key_here"`
3. Restart Apache

**For Linux/Apache:**
```bash
# In your .htaccess or Apache config
SetEnv DIGIMARKER_AGENT_API_KEY "your_agent_api_key_here"
```

**For cPanel:**
1. Go to cPanel → Environment Variables
2. Add:
   - Name: `DIGIMARKER_AGENT_API_KEY`
   - Value: `your_agent_api_key_here`

### 2. Frontend (Browser)

When you first open `admin.php`, you'll be prompted to enter the API key. This is stored in your browser's localStorage.

**To change the API key later:**
1. Open browser console (F12)
2. Run: `localStorage.removeItem('digimarker_agent_api_key')`
3. Refresh the page - you'll be prompted again

## 🔐 Generating a Secure API Key

Generate a secure random key:

**Windows (PowerShell):**
```powershell
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object {[char]$_})
```

**Linux/Mac:**
```bash
openssl rand -hex 32
```

**Online:**
Use a password generator to create a 32+ character random string.

## 📍 Where to Enter the API Key

### Step 1: Set Backend Key

1. Open `config/config.local.php`
2. Add or update:
   ```php
   'DIGIMARKER_AGENT_API_KEY' => 'my_secret_key_12345',
   ```
3. Save the file

### Step 2: Enter in Browser

1. Open `http://localhost/digimarker/public/admin.php`
2. When prompted, enter the **same key** you set in the config file
3. Click OK
4. The key is saved in localStorage for future visits

## ✅ Testing

1. Open the admin panel
2. If you see "Unauthorized" errors, check:
   - The key in `config.local.php` matches what you entered
   - The config file is in the correct location
   - Apache/PHP has been restarted (if using environment variables)

## 🔄 Quick Reference

| Location | File/Place | Purpose |
|----------|------------|---------|
| **Backend** | `config/config.local.php` | Server-side validation |
| **Frontend** | Browser prompt (stored in localStorage) | Sent in API requests |

**Important:** Both must match! The backend checks the key you send from the frontend against the key in the config file.

## 🛠️ Troubleshooting

**"Unauthorized" error:**
- Check that the key in `config.local.php` matches what you entered in the browser
- Clear browser localStorage and re-enter
- Check PHP error logs for authentication failures

**No prompt appears:**
- Check browser console for errors
- Ensure `admin.js` is loaded correctly
- Try clearing browser cache

**Key not working:**
- Verify the key is exactly the same (no extra spaces)
- Restart Apache/PHP if using environment variables
- Check that `config.local.php` is being loaded (enable debug mode)

