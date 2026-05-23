<?php
/**
 * API untuk statistik users
 * Lokasi: /responsive-message-app/api/users_stats.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = Database::getInstance();
    
    // Hitung total users (SEMUA user, tanpa filter)
    $totalAll = $db->select("SELECT COUNT(*) as total FROM users");
    $totalUsers = $totalAll[0]['total'] ?? 0;
    
    // Hitung per tipe user
    $typeCounts = $db->select("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
    
    $stats = [];
    foreach ($typeCounts as $type) {
        $stats[$type['user_type']] = (int)$type['count'];
    }
    
    // Hitung users aktif
    $active = $db->select("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['aktif'] = $active[0]['count'] ?? 0;
    
    // Hitung users nonaktif
    $inactive = $db->select("SELECT COUNT(*) as count FROM users WHERE is_active = 0");
    $stats['nonaktif'] = $inactive[0]['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'total' => $totalUsers  // Kembalikan total semua user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>