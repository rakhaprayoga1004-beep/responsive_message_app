<?php
// C:\xampp\htdocs\responsive-message-app\api\login_flutter.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

require_once '../config/config.php';
require_once '../config/database.php';

$response = ['success' => false, 'message' => ''];

// Handle OPTIONS request (untuk CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed. Use POST.';
    echo json_encode($response);
    exit;
}

// Baca input JSON dari Flutter
$input = json_decode(file_get_contents('php://input'), true);

// Jika JSON tidak valid, coba ambil dari POST (fallback untuk kompatibilitas)
if (!$input) {
    error_log("No JSON input, trying POST data");
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
} else {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
}

error_log("Login attempt - username: $username");
error_log("Login attempt - method: " . $_SERVER['REQUEST_METHOD']);

if (empty($username) || empty($password)) {
    $response['message'] = 'Username dan password harus diisi';
    echo json_encode($response);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Cari user berdasarkan username
    $result = $db->select(
        "SELECT id, username, password_hash, nama_lengkap, user_type, email, phone_number, avatar, privilege_level, is_active 
         FROM users 
         WHERE username = ? AND is_active = 1 
         LIMIT 1",
        [$username]
    );
    
    error_log("Result count: " . count($result));
    
    if (!empty($result)) {
        $user = $result[0];
        error_log("User found: " . $user['username']);
        
        if (password_verify($password, $user['password_hash'])) {
            error_log("Password verified successfully");
            
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Regenerate session ID untuk keamanan
            session_regenerate_id(true);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_time'] = time();
            $_SESSION['is_logged_in'] = true;
            
            error_log("Session set - user_id: " . $_SESSION['user_id']);
            error_log("Session ID: " . session_id());
            
            // Update last login
            $db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            // Generate token
            $token = base64_encode(json_encode([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'user_type' => $user['user_type'],
                'exp' => time() + (7 * 24 * 60 * 60)
            ]));
            
            $response['success'] = true;
            $response['message'] = 'Login berhasil';
            $response['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'user_type' => $user['user_type'],
                'email' => $user['email'] ?? '',
                'phone_number' => $user['phone_number'] ?? '',
                'avatar' => $user['avatar'] ?? 'default-avatar.png',
                'privilege_level' => $user['privilege_level'] ?? 'Limited_Lv3',
                'token' => $token
            ];
            $response['session_id'] = session_id();
            
            error_log("Login success for user: " . $user['username']);
            
        } else {
            error_log("Password verification failed");
            $response['message'] = 'Password salah';
        }
    } else {
        error_log("User not found: $username");
        $response['message'] = 'Username tidak ditemukan';
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    $response['message'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

echo json_encode($response);
?>