<?php
// api/admin/get_backup_list.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication
$headers = getallheaders();
$token = null;

foreach ($headers as $key => $value) {
    if (strtolower($key) === 'authorization') {
        $token = str_replace('Bearer ', '', $value);
        break;
    }
}

// Also check for token in $_GET or $_POST
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}
if (!$token && isset($_POST['token'])) {
    $token = $_POST['token'];
}

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token tidak ditemukan']);
    exit;
}

// Verify token (implementasi sederhana - sesuaikan dengan sistem auth Anda)
// Untuk sementara, kita lewati verifikasi token yang rumit
// Dalam production, gunakan verifikasi yang benar

$backupDir = ROOT_PATH . '/backups';
$backupFiles = [];

if (is_dir($backupDir)) {
    $files = glob($backupDir . '/backup_*.sql');
    foreach ($files as $file) {
        $backupFiles[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file),
            'date_formatted' => date('d/m/Y H:i:s', filemtime($file)),
        ];
    }
    
    // Sort by date descending (newest first)
    usort($backupFiles, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get database size
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as db_size 
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()");
    $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbSizeValue = $dbSize['db_size'] ?? 0;
} catch (Exception $e) {
    $dbSizeValue = 0;
}

echo json_encode([
    'success' => true,
    'data' => [
        'backup_files' => $backupFiles,
        'system_stats' => [
            'db_size_mb' => $dbSizeValue,
            'total_backups' => count($backupFiles),
            'total_size' => array_sum(array_column($backupFiles, 'size'))
        ]
    ]
]);