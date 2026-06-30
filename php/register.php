<?php
/**
 * Backend Endpoint: User Registration
 * Handles secure account creation with MySQL (PDO) & MongoDB (Native Driver)
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

// Sanitize inputs
$fullName = isset($inputData['fullName']) ? sanitizeInput($inputData['fullName']) : '';
$email = isset($inputData['email']) ? sanitizeInput($inputData['email']) : '';
$password = isset($inputData['password']) ? $inputData['password'] : '';
$confirmPassword = isset($inputData['confirmPassword']) ? $inputData['confirmPassword'] : '';

// Validation checks
if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
    sendJsonResponse(false, 'All input fields are required.', null, 400);
}

if (strlen($fullName) < 3) {
    sendJsonResponse(false, 'Full name must be at least 3 characters.', null, 400);
}

if (!validateEmail($email)) {
    sendJsonResponse(false, 'Please provide a valid email address.', null, 400);
}

if (!validatePasswordStrength($password)) {
    sendJsonResponse(false, 'Password does not meet complexity requirements.', null, 400);
}

if ($password !== $confirmPassword) {
    sendJsonResponse(false, 'Passwords do not match.', null, 400);
}

try {
    $mysql = Database::getMySQL();

    // Prevent duplicate registrations using Prepared Statements
    $stmtCheck = $mysql->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        sendJsonResponse(false, 'Email address is already registered.', null, 409);
    }

    // Hash password securely
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Begin Transaction to maintain atomicity across MySQL & MongoDB
    $mysql->beginTransaction();

    // Insert user into MySQL
    $stmtInsert = $mysql->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
    $stmtInsert->execute([$fullName, $email, $passwordHash]);
    $newUserId = (int)$mysql->lastInsertId();

    // Insert associated empty profile document into MongoDB
    $mongo = Database::getMongo();
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->insert([
        'mysql_user_id' => $newUserId,
        'age'           => null,
        'dob'           => null,
        'phone'         => '',
        'address'       => '',
        'bio'           => '',
        'skills'        => [],
        'updated_at'    => new MongoDB\BSON\UTCDateTime(time() * 1000)
    ]);

    // Execute bulk write to the 'profiles' collection
    // MongoDB databases and collections are lazily created upon first insertion.
    $mongo->executeBulkWrite('guvi_internship.profiles', $bulk);

    // Commit transaction on success
    $mysql->commit();

    sendJsonResponse(true, 'Account successfully created.', ['userId' => $newUserId], 201);

} catch (PDOException $e) {
    if (isset($mysql) && $mysql->inTransaction()) {
        $mysql->rollBack();
    }
    // Log exception locally, hide raw error from client
    sendJsonResponse(false, 'A database error occurred during registration.', null, 500);
} catch (Exception $e) {
    if (isset($mysql) && $mysql->inTransaction()) {
        $mysql->rollBack();
    }
    sendJsonResponse(false, 'An internal error occurred: ' . $e->getMessage(), null, 500);
}
