<?php
/**
 * API Endpoint untuk mengecek validitas session
 * File: responsive-message-app/api/check_session.php
 */

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log untuk debugging
error_log("=== CHECK SESSION API CALLED ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Cookie: " . print_r($_COOKIE, true));

// Cek apakah session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Session ID after start: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

try {
    // Cek apakah user sudah login
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        error_log("Session VALID for user: " . $_SESSION['user_id']);
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'data' => [
                'user_id' => $_SESSION['user_id'],
                'user_type' => $_SESSION['user_type'] ?? 'User',
                'user_name' => $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Unknown',
                'nama_lengkap' => $_SESSION['nama_lengkap'] ?? ''
            ]
        ]);
    } else {
        error_log("Session TIDAK VALID - No user_id in session");
        
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'No valid session'
        ]);
    }
} catch (Exception $e) {
    error_log("Exception in check_session: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>