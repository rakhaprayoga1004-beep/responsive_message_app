<?php
/**
 * API untuk membuat user baru
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input');
    }
    
    $db = Database::getInstance();
    
    // Validasi data
    $nama_lengkap = $input['nama_lengkap'] ?? '';
    $email = $input['email'] ?? '';
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $user_type = $input['user_type'] ?? 'Guru';
    $nis_nip = $input['nis_nip'] ?? null;
    $no_telp = $input['no_telp'] ?? null;
    
    if (empty($nama_lengkap) || empty($email) || empty($username) || empty($password)) {
        throw new Exception('Data tidak lengkap');
    }
    
    // Cek username sudah ada
    $check = $db->select("SELECT id FROM users WHERE username = ?", [$username]);
    if (!empty($check)) {
        throw new Exception('Username sudah digunakan');
    }
    
    // Cek email sudah ada
    $check = $db->select("SELECT id FROM users WHERE email = ?", [$email]);
    if (!empty($check)) {
        throw new Exception('Email sudah digunakan');
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (
                username, password_hash, email, user_type, nama_lengkap,
                nis_nip, phone_number, is_active, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, 1, NOW(), NOW()
            )";
    
    $result = $db->execute($sql, [
        $username, $password_hash, $email, $user_type, $nama_lengkap,
        $nis_nip, $no_telp
    ]);
    
    if ($result) {
        $newId = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => ['id' => $newId]
        ]);
    } else {
        throw new Exception('Gagal membuat user');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>