<?php
// C:\FlyEnv-Data\app\apache-2.4.67\Apache24\htdocs\responsive-message-app\api\admin\user_growth.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bersihkan output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

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

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Get period parameter (default 180days)
$period = isset($_GET['period']) ? $_GET['period'] : '180days';

// Define days based on period
$periods = [
    '7days' => 7,
    '30days' => 30,
    '60days' => 60,
    '90days' => 90,
    '180days' => 180,
    '1year' => 365,
    '2years' => 730,
    '3years' => 1095
];

$days = isset($periods[$period]) ? $periods[$period] : 180;

try {
    $db = Database::getInstance();
    
    // Cek apakah tabel users ada
    $tableCheck = $db->select("SHOW TABLES LIKE 'users'");
    if (empty($tableCheck)) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Table users not found'
        ]);
        exit;
    }
    
    // Query user growth data
    $data = $db->select("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_users
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ", [$days]);
    
    ob_clean();
    echo json_encode([
        'success' => true, 
        'data' => $data ?: [],
        'period' => $period,
        'days' => $days
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>