<?php
/**
 * Simple API Endpoint untuk List Backup Files - TANPA AUTH
 * File: modules/admin/api/get_backups_no_auth.php
 */

// Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Tentukan root path
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
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