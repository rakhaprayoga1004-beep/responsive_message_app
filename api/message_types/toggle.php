<?php
// api/message_types/toggle.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

if (!$userData || $userData['user_type'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $current = $db->select("SELECT is_active FROM message_types WHERE id = ?", [$id]);
    if (empty($current)) {
        echo json_encode(['success' => false, 'message' => 'Jenis pesan tidak ditemukan']);
        exit;
    }
    
    $newStatus = $current[0]['is_active'] == 1 ? 0 : 1;
    $result = $db->execute("UPDATE message_types SET is_active = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status berhasil diubah', 'is_active' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengubah status']);
    }
    
} catch (Exception $e) {
    error_log("Error in message_types/toggle.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>