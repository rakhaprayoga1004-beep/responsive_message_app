<?php
/**
 * create_user.php - Create new user
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
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk membuat user']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit();
}

// Validate required fields
$requiredFields = ['username', 'email', 'user_type', 'nama_lengkap'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Field '$field' wajib diisi"]);
        exit();
    }
}

// Check if username exists
$checkQuery = "SELECT id FROM users WHERE username = :username";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bindParam(':username', $input['username']);
$checkStmt->execute();

if ($checkStmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Username sudah digunakan']);
    exit();
}

// Check if email exists
$checkQuery = "SELECT id FROM users WHERE email = :email";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bindParam(':email', $input['email']);
$checkStmt->execute();

if ($checkStmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Email sudah digunakan']);
    exit();
}

// Default password if not provided
$password = isset($input['password']) && !empty($input['password']) 
    ? $input['password'] 
    : 'password123';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$insertQuery = "
    INSERT INTO users (
        username, password_hash, email, user_type, nis_nip, nama_lengkap,
        kelas, jurusan, mata_pelajaran, privilege_level, phone_number,
        is_active, created_at, updated_at
    ) VALUES (
        :username, :password_hash, :email, :user_type, :nis_nip, :nama_lengkap,
        :kelas, :jurusan, :mata_pelajaran, :privilege_level, :phone_number,
        :is_active, NOW(), NOW()
    )
";

$insertStmt = $db->prepare($insertQuery);
$insertStmt->bindParam(':username', $input['username']);
$insertStmt->bindParam(':password_hash', $passwordHash);
$insertStmt->bindParam(':email', $input['email']);
$insertStmt->bindParam(':user_type', $input['user_type']);
$nisNip = isset($input['nis_nip']) ? $input['nis_nip'] : null;
$insertStmt->bindParam(':nis_nip', $nisNip);
$insertStmt->bindParam(':nama_lengkap', $input['nama_lengkap']);
$kelas = isset($input['kelas']) ? $input['kelas'] : null;
$insertStmt->bindParam(':kelas', $kelas);
$jurusan = isset($input['jurusan']) ? $input['jurusan'] : null;
$insertStmt->bindParam(':jurusan', $jurusan);
$mataPelajaran = isset($input['mata_pelajaran']) ? $input['mata_pelajaran'] : null;
$insertStmt->bindParam(':mata_pelajaran', $mataPelajaran);
$privilegeLevel = isset($input['privilege_level']) ? $input['privilege_level'] : 'Limited_Lv3';
$insertStmt->bindParam(':privilege_level', $privilegeLevel);
$phoneNumber = isset($input['phone_number']) ? $input['phone_number'] : null;
$insertStmt->bindParam(':phone_number', $phoneNumber);
$isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
$insertStmt->bindParam(':is_active', $isActive);

if ($insertStmt->execute()) {
    $newUserId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'User berhasil dibuat',
        'data' => [
            'id' => $newUserId,
            'username' => $input['username'],
            'email' => $input['email'],
            'default_password' => $password
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal membuat user']);
}
?>