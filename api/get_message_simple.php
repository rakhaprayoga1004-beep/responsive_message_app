<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    // Ambil ID dari parameter
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Query sederhana tanpa JOIN yang rumit
    $query = "SELECT * FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        // Coba di external_messages
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
        'data' => [
            'message' => $message,
            'attachments' => [],
            'responses' => []
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>