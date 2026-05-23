<?php
/**
 * Simple API Endpoint untuk Create Backup - Tanpa session PHP
 * File: modules/admin/api/create_backup_simple.php
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

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
require_once '../../../includes/functions.php';

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

// Pastikan direktori backup ada
$backupDir = ROOT_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Fungsi sederhana untuk backup tabel
function backupTable($db, $table, &$sql) {
    // Get create table statement
    $stmt = $db->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $row[1] . ";\n\n";
    
    // Get data
    $dataStmt = $db->query("SELECT * FROM `$table`");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        // Get column names
        $columns = array_keys($rows[0]);
        $columnNames = '`' . implode('`, `', $columns) . '`';
        
        $values = [];
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $rowValues[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $rowValues[] = $value;
                } else {
                    $rowValues[] = "'" . str_replace("'", "''", $value) . "'";
                }
            }
            $values[] = "(" . implode(', ', $rowValues) . ")";
        }
        
        $sql .= "INSERT INTO `$table` ($columnNames) VALUES \n";
        $sql .= implode(",\n", $values) . ";\n\n";
    } else {
        $sql .= "-- Table `$table` is empty\n\n";
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_" . DB_NAME . "_{$timestamp}.sql";
    $backupPath = $backupDir . '/' . $filename;
    
    $sql = "-- =====================================================\n";
    $sql .= "-- RESPONSIVE MESSAGE APP - DATABASE BACKUP\n";
    $sql .= "-- =====================================================\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- =====================================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "START TRANSACTION;\n\n";
    
    // Backup each table
    $tableCount = 0;
    foreach ($tables as $table) {
        backupTable($db, $table, $sql);
        $tableCount++;
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";
    
    // Save file
    file_put_contents($backupPath, $sql);
    
    $fileSize = filesize($backupPath);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => $fileSize,
        'tables' => $tableCount,
        'message' => "Backup berhasil: $filename (" . round($fileSize / 1024, 2) . " KB)"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Backup gagal: ' . $e->getMessage()
    ]);
}
?>