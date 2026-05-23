<?php
/**
 * API untuk menghapus user
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID tidak valid');
    }
    
    $db = Database::getInstance();
    
    // Cek user exists
    $check = $db->select("SELECT id FROM users WHERE id = ?", [$id]);
    if (empty($check)) {
        throw new Exception('User tidak ditemukan');
    }
    
    // Delete user
    $result = $db->execute("DELETE FROM users WHERE id = ?", [$id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    } else {
        throw new Exception('Gagal menghapus user');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>