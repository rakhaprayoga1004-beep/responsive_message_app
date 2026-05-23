<?php
// api/admin/download_backup.php

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

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama file tidak ditemukan']);
    exit;
}

// Security: only allow backup_*.sql files
if (!preg_match('/^backup_.*\.sql$/', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama file tidak valid']);
    exit;
}

$backupDir = ROOT_PATH . '/backups';
$filepath = $backupDir . '/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
    exit;
}

// Send file for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($filepath);
exit;