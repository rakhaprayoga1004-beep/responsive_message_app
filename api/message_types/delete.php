<?php
// api/message_types/delete.php
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
    
    // Check if type has messages
    $check = $db->select("SELECT COUNT(*) as total FROM messages WHERE jenis_pesan_id = ?", [$id]);
    $messageCount = (int)($check[0]['total'] ?? 0);
    
    if ($messageCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus jenis pesan yang masih memiliki pesan. Arsipkan atau nonaktifkan saja.']);
        exit;
    }
    
    $result = $db->execute("DELETE FROM message_types WHERE id = ?", [$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus jenis pesan']);
    }
    
} catch (Exception $e) {
    error_log("Error in message_types/delete.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>