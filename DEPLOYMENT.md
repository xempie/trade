# Safe cPanel Deployment Guide

## Quick Setup

1. **Configure deployment settings:**
   ```bash
   cp deploy-config.example.php deploy-config.php
   ```
   Edit `deploy-config.php` with your cPanel FTP details.

2. **Set up production environment:**
   ```bash
   cp .env.prod.example .env.prod
   ```
   Fill in your live server environment variables.

3. **Deploy:**
   ```bash
   php deploy.php
   ```

## What You Need to Provide

### FTP/SFTP Credentials
```php
'host' => 'your-server.com',
'username' => 'your_cpanel_username', 
'password' => 'your_cpanel_password',
'remote_path' => '/public_html/trading-app'
```

### Production Environment Variables
Fill out `.env.prod` with:
- Database credentials (your live MySQL details)
- BingX live API keys
- Telegram live bot token
- Your domain URL

## Safety Features

✅ **Isolated deployment** - Only touches your specified folder
✅ **Automatic backup** - Creates backup before deploying  
✅ **File exclusions** - Doesn't upload dev files, configs, or docs
✅ **Permission handling** - Sets correct file permissions
✅ **Rollback capability** - Can revert if issues occur
✅ **Deployment logging** - Tracks all actions

## Files Excluded from Deployment

- Development configs (`.env.dev`, `deploy-config.php`)
- Documentation (`README.md`, `CLAUDE.md`, etc.)
- Git files and Windows batch files
- Debug and cleanup HTML files

## Manual Steps After Deployment

1. **Upload `.env.prod`** manually via cPanel File Manager (rename to `.env`)
2. **Set up cron jobs** in cPanel for the `/jobs/` scripts
3. **Test API connectivity** by visiting `/api/test.php`

## Commands

```bash
# Test deployment (dry run)
php deploy.php --dry-run

# Full deployment
php deploy.php

# Check deployment log
cat deployment.log
```