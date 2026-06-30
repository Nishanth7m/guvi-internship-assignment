<?php
/**
 * Backend Endpoint: User Authentication
 * Validates credentials in MySQL, issues session token, and stores it in Redis.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Enforce POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', null, 405);
}

// Read raw JSON input
$inputRaw = file_get_contents('php://input');
$inputData = json_decode($inputRaw, true);

if (!$inputData) {
    sendJsonResponse(false, 'Invalid JSON payload received.', null, 400);
}

$email = isset($inputData['email']) ? sanitizeInput($inputData['email']) : '';
$password = isset($inputData['password']) ? $inputData['password'] : '';

// Validation checks
if (empty($email) || empty($password)) {
    sendJsonResponse(false, 'Email address and password are required.', null, 400);
}

if (!validateEmail($email)) {
    sendJsonResponse(false, 'Please provide a valid email format.', null, 400);
}

try {
    $mysql = Database::getMySQL();

    // Query user details with MySQL Prepared Statement
    $stmt = $mysql->prepare("SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify user exists and password hash is valid
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJsonResponse(false, 'Invalid email address or password.', null, 401);
    }

    // Generate cryptographically secure session token
    $sessionToken = generateSessionToken();

    // Store Session in Redis (Key: session:{token} -> Value: userID, TTL: 24 Hours)
    $redis = Database::getRedis();
    $sessionKey = "session:{$sessionToken}";
    $ttlSeconds = 86400; // 24 Hours duration

    $redis->setex($sessionKey, $ttlSeconds, (string)$user['id']);

    // Send response with issued session token for LocalStorage
    sendJsonResponse(true, 'Authentication successful.', [
        'token' => $sessionToken,
        'user'  => [
            'id'       => (int)$user['id'],
            'fullName' => $user['full_name']
        ]
    ], 200);

} catch (PDOException $e) {
    sendJsonResponse(false, 'A database connection error occurred.', null, 500);
} catch (RuntimeException $e) {
    // Catch Redis connection error or custom configuration failure
    sendJsonResponse(false, 'Failed to create active session: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendJsonResponse(false, 'An unexpected system error occurred.', null, 500);
}
