<?php
/**
 * File login sederhana untuk testing
 * Lokasi: /responsive_message_app/api/login_test.php
 */

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name('RMSESSID');
    session_start();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input',
        'received' => file_get_contents('php://input')
    ]);
    exit();
}

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$remember = isset($input['remember']) ? (int)$input['remember'] : 0;

// Simple validation
if (empty($username) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Username dan password harus diisi',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    exit();
}

// For testing, accept any username with password 'password'
if ($password === 'password') {
    // Login successful
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = $username;
    $_SESSION['user_type'] = 'Admin';
    $_SESSION['nama_lengkap'] = 'Administrator';
    
    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'csrf_token' => $_SESSION['csrf_token'],
        'session_id' => session_id(),
        'user' => [
            'id' => 1,
            'username' => $username,
            'nama_lengkap' => 'Administrator',
            'email' => 'admin@example.com',
            'user_type' => 'Admin',
            'status' => 'aktif'
        ]
    ]);
} else {
    // Login failed
    echo json_encode([
        'success' => false,
        'message' => 'Username atau password salah',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}
?>