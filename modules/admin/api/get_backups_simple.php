<?php
/**
 * Simple API Endpoint untuk List Backup Files - Tanpa session PHP
 * File: modules/admin/api/get_backups_simple.php
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error di output
ini_set('log_errors', 1);

// Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../../config/config.php';
require_once '../../../includes/auth.php';

// Verify token (tanpa session PHP)
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');

if (empty($authHeader)) {
    echo json_encode(['success' => false, 'message' => 'No authorization header']);
    exit;
}

// Extract token
$token = str_replace('Bearer ', '', $authHeader);

// Verify token
try {
    $payload = Auth::verifyToken($token);
    if (!$payload) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    $userType = $payload['user_type'] ?? '';
    $privilegeLevel = $payload['privilege_level'] ?? '';
    
    if ($userType !== 'Admin' && $privilegeLevel !== 'Full_Access') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Token verification failed: ' . $e->getMessage()]);
    exit;
}

// Path ke direktori backup
$backupDir = ROOT_PATH . '/backups';
$backupFiles = [];

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

// Cek direktori
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Cari file backup
$files = glob($backupDir . '/backup_*.sql');
foreach ($files as $file) {
    $backupFiles[] = [
        'name' => basename($file),
        'size' => filesize($file),
        'size_formatted' => formatFileSize(filesize($file)),
        'date' => filemtime($file),
        'date_formatted' => date('Y-m-d H:i:s', filemtime($file))
    ];
}

// Sort by date descending (newest first)
usort($backupFiles, function($a, $b) {
    return $b['date'] - $a['date'];
});

echo json_encode([
    'success' => true,
    'data' => [
        'backup_files' => $backupFiles,
        'system_stats' => [
            'total_backups' => count($backupFiles),
            'backup_directory' => $backupDir
        ]
    ]
]);
?>