<?php
/**
 * API untuk bulk action users (activate/deactivate/delete)
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
    
    $action = $input['action'] ?? '';
    $userIds = $input['user_ids'] ?? [];
    
    if (empty($action) || empty($userIds)) {
        throw new Exception('Parameter tidak lengkap');
    }
    
    $db = Database::getInstance();
    
    // Buat placeholder untuk IN clause
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    
    switch ($action) {
        case 'activate':
            $sql = "UPDATE users SET is_active = 1, updated_at = NOW() WHERE id IN ($placeholders)";
            $message = 'diaktifkan';
            break;
            
        case 'deactivate':
            $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id IN ($placeholders)";
            $message = 'dinonaktifkan';
            break;
            
        case 'delete':
            $sql = "DELETE FROM users WHERE id IN ($placeholders)";
            $message = 'dihapus';
            break;
            
        default:
            throw new Exception('Action tidak valid');
    }
    
    $result = $db->execute($sql, $userIds);
    
    if ($result) {
        $affected = $db->getConnection()->rowCount();
        echo json_encode([
            'success' => true,
            'message' => "$affected user berhasil $message",
            'affected' => $affected
        ]);
    } else {
        throw new Exception("Gagal melakukan action: $action");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>