<?php
// C:\xampp\htdocs\responsive-message-app\api\message_types\save.php
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

if (!$userData || $userData['user_type'] !== 'Admin') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? 0;
$jenisPesan = trim($input['jenis_pesan'] ?? '');
$description = trim($input['description'] ?? '');
$responseDeadlineHours = (int)($input['response_deadline_hours'] ?? 72);
$isActive = $input['is_active'] == true ? 1 : 0;

if (empty($jenisPesan)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Nama jenis pesan harus diisi']);
    exit;
}

try {
    $db = Database::getInstance();
    
    if ($id > 0) {
        // Update
        $sql = "
            UPDATE message_types 
            SET jenis_pesan = :jenis_pesan,
                description = :description,
                response_deadline_hours = :response_deadline_hours,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ";
        $params = [
            ':jenis_pesan' => $jenisPesan,
            ':description' => $description,
            ':response_deadline_hours' => $responseDeadlineHours,
            ':is_active' => $isActive,
            ':id' => $id
        ];
        $db->execute($sql, $params);
        $message = 'Jenis pesan berhasil diperbarui';
    } else {
        // Insert
        $sql = "
            INSERT INTO message_types (jenis_pesan, description, response_deadline_hours, is_active, created_at, updated_at)
            VALUES (:jenis_pesan, :description, :response_deadline_hours, :is_active, NOW(), NOW())
        ";
        $params = [
            ':jenis_pesan' => $jenisPesan,
            ':description' => $description,
            ':response_deadline_hours' => $responseDeadlineHours,
            ':is_active' => $isActive
        ];
        $db->execute($sql, $params);
        $message = 'Jenis pesan berhasil ditambahkan';
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>