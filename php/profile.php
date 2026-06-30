<?php
/**
 * Backend Endpoint: User Profile Management
 * Supports retrieval (GET) and update (POST) of authenticated user details
 * Authenticates tokens against Redis, updates MySQL (PDO), and reads/writes MongoDB (Native)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Verify authentication token against Redis
$userId = getAuthorizedUserId();
if (!$userId) {
    sendJsonResponse(false, 'Unauthorized. Please login again.', null, 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // ----------------------------------------------------
    // GET Profile Details
    // ----------------------------------------------------
    try {
        $mysql = Database::getMySQL();
        
        // Fetch core credentials from MySQL
        $stmt = $mysql->prepare("SELECT full_name, email, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();

        if (!$userRow) {
            sendJsonResponse(false, 'User account not found.', null, 404);
        }

        // Fetch custom profile specific details from MongoDB
        $mongo = Database::getMongo();
        $filter = ['mysql_user_id' => $userId];
        $query = new MongoDB\Driver\Query($filter, ['limit' => 1]);
        $cursor = $mongo->executeQuery('guvi_internship.profiles', $query);
        $profilesList = $cursor->toArray();

        $profileDetails = [];
        if (count($profilesList) > 0) {
            $rawDoc = (array)$profilesList[0];
            // Clean BSON Objects to ensure JSON serialization succeeds
            unset($rawDoc['_id']);
            
            $profileDetails = [
                'age'     => isset($rawDoc['age']) ? (int)$rawDoc['age'] : null,
                'dob'     => isset($rawDoc['dob']) ? $rawDoc['dob'] : null,
                'phone'   => isset($rawDoc['phone']) ? $rawDoc['phone'] : '',
                'address' => isset($rawDoc['address']) ? $rawDoc['address'] : '',
                'bio'     => isset($rawDoc['bio']) ? $rawDoc['bio'] : '',
                'skills'  => isset($rawDoc['skills']) ? (array)$rawDoc['skills'] : []
            ];
        } else {
            // Default response if Mongo document hasn't been instantiated yet
            $profileDetails = [
                'age'     => null,
                'dob'     => null,
                'phone'   => '',
                'address' => '',
                'bio'     => '',
                'skills'  => []
            ];
        }

        sendJsonResponse(true, 'Profile details retrieved.', [
            'user' => [
                'fullName'  => $userRow['full_name'],
                'email'     => $userRow['email'],
                'createdAt' => $userRow['created_at']
            ],
            'profile' => $profileDetails
        ], 200);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'Database retrieval error occurred.', null, 500);
    } catch (Exception $e) {
        sendJsonResponse(false, 'Failed to retrieve profile: ' . $e->getMessage(), null, 500);
    }

} elseif ($method === 'POST') {
    // ----------------------------------------------------
    // POST / UPDATE Profile Details
    // ----------------------------------------------------
    $inputRaw = file_get_contents('php://input');
    $inputData = json_decode($inputRaw, true);

    if (!$inputData) {
        sendJsonResponse(false, 'Invalid update format.', null, 400);
    }

    // Sanitize and read updates
    $fullName = isset($inputData['fullName']) ? sanitizeInput($inputData['fullName']) : '';
    $age = isset($inputData['age']) && $inputData['age'] !== '' ? (int)$inputData['age'] : null;
    $dob = isset($inputData['dob']) ? sanitizeInput($inputData['dob']) : null;
    $phone = isset($inputData['phone']) ? sanitizeInput($inputData['phone']) : '';
    $address = isset($inputData['address']) ? sanitizeInput($inputData['address']) : '';
    $bio = isset($inputData['bio']) ? sanitizeInput($inputData['bio']) : '';
    $skills = isset($inputData['skills']) ? (array)$inputData['skills'] : [];

    // Server-side validation
    if (empty($fullName)) {
        sendJsonResponse(false, 'Full name cannot be blank.', null, 400);
    }
    if (strlen($fullName) < 3) {
        sendJsonResponse(false, 'Full name must be at least 3 characters.', null, 400);
    }
    if ($age !== null && ($age < 1 || $age > 120)) {
        sendJsonResponse(false, 'Please provide an age between 1 and 120.', null, 400);
    }
    if (!empty($dob) && !validateDateOfBirth($dob)) {
        sendJsonResponse(false, 'Please provide a valid Date of Birth.', null, 400);
    }
    if (!empty($phone) && !validatePhone($phone)) {
        sendJsonResponse(false, 'Please check your phone number formatting.', null, 400);
    }

    // Sanitize elements inside skills array
    $sanitizedSkills = array_map(function($val) {
        return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
    }, $skills);

    try {
        $mysql = Database::getMySQL();
        
        // Begin Transaction
        $mysql->beginTransaction();

        // Update MySQL Full Name
        $stmt = $mysql->prepare("UPDATE users SET full_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$fullName, $userId]);

        // Update MongoDB profile details
        $mongo = Database::getMongo();
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(
            ['mysql_user_id' => $userId],
            ['$set' => [
                'age'        => $age,
                'dob'        => $dob,
                'phone'      => $phone,
                'address'    => $address,
                'bio'        => $bio,
                'skills'     => $sanitizedSkills,
                'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000)
            ]],
            ['multi' => false, 'upsert' => true]
        );

        $mongo->executeBulkWrite('guvi_internship.profiles', $bulk);

        // Commit transaction
        $mysql->commit();

        sendJsonResponse(true, 'Profile successfully synchronized.', null, 200);

    } catch (PDOException $e) {
        if (isset($mysql) && $mysql->inTransaction()) {
            $mysql->rollBack();
        }
        sendJsonResponse(false, 'MySQL transaction update failure.', null, 500);
    } catch (Exception $e) {
        if (isset($mysql) && $mysql->inTransaction()) {
            $mysql->rollBack();
        }
        sendJsonResponse(false, 'System update failed: ' . $e->getMessage(), null, 500);
    }
} else {
    sendJsonResponse(false, 'Method Not Allowed', null, 405);
}
