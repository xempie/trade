# Deployment Guide - Updated Project Structure

## 📁 New Project Structure (2025-09-13)

The project has been reorganized into two parts:
- **Root (`/`)**: Landing page and documentation
- **Trading App (`/ta/`)**: All application files

## 🚀 Two-Part Deployment Process

### Method 1: SSH Deployment (Recommended)

**Deploy Landing Page:**
```bash
scp index.html root@88.99.191.227:/var/www/html/
```

**Deploy Trading App:**
```bash
cd ta/
scp -r * root@88.99.191.227:/var/www/html/ta/
```

### Method 2: Using Deployment Scripts

1. **Configure deployment settings:**
   ```bash
   cd ta/
   cp deploy-config.example.php deploy-config.php
   ```
   Edit `deploy-config.php` with your server details.

2. **Set up production environment:**
   ```bash
   cp .env.example .env
   ```
   Fill in your live server environment variables.

3. **Deploy Trading App:**
   ```bash
   php simple-deploy.php
   ```

4. **Deploy Landing Page manually:**
   Upload root `index.html` to `/var/www/html/`

## Server Configuration

### SSH Access (Recommended)
```
Server: 88.99.191.227
User: root
SSH Key: ~/.ssh/trading_server_key
Alias: trading-server
```

### Target Directories
```
Landing Page: /var/www/html/index.html
Trading App: /var/www/html/ta/ (entire directory)
```

### Production Environment Variables
Fill out `/ta/.env` with:
- Database credentials (MySQL: ashkan/TradingApp2025!)
- BingX live API keys
- Telegram live bot token
- APP_URL=https://brainity.com.au/ta

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

1. **Verify environment file** `/var/www/html/ta/.env` has correct production settings
2. **Set up cron jobs** for background monitoring scripts in `/ta/jobs/`
3. **Test connectivity**:
   - Homepage: https://brainity.com.au/
   - Trading App: https://brainity.com.au/ta/
   - Database: Check connection via admin panel

## Quick Deployment Commands

### SSH Method (Recommended)
```bash
# Deploy landing page
scp index.html trading-server:/var/www/html/

# Deploy trading app (from ta/ directory)
cd ta/
scp -r * trading-server:/var/www/html/ta/
```

### Script Method
```bash
# From ta/ directory
php simple-deploy.php

# Then manually upload root index.html
```

## File Structure After Deployment

```
/var/www/html/
├── index.html                 # Landing page
└── ta/                       # Trading application
    ├── .env                  # Production environment
    ├── index.php             # App entry point
    ├── api/                  # API endpoints
    ├── auth/                 # Authentication
    ├── assets/               # CSS, JS, images
    └── ...                   # All other app files
```