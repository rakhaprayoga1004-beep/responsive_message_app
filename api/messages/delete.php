<?php
// C:\xampp\htdocs\responsive-message-app\api\messages\delete.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Decode token
$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $userData['user_id'];
$userType = $userData['user_type'];

$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pesan tidak valid']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check permission
    $checkSql = "SELECT pengirim_id FROM messages WHERE id = :id";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':id' => $messageId]);
    $message = $checkStmt->fetch();
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit;
    }
    
    if ($userType !== 'Admin' && $message['pengirim_id'] != $userId) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus pesan ini']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Delete responses
    $db->prepare("DELETE FROM message_responses WHERE message_id = :id")->execute([':id' => $messageId]);
    
    // Delete reviews
    $db->prepare("DELETE FROM wakepsek_reviews WHERE message_id = :id")->execute([':id' => $messageId]);
    
    // Delete attachments
    $db->prepare("DELETE FROM message_attachments WHERE message_id = :id")->execute([':id' => $messageId]);
    
    // Delete message
    $db->prepare("DELETE FROM messages WHERE id = :id")->execute([':id' => $messageId]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pesan berhasil dihapus']);
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>