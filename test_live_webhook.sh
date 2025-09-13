#!/bin/bash

# Test the live webhook with a proper signal
curl -X POST https://brainity.com.au/bot/bot.php \
  -H "Content-Type: application/json" \
  -H "User-Agent: TradingView-Webhook/1.0" \
  -d '{
    "symbol": "BTCUSDT",
    "side": "LONG",
    "leverage": 6,
    "entries": [65000, 64000],
    "targets": ["%2", "%4"],
    "stop_loss": "%5",
    "type": "TRADING_SIGNAL",
    "external_signal_id": "test-webhook-'$(date +%s)'"
  }' \
  -v