<?php
/**
 * API untuk manajemen Backup & Restore Database
 * Mendukung: create, list, restore, delete, download
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../includes/auth.php';

// Verify token
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userId = verifyToken($token);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

try {
    if ($action === 'create') {
        // Create backup
        $backupDir = ROOT_PATH . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_" . DB_NAME . "_$timestamp.sql";
        $backupPath = $backupDir . '/' . $filename;
        
        $host = str_replace(':8080', '', DB_HOST);
        $port = DB_PORT;
        $user = DB_USER;
        $pass = DB_PASS;
        $name = DB_NAME;
        
        if (!empty($port) && $port != '3306') {
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --routines --triggers --events %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($backupPath)
            );
        } else {
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --routines --triggers --events %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($backupPath)
            );
        }
        
        $output = shell_exec($command);
        
        if (file_exists($backupPath) && filesize($backupPath) > 0) {
            $size = filesize($backupPath);
            echo json_encode([
                'success' => true,
                'message' => "Backup created successfully: $filename (" . formatFileSize($size) . ")",
                'filename' => $filename,
                'size' => $size
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Backup failed: ' . ($output ?: 'Unknown error')]);
        }
        
    } elseif ($action === 'list') {
        // List backups
        $backupDir = ROOT_PATH . '/backups';
        $files = [];
        
        if (is_dir($backupDir)) {
            $allFiles = glob($backupDir . '/*.{sql,zip,gz}', GLOB_BRACE);
            foreach ($allFiles as $file) {
                $files[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'size_formatted' => formatFileSize(filesize($file)),
                    'date' => filemtime($file),
                    'date_formatted' => date('d/m/Y H:i:s', filemtime($file))
                ];
            }
            usort($files, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        
        echo json_encode(['success' => true, 'data' => $files]);
        
    } elseif ($action === 'restore') {
        // Restore database
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }
        
        $uploadedFile = $_FILES['backup_file']['tmp_name'];
        $originalName = $_FILES['backup_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['sql', 'zip', 'gz'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Only .sql, .zip, .gz allowed']);
            exit;
        }
        
        $backupDir = ROOT_PATH . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupPath = $backupDir . '/restore_' . date('Ymd_His') . '_' . $originalName;
        if (!move_uploaded_file($uploadedFile, $backupPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
            exit;
        }
        
        $sqlFile = $backupPath;
        if ($ext === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($backupPath) === true) {
                $zip->extractTo($backupDir);
                $zip->close();
                $files = glob($backupDir . '/*.sql');
                if (!empty($files)) {
                    $sqlFile = $files[0];
                }
            }
        } elseif ($ext === 'gz') {
            $sqlFile = $backupDir . '/' . basename($originalName, '.gz');
            $bufferSize = 4096;
            $file = gzopen($backupPath, 'rb');
            $outFile = fopen($sqlFile, 'wb');
            while (!gzeof($file)) {
                fwrite($outFile, gzread($file, $bufferSize));
            }
            fclose($outFile);
            gzclose($file);
        }
        
        $host = str_replace(':8080', '', DB_HOST);
        $port = DB_PORT;
        $user = DB_USER;
        $pass = DB_PASS;
        $name = DB_NAME;
        
        if (!empty($port) && $port != '3306') {
            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($sqlFile)
            );
        } else {
            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($sqlFile)
            );
        }
        
        $output = shell_exec($command);
        
        if (empty($output)) {
            echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Restore failed: ' . $output]);
        }
        
        @unlink($backupPath);
        if ($sqlFile !== $backupPath) {
            @unlink($sqlFile);
        }
        
    } elseif ($action === 'delete') {
        // Delete backup
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = basename($input['filename'] ?? '');
        $filepath = ROOT_PATH . '/backups/' . $filename;
        
        if (file_exists($filepath) && unlink($filepath)) {
            echo json_encode(['success' => true, 'message' => 'Backup deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
        }
        
    } elseif ($action === 'download') {
        // Download backup
        $filename = basename($_GET['filename'] ?? '');
        $filepath = ROOT_PATH . '/backups/' . $filename;
        
        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
    }
    
} catch (Exception $e) {
    error_log("Backup API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}