<?php

/**
 * Environment Variables Loader
 * Load configuration from .env file
 */

class EnvLoader {
    private static $loaded = false;
    private static $config = [];

    public static function load($envFile = '.env') {
        if (self::$loaded) {
            return;
        }

        $envPath = __DIR__ . '/' . $envFile;
        
        if (!file_exists($envPath)) {
            throw new Exception("Environment file not found: {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Store in our config array and set as environment variable
                self::$config[$key] = $value;
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        
        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        self::load();
        return self::$config[$key] ?? $_ENV[$key] ?? getenv($key) ?? $default;
    }

    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }

    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }

    public static function getFloat($key, $default = 0.0) {
        return (float) self::get($key, $default);
    }

    public static function getAll() {
        self::load();
        return self::$config;
    }
}

// Auto-load environment variables when this file is included
EnvLoader::load();