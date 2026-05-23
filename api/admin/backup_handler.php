<?php
// api/admin/backup_handler.php

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

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token tidak ditemukan']);
    exit;
}

// Parse action from request
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's multipart form data (file upload)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        // Try to get from raw input
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
}

// Define backup directory
$backupDir = ROOT_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Handle different actions
switch ($action) {
    case 'create_backup':
        $result = createDatabaseBackup($backupDir);
        echo json_encode($result);
        break;
        
    case 'restore_database':
        if (!isset($_FILES['backup_file'])) {
            echo json_encode(['success' => false, 'message' => 'File backup tidak ditemukan']);
            exit;
        }
        $result = restoreDatabase($_FILES['backup_file']);
        echo json_encode($result);
        break;
        
    case 'delete_backup':
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = $input['filename'] ?? '';
        $result = deleteBackupFile($filename, $backupDir);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal: ' . $action]);
}

/**
 * Create full database backup
 */
function createDatabaseBackup($backupDir) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_" . DB_NAME . "_{$timestamp}.sql";
    $backupPath = $backupDir . '/' . $filename;
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get all tables
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $sql = "-- =====================================================\n";
        $sql .= "-- RESPONSIVE MESSAGE APP - FULL DATABASE BACKUP\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . DB_NAME . "\n";
        $sql .= "-- =====================================================\n\n";
        
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "START TRANSACTION;\n\n";
        
        $totalTables = 0;
        
        foreach ($tables as $table) {
            $totalTables++;
            
            // Get create table syntax
            $createStmt = $db->query("SHOW CREATE TABLE `$table`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createRow[1] . ";\n\n";
            
            // Get data
            $dataStmt = $db->query("SELECT * FROM `$table`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Get column names
                $colStmt = $db->query("SHOW COLUMNS FROM `$table`");
                $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $rowValues[] = $value;
                        } else {
                            $escaped = str_replace("'", "''", $value);
                            $rowValues[] = "'" . $escaped . "'";
                        }
                    }
                    $values[] = "(" . implode(', ', $rowValues) . ")";
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sql .= "COMMIT;\n";
        
        file_put_contents($backupPath, $sql);
        
        $size = filesize($backupPath);
        
        return [
            'success' => true,
            'filename' => $filename,
            'size' => $size,
            'tables' => $totalTables,
            'views' => 0,
            'levels' => 1,
            'message' => "Backup berhasil dibuat: $filename (" . round($size / 1024 / 1024, 2) . " MB)"
        ];
        
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Backup gagal: ' . $e->getMessage()
        ];
    }
}

/**
 * Restore database from backup file
 */
function restoreDatabase($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload file gagal: ' . $file['error']];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        return ['success' => false, 'message' => 'Format file tidak valid. Hanya file .sql yang diperbolehkan'];
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $sql = file_get_contents($file['tmp_name']);
        
        if ($sql === false) {
            return ['success' => false, 'message' => 'Gagal membaca file backup'];
        }
        
        // Disable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Split queries by semicolon
        $queries = explode(';', $sql);
        $count = 0;
        $errors = [];
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query) && strpos($query, '--') !== 0) {
                try {
                    $db->exec($query);
                    $count++;
                } catch (Exception $e) {
                    // Skip errors for duplicate entries and already exists
                    $errorMsg = $e->getMessage();
                    if (strpos($errorMsg, 'Duplicate entry') === false && 
                        strpos($errorMsg, 'already exists') === false) {
                        $errors[] = $errorMsg;
                        error_log("Query error: " . substr($errorMsg, 0, 200));
                    }
                }
            }
        }
        
        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => "Database berhasil direstore dari file: " . basename($file['name']) . " ($count queries dieksekusi)"
            ];
        } else {
            return [
                'success' => true,
                'message' => "Database berhasil direstore dengan " . count($errors) . " warning (non-kritis)"
            ];
        }
        
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Restore gagal: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete backup file
 */
function deleteBackupFile($filename, $backupDir) {
    if (empty($filename)) {
        return ['success' => false, 'message' => 'Nama file tidak valid'];
    }
    
    // Security: only allow backup_*.sql files
    if (!preg_match('/^backup_.*\.sql$/', $filename)) {
        return ['success' => false, 'message' => 'Nama file tidak valid'];
    }
    
    $filepath = $backupDir . '/' . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            return ['success' => true, 'message' => 'File backup berhasil dihapus'];
        } else {
            return ['success' => false, 'message' => 'Gagal menghapus file backup'];
        }
    } else {
        return ['success' => false, 'message' => 'File backup tidak ditemukan'];
    }
}