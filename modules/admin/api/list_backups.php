<?php
/**
 * API Endpoint untuk List Backup Files
 * File: modules/admin/api/list_backups.php
 */

require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check authentication
Auth::checkAuth();
if ($_SESSION['user_type'] !== 'Admin' && $_SESSION['privilege_level'] !== 'Full_Access') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Debug: Tampilkan path untuk debugging
$debug = [];
$debug['root_path'] = ROOT_PATH;
$debug['backup_dir'] = ROOT_PATH . '/backups';
$debug['is_dir'] = is_dir(ROOT_PATH . '/backups');
$debug['files_found'] = [];

$backupDir = ROOT_PATH . '/backups';
$backupFiles = [];

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

// Pastikan direktori backup ada
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    $debug['created_dir'] = true;
}

// Cari file backup dengan berbagai pattern
$patterns = [
    $backupDir . '/backup_*.sql',
    $backupDir . '/*.sql',
];

foreach ($patterns as $pattern) {
    $files = glob($pattern);
    foreach ($files as $file) {
        if (is_file($file)) {
            $debug['files_found'][] = basename($file);
            $backupFiles[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'size_formatted' => formatFileSize(filesize($file)),
                'date' => filemtime($file),
                'date_formatted' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => 'sql'
            ];
        }
    }
}

// Sort by date descending (newest first)
usort($backupFiles, function($a, $b) {
    return $b['date'] - $a['date'];
});

$totalSize = array_sum(array_column($backupFiles, 'size'));

// Hapus debug untuk production, tapi biarkan untuk testing
if (isset($_GET['debug'])) {
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'data' => [
            'backup_files' => $backupFiles,
            'system_stats' => [
                'total_backups' => count($backupFiles),
                'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                'backup_directory' => $backupDir,
                'is_writable' => is_writable($backupDir)
            ]
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'backup_files' => $backupFiles,
            'system_stats' => [
                'total_backups' => count($backupFiles),
                'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                'backup_directory' => $backupDir,
                'is_writable' => is_writable($backupDir)
            ]
        ]
    ]);
}
?>