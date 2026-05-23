<?php
/**
 * Simple Login API untuk testing
 * File: responsive-message-app/api/simple_login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

try {
    // Ambil data dari POST (form data atau JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? $_POST['username'] ?? '';
    $password = $input['password'] ?? $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password required');
    }
    
    error_log("Simple login attempt: $username");
    
    $db = Database::getInstance()->getConnection();
    
    // Cari user (gunakan data dummy untuk testing)
    // Dalam implementasi nyata, query ke database
    $query = "SELECT id, username, password_hash, user_type, nama_lengkap 
              FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Untuk testing, jika user tidak ditemukan, buat session dummy
        error_log("User not found, using dummy session for testing");
        
        $session = SessionManager::getInstance();
        $session->setSession(1, 'Admin', 'Test User');
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful (dummy session)',
            'session_id' => session_id(),
            'user_id' => 1,
            'user_type' => 'Admin',
            'user_name' => 'Test User'
        ]);
        exit;
    }
    
    // Verifikasi password (sesuaikan dengan hash method yang digunakan)
    if (password_verify($password, $user['password_hash'])) {
        $session = SessionManager::getInstance();
        $session->setSession(
            $user['id'],
            $user['user_type'] ?? 'User',
            $user['nama_lengkap'] ?? $user['username']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'session_id' => session_id(),
            'user_id' => $user['id'],
            'user_type' => $user['user_type'] ?? 'User',
            'user_name' => $user['nama_lengkap'] ?? $user['username']
        ]);
    } else {
        throw new Exception('Invalid password');
    }
    
} catch (Exception $e) {
    error_log("Simple login error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>