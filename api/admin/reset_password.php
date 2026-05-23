<?php
/**
 * reset_password.php - Reset user password
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Ambil token dari header Authorization
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token tidak ditemukan']);
    exit();
}

// Koneksi database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Verifikasi token dan cek admin
$query = "SELECT t.user_id, u.user_type 
          FROM api_tokens t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.token = :token AND t.is_active = 1 AND (t.expires_at IS NULL OR t.expires_at > NOW())";
$stmt = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token tidak valid atau sudah kadaluarsa']);
    exit();
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit();
}

// Generate random password
$newPassword = bin2hex(random_bytes(4)); // 8 character random password
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateQuery = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->bindParam(':password_hash', $passwordHash);
$updateStmt->bindParam(':id', $userId);

if ($updateStmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Password berhasil direset',
        'data' => [
            'new_password' => $newPassword
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mereset password']);
}
?>