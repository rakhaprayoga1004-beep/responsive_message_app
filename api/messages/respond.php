<?php
// C:\xampp\htdocs\responsive-message-app\api\messages\respond.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/config.php';
require_once '../../config/database.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $userData['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $messageId = $input['message_id'] ?? 0;
    $catatanRespon = trim($input['catatan_respon'] ?? '');
    $status = $input['status'] ?? '';
    
    $errors = [];
    
    if ($messageId <= 0) {
        $errors[] = 'ID pesan tidak valid';
    }
    
    if (empty($catatanRespon)) {
        $errors[] = 'Catatan respon harus diisi';
    }
    
    if (empty($status)) {
        $errors[] = 'Status harus dipilih';
    }
    
    if (!empty($errors)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        $db->beginTransaction();
        
        // Insert response
        $sql = "
            INSERT INTO message_responses (
                message_id, responder_id, catatan_respon, status, created_at
            ) VALUES (
                :message_id, :responder_id, :catatan_respon, :status, NOW()
            )
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':message_id' => $messageId,
            ':responder_id' => $userId,
            ':catatan_respon' => htmlspecialchars($catatanRespon),
            ':status' => $status
        ]);
        
        // Update message status
        $updateSql = "
            UPDATE messages 
            SET status = :status, 
                responder_id = :responder_id,
                tanggal_respon = NOW(),
                updated_at = NOW()
            WHERE id = :message_id
        ";
        
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute([
            ':status' => $status,
            ':responder_id' => $userId,
            ':message_id' => $messageId
        ]);
        
        $db->commit();
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Respon berhasil dikirim']);
        
    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>