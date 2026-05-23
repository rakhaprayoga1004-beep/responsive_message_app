<?php
// C:\FlyEnv-Data\app\apache-2.4.67\Apache24\htdocs\responsive-message-app\api\admin\dashboard_stats.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/config.php';
require_once '../../config/database.php';

// Untuk testing, izinkan akses tanpa autentikasi dulu
// Hapus komentar untuk production
/*
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
*/

try {
    $db = Database::getInstance();
    
    // Total Users
    $result = $db->select("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $totalUsers = !empty($result) ? (int)$result[0]['total'] : 0;
    
    // New Users 30 days
    $result = $db->select("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newUsers30Days = !empty($result) ? (int)$result[0]['total'] : 0;
    
    // Total Messages
    $result = $db->select("SELECT COUNT(*) as total FROM messages");
    $totalMessages = !empty($result) ? (int)$result[0]['total'] : 0;
    
    // Pending Messages
    $result = $db->select("SELECT COUNT(*) as total FROM messages WHERE status = 'Pending'");
    $pendingMessages = !empty($result) ? (int)$result[0]['total'] : 0;
    
    // Expired Messages (7 hari terakhir)
    $result = $db->select("SELECT COUNT(*) as total FROM messages WHERE status = 'Expired' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $expiredMessages = !empty($result) ? (int)$result[0]['total'] : 0;
    
    // Message Status Distribution
    $messageStatus = $db->select("SELECT status, COUNT(*) as count FROM messages GROUP BY status");
    
    // Message Type Stats
    $messageTypeStats = $db->select("
        SELECT 
            mt.id,
            mt.jenis_pesan as type,
            COUNT(m.id) as total,
            SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as rejected
        FROM message_types mt
        LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
        GROUP BY mt.id
        ORDER BY total DESC
    ");
    
    // Recent Messages
    $recentMessages = $db->select("
        SELECT 
            m.id,
            COALESCE(m.pengirim_nama, u.nama_lengkap, 'Unknown') as nama_lengkap,
            mt.jenis_pesan,
            m.isi_pesan,
            m.status,
            m.created_at
        FROM messages m
        LEFT JOIN users u ON m.pengirim_id = u.id
        LEFT JOIN message_types mt ON mt.id = m.jenis_pesan_id
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    
    // Response Stats (SEMUA WAKTU agar ada data)
    $responseResult = $db->select("
        SELECT 
            COUNT(*) as total_messages,
            SUM(CASE WHEN tanggal_respon IS NOT NULL OR responder_id IS NOT NULL THEN 1 ELSE 0 END) as responded
        FROM messages
    ");
    
    $responseStatsData = !empty($responseResult) ? $responseResult[0] : ['total_messages' => 0, 'responded' => 0];
    
    // Hitung response rate
    $totalMsg = (int)($responseStatsData['total_messages'] ?? 0);
    $respondedMsg = (int)($responseStatsData['responded'] ?? 0);
    $responseRate = 0;
    if ($totalMsg > 0) {
        $responseRate = round(($respondedMsg / $totalMsg) * 100, 2);
    }
    
    // Kirim response dengan key 'data'
    $responseData = [
        'total_users' => $totalUsers,
        'new_users_30days' => $newUsers30Days,
        'total_messages' => $totalMessages,
        'pending_messages' => $pendingMessages,
        'expired_messages' => $expiredMessages,
        'response_stats' => [
            'total_messages' => $totalMsg,
            'responded' => $respondedMsg,
            'response_rate' => $responseRate
        ],
        'message_status' => $messageStatus ?: [],
        'message_type_stats' => $messageTypeStats ?: [],
        'recent_messages' => $recentMessages ?: [],
    ];
    
    ob_clean();
    echo json_encode(['success' => true, 'data' => $responseData]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>