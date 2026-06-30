<?php
/**
 * Diagnostic Script: Test Redis Connection
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== REDIS DIAGNOSTIC TEST ===\n\n";

$redisUrl = getenv('REDIS_URL');
$host = getenv('REDIS_HOST');
$port = getenv('REDIS_PORT');
$auth = getenv('REDIS_AUTH');

echo "Raw Environment Variables:\n";
echo "REDIS_URL: " . ($redisUrl ? substr($redisUrl, 0, 15) . "..." : "NOT SET") . "\n";
echo "REDIS_HOST: " . ($host ?: "NOT SET") . "\n";
echo "REDIS_PORT: " . ($port ?: "NOT SET") . "\n";
echo "REDIS_AUTH: " . ($auth ? "**** (set)" : "NOT SET") . "\n\n";

// Parse URL if present
if (!empty($redisUrl)) {
    echo "Parsing REDIS_URL...\n";
    $parts = parse_url($redisUrl);
    $parsedHost = isset($parts['host']) ? $parts['host'] : '127.0.0.1';
    $parsedPort = isset($parts['port']) ? (int)$parts['port'] : 6379;
    $parsedAuth = isset($parts['pass']) ? $parts['pass'] : '';
    
    echo "Parsed Host: $parsedHost\n";
    echo "Parsed Port: $parsedPort\n";
    echo "Parsed Auth: " . ($parsedAuth ? "****" : "empty") . "\n\n";
    
    $targetHost = $parsedHost;
    $targetPort = $parsedPort;
    $targetAuth = $parsedAuth;
} else {
    echo "Using individual variables...\n";
    $targetHost = $host ?: '127.0.0.1';
    $targetPort = (int)($port ?: 6379);
    $targetAuth = $auth;
}

echo "Attempting connection to $targetHost:$targetPort...\n";

try {
    if (!class_exists('Redis')) {
        die("Error: PHP Redis extension class 'Redis' is not installed/loaded in this container.\n");
    }

    $redis = new Redis();
    
    // Set a short timeout (3 seconds) for the test
    $connected = $redis->connect($targetHost, $targetPort, 3.0);
    
    if (!$connected) {
        echo "Connection status: FAILED (connect returned false without throwing exception)\n";
    } else {
        echo "Connection status: SUCCESS (socket connected)\n";
        
        if (!empty($targetAuth)) {
            echo "Attempting authentication...\n";
            $authSuccess = $redis->auth($targetAuth);
            if ($authSuccess) {
                echo "Authentication status: SUCCESS\n";
            } else {
                echo "Authentication status: FAILED (invalid password)\n";
            }
        } else {
            echo "Authentication status: SKIPPED (no password provided)\n";
        }
        
        // Try a basic ping
        $ping = $redis->ping();
        echo "Ping response: " . $ping . "\n";
    }
} catch (Exception $e) {
    echo "Exception Caught:\n";
    echo "Class: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
