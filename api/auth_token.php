<?php
/**
 * API Token Authentication System
 * Provides secure token-based authentication for browser API access
 */

// Database configuration will be loaded through loadEnv function

class TokenAuth {
    private $db;
    private static $tokenSecret;
    
    public function __construct($database) {
        $this->db = $database;
        self::$tokenSecret = getenv('API_TOKEN_SECRET') ?: 'crypto_trade_api_secret_2024';
    }
    
    /**
     * Generate a secure API token
     */
    public static function generateToken($orderId = null, $action = null, $expiresIn = 3600) {
        $payload = [
            'order_id' => $orderId,
            'action' => $action,
            'expires' => time() + $expiresIn,
            'random' => bin2hex(random_bytes(16))
        ];
        
        $token = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $token, self::$tokenSecret);
        
        return $token . '.' . $signature;
    }
    
    /**
     * Validate API token
     */
    public static function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($tokenData, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $tokenData, self::$tokenSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(base64_decode($tokenData), true);
        if (!$payload) {
            return false;
        }
        
        // Check expiration
        if (time() > $payload['expires']) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Authenticate API request
     */
    public function authenticate() {
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing authentication token']);
            exit;
        }
        
        $payload = self::validateToken($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }
        
        return $payload;
    }
}

/**
 * Quick authentication helper for API endpoints
 */
function authenticateApiRequest() {
    try {
        // Load environment variables
        function loadEnv($path) {
            if (!file_exists($path)) {
                return false;
            }
            
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
        
        $envPath = __DIR__ . '/../.env';
        loadEnv($envPath);
        
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';
        
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $auth = new TokenAuth($pdo);
        return $auth->authenticate();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Authentication service unavailable']);
        exit;
    }
}
?>