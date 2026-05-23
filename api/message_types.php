<?php
/**
 * API untuk mendapatkan daftar jenis pesan dengan jumlah pesan dan statistik lengkap
 * File: api/message_types.php
 */

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/api_error.log');

// Hapus output buffer
while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// ==================== TAMBAHAN: DUKUNGAN TOKEN UNTUK FLUTTER ====================
// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Decode token
$userDataFromToken = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userDataFromToken = $payload;
    }
}
// ==================== AKHIR TAMBAHAN ====================

// Start session dengan nama yang konsisten
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    } else {
        session_name('PHPSESSID');
    }
    session_start();
}

// Debug logging
error_log("=== Message Types API Request ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Data: " . print_r($_SESSION, true));

// ==================== MODIFIKASI: CEK AUTH DENGAN TOKEN ATAU SESSION ====================
$isAuthenticated = false;

// Cek dari session (untuk web)
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $isAuthenticated = true;
    error_log("User authenticated via session - user_id: {$_SESSION['user_id']}");
}

// Cek dari token (untuk Flutter)
if (!$isAuthenticated && $userDataFromToken !== null) {
    $isAuthenticated = true;
    error_log("User authenticated via token - user_id: {$userDataFromToken['user_id']}, user_type: {$userDataFromToken['user_type']}");
}

if (!$isAuthenticated) {
    error_log("User not authenticated - no valid session or token");
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Unauthorized', 
        'message' => 'Please login first',
        'session_id' => session_id(),
        'session_name' => session_name()
    ]);
    exit();
}
// ==================== AKHIR MODIFIKASI ====================

$db = Database::getInstance()->getConnection();

try {
    // Query lengkap dengan semua statistik
    $sql = "SELECT 
                mt.id,
                mt.jenis_pesan,
                mt.responder_type,
                mt.description,
                mt.response_deadline_hours,
                mt.color_code,
                mt.icon_class,
                mt.is_active,
                mt.allow_external,
                mt.created_at,
                mt.updated_at,
                COUNT(m.id) as message_count,
                SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END) as processed_count,
                SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END) as completed_count,
                AVG(CASE 
                    WHEN m.status != 'Pending' AND m.status != 'Ditolak' AND m.status != 'Pending' 
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, COALESCE(m.tanggal_respon, NOW())) 
                    ELSE NULL 
                END) as avg_response_time
            FROM message_types mt
            LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
            GROUP BY mt.id
            ORDER BY mt.is_active DESC, mt.id ASC";
    
    $stmt = $db->query($sql);
    $messageTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Query executed successfully, found " . count($messageTypes) . " message types");
    
    // Format data untuk Flutter
    $formattedTypes = [];
    foreach ($messageTypes as $type) {
        $formattedTypes[] = [
            'id' => (int)$type['id'],
            'jenis_pesan' => $type['jenis_pesan'],
            'responder_type' => $type['responder_type'],
            'description' => $type['description'],
            'response_deadline_hours' => (int)($type['response_deadline_hours'] ?? 72),
            'color_code' => $type['color_code'] ?? '#0B4D8A',
            'icon_class' => $type['icon_class'] ?? 'message',
            'is_active' => (bool)$type['is_active'],
            'allow_external' => (bool)($type['allow_external'] ?? false),
            'message_count' => (int)($type['message_count'] ?? 0),
            'pending_count' => (int)($type['pending_count'] ?? 0),
            'approved_count' => (int)($type['approved_count'] ?? 0),
            'rejected_count' => (int)($type['rejected_count'] ?? 0),
            'processed_count' => (int)($type['processed_count'] ?? 0),
            'completed_count' => (int)($type['completed_count'] ?? 0),
            'avg_response_time' => (float)($type['avg_response_time'] ?? 0),
            'created_at' => $type['created_at'],
            'updated_at' => $type['updated_at']
        ];
    }
    
    error_log("Message Types API: Found " . count($formattedTypes) . " types with full statistics");
    
    ob_clean();
    echo json_encode([
        'success' => true, 
        'types' => $formattedTypes,  // Kunci 'types' untuk kompatibilitas dengan Flutter
        'data' => $formattedTypes,   // Kunci 'data' untuk kompatibilitas dengan kode lama
        'total' => count($formattedTypes),
        'stats_summary' => [
            'total_types' => count($formattedTypes),
            'active_types' => count(array_filter($formattedTypes, function($t) { return $t['is_active']; })),
            'total_messages' => array_sum(array_column($formattedTypes, 'message_count')),
            'avg_response_time' => array_sum(array_column($formattedTypes, 'avg_response_time')) / count($formattedTypes),
            'completion_rate' => array_sum(array_column($formattedTypes, 'completed_count')) / max(1, array_sum(array_column($formattedTypes, 'message_count'))) * 100
        ]
    ], JSON_PRETTY_PRINT);
    exit();
    
} catch (Exception $e) {
    error_log("Message Types API error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}
?>