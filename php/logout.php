<?php
/**
 * Backend Endpoint: User Logout
 * Invalidates the active session token in Redis
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Enforce POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $headers = getRequestHeaders();
    $authHeader = isset($headers['authorization']) ? $headers['authorization'] : null;

    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if ($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        
        // Remove token from Redis to invalidate session in the backend
        $redis = Database::getRedis();
        $redis->del("session:{$token}");
    }

    // Always respond with success so frontend clears LocalStorage and redirects
    sendJsonResponse(true, 'Successfully logged out.', null, 200);

} catch (Exception $e) {
    // Return success even on cache connection error to allow frontend redirection
    sendJsonResponse(true, 'Logged out from frontend, backend invalidation issue.', null, 200);
}
