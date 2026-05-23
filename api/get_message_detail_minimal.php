<?php
/**
 * Minimal version - tanpa debugging yang bermasalah
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

try {
    $session = SessionManager::getInstance();
    $sessionData = $session->checkSession();
    
    if (!$sessionData) {
        throw new Exception('Unauthorized');
    }
    
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Query sederhana
    $query = "SELECT * FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        $query = "SELECT * FROM external_messages WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        $message['is_external'] = 1;
    } else {
        $message['is_external'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => $message,
            'current_user' => [
                'id' => $sessionData['user_id'],
                'type' => $sessionData['user_type']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>