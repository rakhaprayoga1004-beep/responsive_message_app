<?php
// api/message_types/list.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

// Handle preflight
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

if (!$userData || $userData['user_type'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get all message types with statistics
    $sql = "
        SELECT 
            mt.*,
            COUNT(m.id) as message_count,
            SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END) as processed_count,
            SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END) as completed_count,
            AVG(TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon)) as avg_response_time
        FROM message_types mt
        LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
        GROUP BY mt.id
        ORDER BY mt.id ASC
    ";
    
    $result = $db->select($sql);
    
    // Process results
    $types = [];
    foreach ($result as $row) {
        $types[] = [
            'id' => (int)$row['id'],
            'jenis_pesan' => $row['jenis_pesan'],
            'description' => $row['description'],
            'response_deadline_hours' => (int)$row['response_deadline_hours'],
            'is_active' => (int)$row['is_active'],
            'message_count' => (int)($row['message_count'] ?? 0),
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'approved_count' => (int)($row['approved_count'] ?? 0),
            'rejected_count' => (int)($row['rejected_count'] ?? 0),
            'processed_count' => (int)($row['processed_count'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
            'avg_response_time' => round((float)($row['avg_response_time'] ?? 0), 2),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'responder_type' => $row['responder_type'] ?? 'Guru',
            'color_code' => $row['color_code'] ?? '#0d6efd',
            'icon_class' => $row['icon_class'] ?? 'fas fa-envelope',
            'allow_external' => (int)($row['allow_external'] ?? 1)
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $types]);
    
} catch (Exception $e) {
    error_log("Error in message_types/list.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>