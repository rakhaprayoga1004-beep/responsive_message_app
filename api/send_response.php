<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config/database.php';
require_once '../utils/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Check session
    $session = SessionManager::getInstance();
    $sessionData = $session->checkSession();
    
    if (!$sessionData) {
        throw new Exception('Unauthorized');
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message_id']) || !isset($input['response'])) {
        throw new Exception('Missing required fields');
    }
    
    $messageId = intval($input['message_id']);
    $response = trim($input['response']);
    $responderId = $sessionData['user_id'];
    
    if ($messageId <= 0 || empty($response)) {
        throw new Exception('Invalid input');
    }
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Insert response
    $query = "INSERT INTO message_responses 
              (message_id, responder_id, catatan_respon, status, created_at) 
              VALUES 
              (:message_id, :responder_id, :response, 'Dibalas', NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':message_id', $messageId);
    $stmt->bindParam(':responder_id', $responderId);
    $stmt->bindParam(':response', $response);
    
    if ($stmt->execute()) {
        // Update message status
        $updateQuery = "UPDATE messages SET 
                       status = 'Dibalas', 
                       tanggal_respon = NOW(),
                       responder_id = :responder_id
                       WHERE id = :message_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':message_id', $messageId);
        $updateStmt->bindParam(':responder_id', $responderId);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Response sent successfully'
        ]);
    } else {
        throw new Exception('Failed to save response');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>