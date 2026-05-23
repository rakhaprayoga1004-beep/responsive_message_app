<?php
// api/message_types/update.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id = (int)($input['id'] ?? 0);
$jenis_pesan = trim($input['jenis_pesan'] ?? '');
$description = trim($input['description'] ?? '');
$response_deadline_hours = (int)($input['response_deadline_hours'] ?? 72);
$is_active = (int)($input['is_active'] ?? 1);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

if (empty($jenis_pesan)) {
    echo json_encode(['success' => false, 'message' => 'Jenis pesan tidak boleh kosong']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if type exists
    $check = $db->select("SELECT id FROM message_types WHERE id = ?", [$id]);
    if (empty($check)) {
        echo json_encode(['success' => false, 'message' => 'Jenis pesan tidak ditemukan']);
        exit;
    }
    
    // Check duplicate name (excluding current)
    $check = $db->select("SELECT id FROM message_types WHERE jenis_pesan = ? AND id != ?", [$jenis_pesan, $id]);
    if (!empty($check)) {
        echo json_encode(['success' => false, 'message' => 'Jenis pesan sudah ada']);
        exit;
    }
    
    $sql = "UPDATE message_types 
            SET jenis_pesan = ?, description = ?, response_deadline_hours = ?, is_active = ?, updated_at = NOW() 
            WHERE id = ?";
    
    $result = $db->execute($sql, [$jenis_pesan, $description, $response_deadline_hours, $is_active, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jenis pesan']);
    }
    
} catch (Exception $e) {
    error_log("Error in message_types/update.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>