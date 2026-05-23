<?php
/**
 * API untuk manajemen Audit Logs
 * Mendukung: GET (list), POST (clear logs)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../includes/auth.php';

// Verify token
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userId = verifyToken($token);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

try {
    if ($method === 'GET') {
        // Get audit logs
        $sql = "SELECT a.*, u.nama_lengkap as user_name 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                ORDER BY a.created_at DESC 
                LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$limit]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $logs]);
        
    } elseif ($method === 'POST') {
        // Clear old logs
        $input = json_decode(file_get_contents('php://input'), true);
        $days = (int)($input['days'] ?? 30);
        
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        
        echo json_encode(['success' => true, 'message' => "Cleared $deleted logs", 'deleted' => $deleted]);
    }
    
} catch (Exception $e) {
    error_log("Audit Logs API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}