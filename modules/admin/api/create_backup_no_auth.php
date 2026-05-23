<?php
/**
 * Simple API Endpoint untuk Create Backup - TANPA AUTH
 * File: modules/admin/api/create_backup_no_auth.php
 */

// Error reporting - tampilkan semua error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Fungsi untuk log error
function logError($message) {
    $logFile = __DIR__ . '/backup_error.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

try {
    logError('Starting backup process...');
    
    // Tentukan root path dengan benar
    // File ini ada di: modules/admin/api/create_backup_no_auth.php
    $rootPath = dirname(__DIR__, 3); // modules/admin/api -> modules/admin -> modules -> root
    logError('Root path: ' . $rootPath);
    
    // Include config
    $configPath = $rootPath . '/config/config.php';
    logError('Config path: ' . $configPath);
    
    if (!file_exists($configPath)) {
        throw new Exception('Config file not found: ' . $configPath);
    }
    
    require_once $configPath;
    
    // Koneksi database
    $host = DB_HOST;
    $port = DB_PORT;
    $dbname = DB_NAME;
    $username = DB_USER;
    $password = DB_PASS;
    
    logError("Connecting to database: $host:$port/$dbname");
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    logError('Database connected successfully');
    
    // Pastikan direktori backup ada
    $backupDir = $rootPath . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        logError('Created backup directory: ' . $backupDir);
    }
    
    // Fungsi format file size
    function formatFileSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
    
    // Fungsi untuk backup satu tabel
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
    
    // Get all tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    logError('Found ' . count($tables) . ' tables');
    
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
        logError('Backing up table: ' . $table);
        backupTable($db, $table, $sql);
        $tableCount++;
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";
    
    // Save file
    file_put_contents($backupPath, $sql);
    
    $fileSize = filesize($backupPath);
    logError('Backup completed: ' . $filename . ' (' . formatFileSize($fileSize) . ')');
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => $fileSize,
        'tables' => $tableCount,
        'message' => "Backup berhasil: $filename (" . formatFileSize($fileSize) . ")"
    ]);
    
} catch (Exception $e) {
    logError('ERROR: ' . $e->getMessage());
    logError('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Backup gagal: ' . $e->getMessage()
    ]);
}
?>