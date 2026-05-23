<?php
/**
 * delete_user.php - Delete user (soft delete or hard delete)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
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

// Jangan hapus admin sendiri
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
if ($tokenData['user_id'] == $userId) {
    echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri']);
    exit();
}

// Soft delete (set is_active = 0) - lebih aman
$deleteQuery = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':id', $userId);

if ($deleteStmt->execute()) {
    // Also deactivate user's tokens
    $tokenQuery = "UPDATE api_tokens SET is_active = 0 WHERE user_id = :user_id";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindParam(':user_id', $userId);
    $tokenStmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'User berhasil dinonaktifkan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus user']);
}
?>