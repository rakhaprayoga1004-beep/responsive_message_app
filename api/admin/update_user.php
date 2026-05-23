<?php
/**
 * update_user.php - Update user information
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

$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
if ($tokenData['user_type'] !== 'Admin' && $tokenData['user_type'] !== 'Kepala_Sekolah') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengupdate user']);
    exit();
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit();
}

// Build update query
$updateFields = [];
$params = [':id' => $userId];

$allowedFields = [
    'username', 'email', 'user_type', 'nis_nip', 'nama_lengkap',
    'kelas', 'jurusan', 'mata_pelajaran', 'privilege_level', 'phone_number'
];

foreach ($allowedFields as $field) {
    if (isset($input[$field])) {
        $updateFields[] = "$field = :$field";
        $params[":$field"] = $input[$field];
    }
}

// Update password if provided
if (isset($input['password']) && !empty($input['password'])) {
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    $updateFields[] = "password_hash = :password_hash";
    $params[':password_hash'] = $passwordHash;
}

if (empty($updateFields)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada data yang diupdate']);
    exit();
}

$updateFields[] = "updated_at = NOW()";
$updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = :id";

$updateStmt = $db->prepare($updateQuery);
foreach ($params as $key => $value) {
    $updateStmt->bindValue($key, $value);
}

if ($updateStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate user']);
}
?>