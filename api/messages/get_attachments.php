<?php
/**
 * Get message attachments API
 * File: api/messages/get_attachments.php
 */
 
// ============================================================================
// LOAD CONFIGURATION
// ============================================================================
require_once __DIR__ . '/../../config/config.php'; 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rootPath = realpath(__DIR__ . '/../..');

if ($rootPath === false) {
    echo json_encode(['success' => false, 'message' => 'Root path not found']);
    exit();
}

$configPath = $rootPath . '/config/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Config file not found']);
    exit();
}
require_once $configPath;

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$messageId = (int)($_GET['message_id'] ?? 0);

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT id, filename, filepath, filesize, mime_type, created_at 
        FROM message_attachments 
        WHERE message_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$messageId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'attachments' => $attachments,
        'total' => count($attachments)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>