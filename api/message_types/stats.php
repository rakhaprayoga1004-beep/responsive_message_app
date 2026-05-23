<?php
// api/message_types/stats.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

try {
    $db = Database::getInstance();
    
    // Total types
    $result = $db->select("SELECT COUNT(*) as total FROM message_types");
    $totalTypes = (int)($result[0]['total'] ?? 0);
    
    // Active types
    $result = $db->select("SELECT COUNT(*) as total FROM message_types WHERE is_active = 1");
    $activeTypes = (int)($result[0]['total'] ?? 0);
    
    // Total messages
    $result = $db->select("SELECT COUNT(*) as total FROM messages");
    $totalMessages = (int)($result[0]['total'] ?? 0);
    
    // Avg response time
    $result = $db->select("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, tanggal_respon)) as avg_time FROM messages WHERE tanggal_respon IS NOT NULL");
    $avgResponseTime = round((float)($result[0]['avg_time'] ?? 0), 2);
    
    // Completion rate
    $result = $db->select("SELECT COUNT(*) as total FROM messages WHERE status = 'Selesai'");
    $completedMessages = (int)($result[0]['total'] ?? 0);
    $completionRate = $totalMessages > 0 ? round(($completedMessages / $totalMessages) * 100, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_types' => $totalTypes,
            'active_types' => $activeTypes,
            'total_messages' => $totalMessages,
            'avg_response_time' => $avgResponseTime,
            'completion_rate' => $completionRate
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in message_types/stats.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>