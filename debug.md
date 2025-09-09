# Debug and Testing Documentation

**⚠️ CRITICAL: This project is NOT running on localhost - it's on a LIVE SERVER. Never test anything on localhost.**

## Project Information
- **Live Server**: https://brainity.com.au/ta/
- **Deployment Path**: public_html/addons/brainity/ta/
- **Environment**: Production (Live Server)
- **Testing**: Must be done on live server environment

## ⚠️ COMMON MISTAKE: Localhost URLs
**NEVER use localhost URLs in any test files or API calls on the live server:**
```php
// ❌ WRONG - Will cause 404 errors on live server
$url = 'http://localhost/trade/api/place_order.php';

// ✅ CORRECT - Use live server URLs
$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'brainity.com.au');
$url = $baseUrl . '/ta/api/place_order.php';
```

---

## Debug History

### 2025-09-09: Demo Trading API Issues

#### **Problem**
Demo trading functionality was failing with HTTP 404 errors when accessing the API endpoint.

**Error Details:**
```
[09-Sep-2025 15:59:39 Australia/Sydney] PHP Warning: require_once(../auth/api_protection.php): failed to open stream: No such file or directory in /home/vahid279/public_html/addons/brainity/ta/api/place_order.php on line 3
[09-Sep-2025 15:59:39 Australia/Sydney] PHP Fatal error: require_once(): Failed opening required '../auth/api_protection.php'
```

#### **Root Cause**
Incorrect relative path in `api/place_order.php` line 3:
```php
// BROKEN - relative path not resolving correctly
require_once '../auth/api_protection.php';
```

#### **Solution**
Fixed the require path using `__DIR__` for absolute path resolution:
```php
// FIXED - absolute path resolution
require_once __DIR__ . '/../auth/api_protection.php';
```

#### **Testing Performed**
1. **Trading Mode Configuration Test**: Verified TRADING_MODE environment variable switching
2. **API Helper Functions Test**: Confirmed getBingXApiUrl() returns correct URLs
3. **Demo Order Simulation Test**: Verified demo orders are simulated correctly
4. **Path Resolution Test**: Confirmed all file includes work properly

#### **Test Results**
- ✅ Trading mode switching (demo/live): Working
- ✅ API helper functions: Working  
- ✅ Demo order simulation: Working
- ✅ Path resolution: Fixed
- ✅ Environment variable handling: Working

---

## Common Debugging Patterns

### **File Path Issues**
**Problem**: Relative paths failing in PHP includes
**Solution**: Always use `__DIR__` for reliable path resolution
```php
// Good
require_once __DIR__ . '/../path/to/file.php';

// Avoid
require_once '../path/to/file.php';
```

### **Environment Variables**
**Check Trading Mode:**
```php
$tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
echo "Current mode: " . $tradingMode;
```

**Debug Environment Loading:**
```php
// Check if .env is loaded
$vars = ['TRADING_MODE', 'BINGX_API_KEY', 'DB_HOST'];
foreach ($vars as $var) {
    echo "$var: " . (getenv($var) ?: 'NOT SET') . "\n";
}
```

### **API Testing**
**Demo Mode Verification:**
```php
require_once './api/api_helper.php';
$info = getTradingModeInfo();
print_r($info);
```

**Database Connection Test:**
```php
try {
    $pdo = getDbConnection();
    echo "✅ Database connected successfully\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
```

---

## Testing Guidelines

### **⚠️ Live Server Testing Only**
- Never test on localhost - project runs on live server
- Always test on https://brainity.com.au/ta/
- Use PHP CLI on server for safe testing

### **Safe Testing Methods**
1. **CLI Testing**: Create test files and run via PHP CLI
2. **Environment Isolation**: Temporarily switch to demo mode for testing
3. **Debug Logging**: Use error_log() for debugging without disrupting users

### **Test File Template**
```php
<?php
// Safe test template for live server
echo "=== Debug Test ===\n";

// Set demo mode temporarily
$originalMode = getenv('TRADING_MODE');
putenv('TRADING_MODE=demo');

try {
    // Your test code here
    
} finally {
    // Always restore original mode
    putenv('TRADING_MODE=' . $originalMode);
}

echo "✅ Test complete\n";
?>
```

---

## Error Handling Patterns

### **API Error Handling**
```php
try {
    $result = placeBingXOrder($apiKey, $apiSecret, $orderData);
    if (!$result['success']) {
        error_log("Order failed: " . $result['error']);
        // Handle gracefully
    }
} catch (Exception $e) {
    error_log("API Exception: " . $e->getMessage());
    // Return user-friendly error
}
```

### **Database Error Handling**
```php
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    return false; // Don't expose DB details to user
}
```

### **Environment Error Handling**
```php
// Check critical environment variables
$required = ['BINGX_API_KEY', 'BINGX_SECRET_KEY', 'DB_HOST'];
foreach ($required as $var) {
    if (!getenv($var)) {
        throw new Exception("Missing required environment variable: $var");
    }
}
```

---

## Debug Log Locations

### **Application Logs**
- `debug.log` - Application debug messages
- `error.log` - PHP error messages
- `deployment.log` - Deployment history

### **Server Logs**
- cPanel Error Logs (check hosting control panel)
- PHP error logs in server directories

### **Log Monitoring Commands**
```bash
# View recent debug entries
tail -20 debug.log

# Monitor logs in real-time
tail -f debug.log

# Search for specific errors
grep "ERROR" debug.log
```

---

## Deployment Debugging

### **Common Deployment Issues**
1. **File Permissions**: Ensure 644 for files, 755 for directories
2. **Path Issues**: Use absolute paths with __DIR__
3. **Environment Variables**: Verify .env is not deployed (security)
4. **Database Connection**: Check production DB credentials

### **Post-Deployment Verification**
```bash
# Test critical endpoints
curl -X POST https://brainity.com.au/ta/api/place_order.php \
  -H "Content-Type: application/json" \
  -d '{"test": true}'

# Check PHP configuration
php -v
php -m | grep pdo
```

---

## Security Debugging

### **API Protection**
- All API endpoints must include `api_protection.php`
- Authentication bypassed only for localhost (development)
- Production requires valid Google OAuth session

### **Environment Security**
- Never log sensitive environment variables
- .env file must never be deployed to server
- Use masked logging for debugging credentials

```php
// Good - masked logging
error_log("API Key configured: " . (getenv('BINGX_API_KEY') ? 'YES' : 'NO'));

// Bad - exposes secrets
error_log("API Key: " . getenv('BINGX_API_KEY'));
```

---

## Quick Reference Commands

### **Environment Check**
```bash
php -r "echo 'Trading Mode: ' . getenv('TRADING_MODE') . \"\n\";"
```

### **Database Test**
```bash
php -r "
require_once 'api/place_order.php';
try { 
    \$pdo = getDbConnection(); 
    echo 'DB: OK\n'; 
} catch (Exception \$e) { 
    echo 'DB: FAIL - ' . \$e->getMessage() . \"\n\"; 
}
"
```

### **API Helper Test**
```bash
php -r "
require_once 'api/api_helper.php';
echo 'Mode: ' . (isDemoMode() ? 'DEMO' : 'LIVE') . \"\n\";
echo 'URL: ' . getBingXApiUrl() . \"\n\";
"
```

---

**Last Updated**: 2025-09-09  
**Status**: Demo trading issues resolved, all systems operational