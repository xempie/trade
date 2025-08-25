# Gmail Authentication Setup Guide

## Overview
Secure Google OAuth login system that restricts access to authorized Gmail accounts only.

## Google OAuth Setup

### 1. Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the "Google+ API" and "Google OAuth2 API"

### 2. Configure OAuth Consent Screen
1. Go to "APIs & Services" > "OAuth consent screen"
2. Choose "External" user type
3. Fill required fields:
   - App name: "Crypto Trading App"
   - User support email: Your Gmail
   - Developer contact: Your Gmail
4. Add scopes: `email`, `profile`
5. Add test users (your Gmail accounts)

### 3. Create OAuth Credentials
1. Go to "APIs & Services" > "Credentials"
2. Click "+ CREATE CREDENTIALS" > "OAuth client ID"
3. Choose "Web application"
4. Add authorized redirect URIs:
   - Development: `http://localhost/trade/auth/callback.php`
   - Production: `https://yourdomain.com/your-app-folder/auth/callback.php`
5. Save Client ID and Client Secret

## Environment Configuration

### Development (.env.dev)
```env
[REDACTED_CLIENT_ID]
[REDACTED_CLIENT_SECRET]
ALLOWED_EMAILS=your-email@gmail.com,admin@gmail.com
```

### Production (.env.prod)
```env
[REDACTED_CLIENT_ID]
[REDACTED_CLIENT_SECRET]
ALLOWED_EMAILS=your-live-email@gmail.com
```

## Security Features

✅ **Gmail-only authentication** - Only Google accounts allowed
✅ **Email whitelist** - Only specified Gmail accounts can access
✅ **Session security** - HTTP-only cookies, secure flags
✅ **CSRF protection** - State parameter validation
✅ **API protection** - All endpoints require authentication
✅ **Automatic logout** - Session management with timeout

## Protected Resources

- **Main App**: `index.php` (converted from index.html)
- **API Endpoints**: All `/api/*.php` files require authentication
- **Background Jobs**: Cron jobs run independently (not web-protected)

## Login Flow

1. User visits protected page → redirected to `/auth/login.php`
2. Click "Sign in with Google" → Google OAuth consent
3. Google redirects to `/auth/callback.php` with authorization code
4. App exchanges code for access token and gets user info
5. If email is in whitelist → create session and redirect to app
6. If not authorized → show access denied message

## File Structure
```
auth/
├── config.php           # Authentication configuration
├── login.php           # Google Sign-In page
├── callback.php        # OAuth callback handler
├── logout.php          # Logout handler
└── api_protection.php  # API middleware protection
```

## Usage

### Protect a Page
```php
<?php
require_once 'auth/config.php';
requireAuth(); // Redirects to login if not authenticated

$user = getCurrentUser(); // Get current user info
?>
```

### Protect an API Endpoint
```php
<?php
require_once '../auth/api_protection.php';
protectAPI(); // Returns JSON error if not authenticated
?>
```

## Managing Access

### Add/Remove Users
Edit the `ALLOWED_EMAILS` in your environment file:
```env
ALLOWED_EMAILS=user1@gmail.com,user2@gmail.com,admin@gmail.com
```

### Check Current User
```php
$user = getCurrentUser();
// Returns: ['email' => '...', 'name' => '...', 'picture' => '...']
```

## Troubleshooting

### "Access Denied" Error
- Check if Gmail account is listed in `ALLOWED_EMAILS`
- Verify email format (no spaces, comma-separated)

### OAuth Errors
- Verify Google Client ID/Secret are correct
- Check redirect URI matches exactly in Google Console
- Ensure app domain is authorized

### Session Issues
- Check PHP session configuration
- Verify cookies are enabled in browser
- Check server time synchronization