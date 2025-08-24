# Crypto Trading Management App

Personal web application for managing crypto futures trading signals with BingX exchange integration, automated order placement, watchlist management, and Telegram notifications.

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

1. Copy `.env.example` to `.env` and fill in your API keys and database credentials
2. Set up MySQL database named `crypto_trading`
3. Configure web server to point to this directory
4. Set up cron jobs for background monitoring

## Configuration

- **Position Sizing**: 3.3% of available funds per entry point
- **Maximum Leverage**: 10x
- **Exchange**: BingX USDT-M Futures only

## Environment Variables

See `.env` file for required configuration variables including:
- Database credentials
- BingX API keys
- Telegram bot configuration