# Crypto Trading Management App

Personal web application for managing crypto futures trading signals with BingX exchange integration, automated order placement, watchlist management, and Telegram notifications.

## 📁 Project Structure

This project is organized into two main parts:

### **Root Directory (`/`)**
- **Landing Page**: `index.html` - Professional homepage for brainity.com.au
- **Documentation**: All `*.md` files containing project documentation
- **Configuration**: Git repository (`.git/`) and Claude settings (`.claude/`)

### **Trading Application (`/ta/`)**
- **Application Files**: All PHP files, configuration, and assets
- **Environment**: `.env` file with API keys and database settings
- **API Endpoints**: `/ta/api/` directory
- **Authentication**: `/ta/auth/` directory
- **Assets**: `/ta/assets/` directory (CSS, JS, images)

## 🌐 URLs

- **Homepage**: https://brainity.com.au/ (landing page)
- **Trading App**: https://brainity.com.au/ta/ (full application)
- **OAuth Callback**: https://brainity.com.au/ta/auth/callback.php

## Features

- **Signal Management**: Create signals with multiple entry points (market + entry 2 + entry 3)
- **Order Placement**: Automated BingX order placement with position size calculation
- **Watchlist**: Price alerts for entry points and profit levels
- **Position Tracking**: Real-time P&L and margin usage monitoring
- **Notifications**: Telegram alerts for price triggers and order fills

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript/React
- **Backend**: PHP
- **Database**: MySQL
- **APIs**: BingX Futures API, Telegram Bot API

## Setup

1. **Environment Configuration**: 
   - Navigate to `/ta/` directory
   - Copy `.env.example` to `.env` and fill in your API keys and database credentials
2. **Database Setup**: 
   - Set up MySQL database named `crypto_trading`
   - Import required SQL files from `/ta/` directory
3. **Web Server Configuration**: 
   - Configure web server DocumentRoot to project root
   - Ensure `/ta/` subdirectory is accessible
4. **Cron Jobs**: 
   - Set up background monitoring jobs (see `/ta/cron-setup.php`)

## Deployment

### Two-Part Deployment Process:
1. **Landing Page**: Deploy `index.html` to server root
2. **Trading App**: Deploy entire `/ta/` folder to server `/ta/` directory

### File Locations:
- **Deployment Scripts**: Located in `/ta/` directory
- **Configuration**: All app config files are in `/ta/`
- **Environment**: `.env` file is in `/ta/` directory

## Configuration

- **Position Sizing**: 3.3% of available funds per entry point
- **Maximum Leverage**: 10x
- **Exchange**: BingX USDT-M Futures only

## Environment Variables

See `/ta/.env` file for required configuration variables including:
- Database credentials (MySQL connection info)
- BingX API keys (demo/live trading)
- Telegram bot configuration (notifications)
- Google OAuth configuration (authentication)
- Trading parameters and position sizing

## Important Notes

- **All application files** are located in the `/ta/` directory
- **Landing page** (`index.html`) serves as the homepage
- **Documentation** remains in the root directory for easy access
- **Deployment scripts** handle both root and `/ta/` deployment automatically