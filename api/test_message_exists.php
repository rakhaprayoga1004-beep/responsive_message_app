<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $db = Database::getInstance()->getConnection();
    
    // Cek di messages
    $query = "SELECT id, pengirim_nama, isi_pesan FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'found_in_messages' => $message ? true : false,
        'data' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>