<?php
/**
 * API Endpoint tanpa autentikasi (untuk testing)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Query untuk internal messages
    $query = "SELECT * FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        $query = "SELECT * FROM external_messages WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$message) {
        throw new Exception('Message not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'note' => 'This is without authentication'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>