<?php
/**
 * API untuk reset password user
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
    
    $id = $input['id'] ?? 0;
    
    if ($id <= 0) {
        throw new Exception('ID tidak valid');
    }
    
    $db = Database::getInstance();
    
    // Generate password random
    $newPassword = bin2hex(random_bytes(4)); // 8 karakter
    $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
    $result = $db->execute($sql, [$password_hash, $id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Password berhasil direset',
            'new_password' => $newPassword
        ]);
    } else {
        throw new Exception('Gagal mereset password');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>