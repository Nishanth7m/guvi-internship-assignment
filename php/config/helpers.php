<?php
/**
 * Global API Helpers, Validator, and Session Utilities
 */

require_once __DIR__ . '/db.php';

/**
 * Send JSON API Response and terminate script execution.
 */
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    // Clear any output buffer to ensure we only return JSON
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Retrieve all request headers in a server-agnostic manner.
 */
function getRequestHeaders() {
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if ($headers !== false) {
            return array_change_key_case($headers, CASE_LOWER);
        }
    }
    
    $headers = [];
    foreach ($_SERVER as $key => $val) {
        if (strpos($key, 'HTTP_') === 0) {
            $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
            $headers[$headerName] = $val;
        }
    }
    return $headers;
}

/**
 * Standard sanitization for raw string input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate standard email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password requirements (minimum 8 chars, at least one letter, and one digit)
 */
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return false;
    }
    // Regex checks for at least one letter and one number
    return preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

/**
 * Validate Phone numbers (digits and common punctuation, length 10-15)
 */
function validatePhone($phone) {
    // Remove common phone symbols
    $clean = preg_replace('/[+\-\s()]/', '', $phone);
    return ctype_digit($clean) && strlen($clean) >= 10 && strlen($clean) <= 15;
}

/**
 * Validate Date of Birth (must be valid calendar date and format YYYY-MM-DD)
 */
function validateDateOfBirth($dob) {
    $format = 'Y-m-d';
    $d = DateTime::createFromFormat($format, $dob);
    if (!$d || $d->format($format) !== $dob) {
        return false;
    }
    
    // Ensure DOB is not in the future and user is within a realistic age range (e.g. max 120 years)
    $today = new DateTime();
    $minDate = (new DateTime())->modify('-120 years');
    return $d <= $today && $d >= $minDate;
}

/**
 * Generate cryptographically secure session token
 */
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Extract Authorization Token and return verified user ID from Redis.
 * Returns null if token is invalid or missing.
 */
function getAuthorizedUserId() {
    $headers = getRequestHeaders();
    $authHeader = isset($headers['authorization']) ? $headers['authorization'] : null;

    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!$authHeader) {
        return null;
    }

    // Matches Bearer <token>
    if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        try {
            $redis = Database::getRedis();
            $userId = $redis->get("session:{$token}");
            return $userId ? (int)$userId : null;
        } catch (Exception $e) {
            // Redis error or service unavailable
            return null;
        }
    }
    return null;
}
