<?php
/**
 * Test Message API - Untuk mengecek apakah pesan ada di database
 * File: responsive-message-app/api/test_message.php
 */

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load konfigurasi database
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    error_log("=== TEST MESSAGE API CALLED ===");
    error_log("Testing message ID: " . $messageId);
    
    $db = Database::getInstance()->getConnection();
    
    // Cek di tabel messages
    $query = "SELECT id, pengirim_nama, isi_pesan, status, created_at FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        error_log("Message not found in internal messages, checking external...");
        
        // Cek di external_messages
        $query = "SELECT id, pengirim_nama, isi_pesan, status, created_at FROM external_messages WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message) {
            $message['is_external'] = true;
        }
    } else {
        $message['is_external'] = false;
    }
    
    if ($message) {
        error_log("Message found: ID " . $messageId);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'source' => $message['is_external'] ? 'external' : 'internal'
        ]);
    } else {
        error_log("Message not found for ID: " . $messageId);
        echo json_encode([
            'success' => false,
            'error' => 'Message not found',
            'message_id' => $messageId
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in test_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>