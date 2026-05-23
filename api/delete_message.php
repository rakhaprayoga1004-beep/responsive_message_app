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
    
    if (!isset($input['message_id'])) {
        throw new Exception('Missing message ID');
    }
    
    $messageId = intval($input['message_id']);
    $userId = $sessionData['user_id'];
    $userType = $sessionData['user_type'];
    
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user has permission to delete
    $checkQuery = "SELECT pengirim_id FROM messages WHERE id = :message_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':message_id', $messageId);
    $checkStmt->execute();
    
    $message = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        // Check external messages
        $checkExtQuery = "SELECT pengirim_id FROM external_messages WHERE id = :message_id";
        $checkExtStmt = $db->prepare($checkExtQuery);
        $checkExtStmt->bindParam(':message_id', $messageId);
        $checkExtStmt->execute();
        $extMessage = $checkExtStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$extMessage) {
            throw new Exception('Message not found');
        }
        
        // Only admin can delete external messages
        if ($userType !== 'Admin') {
            throw new Exception('Permission denied');
        }
        
        // Delete external message
        $deleteExtQuery = "DELETE FROM external_messages WHERE id = :message_id";
        $deleteExtStmt = $db->prepare($deleteExtQuery);
        $deleteExtStmt->bindParam(':message_id', $messageId);
        $deleteExtStmt->execute();
        
    } else {
        // Check permission for internal messages
        if ($message['pengirim_id'] != $userId && $userType !== 'Admin') {
            throw new Exception('Permission denied');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Delete attachments first
            $deleteAttQuery = "DELETE FROM attachments WHERE message_id = :message_id";
            $deleteAttStmt = $db->prepare($deleteAttQuery);
            $deleteAttStmt->bindParam(':message_id', $messageId);
            $deleteAttStmt->execute();
            
            // Delete responses
            $deleteRespQuery = "DELETE FROM message_responses WHERE message_id = :message_id";
            $deleteRespStmt = $db->prepare($deleteRespQuery);
            $deleteRespStmt->bindParam(':message_id', $messageId);
            $deleteRespStmt->execute();
            
            // Delete message
            $deleteMsgQuery = "DELETE FROM messages WHERE id = :message_id";
            $deleteMsgStmt = $db->prepare($deleteMsgQuery);
            $deleteMsgStmt->bindParam(':message_id', $messageId);
            $deleteMsgStmt->execute();
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>