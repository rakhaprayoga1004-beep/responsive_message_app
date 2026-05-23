<?php
/**
 * API Authentication Endpoint
 * File: api/auth.php
 * Method: POST
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Username dan password diperlukan');
    }
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT id, username, email, user_type, nama_lengkap, nis_nip, 
               privilege_level, kelas, jurusan, phone_number, is_active
        FROM users 
        WHERE (username = :username OR email = :username) AND is_active = 1
    ");
    $stmt->execute([':username' => $input['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Username atau password salah');
    }
    
    // Verify password using Security class
    $verifyStmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $verifyStmt->execute([':id' => $user['id']]);
    $userData = $verifyStmt->fetch();
    
    if (!Security::verifyPassword($input['password'], $userData['password_hash'])) {
        throw new Exception('Username atau password salah');
    }
    
    // Generate API token
    $token = bin2hex(random_bytes(32));
    $updateStmt = $db->prepare("UPDATE users SET api_token = :token WHERE id = :id");
    $updateStmt->execute([':token' => $token, ':id' => $user['id']]);
    
    // Remove sensitive data
    unset($user['password_hash']);
    
    $response['success'] = true;
    $response['message'] = 'Login berhasil';
    $response['data'] = [
        'user' => $user,
        'token' => $token
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(401);
}

echo json_encode($response);