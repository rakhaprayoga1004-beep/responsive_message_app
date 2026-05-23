<?php
/**
 * API untuk mendapatkan TOTAL STATISTIK SEMUA USER (TANPA FILTER)
 * Endpoint: api/total_users_stats.php
 * Method: GET
 * Response: JSON dengan total semua user dan statistik per tipe
 */

require_once '../config/config.php';
require_once '../includes/auth.php';

// Check authentication
Auth::checkAuth();

header('Content-Type: application/json');

// Get database connection
$db = Database::getInstance()->getConnection();

// Query untuk mendapatkan total semua user (aktif)
$totalQuery = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
$totalStmt = $db->query($totalQuery);
$totalAllUsers = $totalStmt->fetch()['total'];

// Query untuk mendapatkan statistik per tipe user (aktif)
$typeStatsQuery = "
    SELECT 
        user_type,
        COUNT(*) as count
    FROM users
    WHERE is_active = 1
    GROUP BY user_type
    ORDER BY count DESC
";
$typeStats = $db->query($typeStatsQuery)->fetchAll();

// Format response
$response = [
    'success' => true,
    'data' => [
        'total_all_users' => (int)$totalAllUsers,
        'stats_by_type' => []
    ]
];

foreach ($typeStats as $stat) {
    $response['data']['stats_by_type'][$stat['user_type']] = (int)$stat['count'];
}

echo json_encode($response);
?>