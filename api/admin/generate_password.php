<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Decode token
$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData || $userData['user_type'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $password_asli = trim($input['password_asli'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username harus diisi']);
        exit;
    }
    
    if (empty($password_asli)) {
        echo json_encode(['success' => false, 'message' => 'Password harus diisi']);
        exit;
    }
    
    $password_hash = password_hash($password_asli, PASSWORD_DEFAULT);
    
    echo json_encode([
        'success' => true,
        'username' => $username,
        'password_asli' => $password_asli,
        'password_hash' => $password_hash
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>