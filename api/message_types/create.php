<?php
// api/message_types/create.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$jenis_pesan = trim($input['jenis_pesan'] ?? '');
$description = trim($input['description'] ?? '');
$response_deadline_hours = (int)($input['response_deadline_hours'] ?? 72);
$is_active = (int)($input['is_active'] ?? 1);

if (empty($jenis_pesan)) {
    echo json_encode(['success' => false, 'message' => 'Jenis pesan tidak boleh kosong']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if type already exists
    $check = $db->select("SELECT id FROM message_types WHERE jenis_pesan = ?", [$jenis_pesan]);
    if (!empty($check)) {
        echo json_encode(['success' => false, 'message' => 'Jenis pesan sudah ada']);
        exit;
    }
    
    $sql = "INSERT INTO message_types (jenis_pesan, description, response_deadline_hours, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())";
    
    $result = $db->execute($sql, [$jenis_pesan, $description, $response_deadline_hours, $is_active]);
    
    if ($result) {
        $id = $db->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil ditambahkan', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan jenis pesan']);
    }
    
} catch (Exception $e) {
    error_log("Error in message_types/create.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>