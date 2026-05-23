<?php
/**
 * API Endpoint untuk Create Backup - Khusus untuk Flutter
 * File: modules/admin/api/create_backup.php
 * URL: http://localhost:8090/responsive-message-app/modules/admin/api/create_backup.php
 */

// Error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Hentikan output buffering
while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication
Auth::checkAuth();
if ($_SESSION['user_type'] !== 'Admin' && $_SESSION['privilege_level'] !== 'Full_Access') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Pastikan direktori backup ada
$backupDir = ROOT_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Fungsi untuk mendapatkan semua tabel (termasuk yang kosong)
function getAllTables($db) {
    $tables = [];
    $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Fungsi untuk mendapatkan semua views
function getAllViews($db) {
    $views = [];
    $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $views[] = $row[0];
    }
    return $views;
}

// Fungsi untuk mendapatkan foreign key dependencies
function getTableDependencies($db) {
    $foreignKeys = [];
    $tables = getAllTables($db);
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$table]);
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($fks)) {
            $foreignKeys[$table] = $fks;
        }
    }
    
    // Tentukan level dependensi
    $levels = [];
    $level = 0;
    $processed = [];
    $allTables = $tables;
    
    while (count($processed) < count($allTables)) {
        $currentLevelTables = [];
        
        foreach ($allTables as $table) {
            if (in_array($table, $processed)) continue;
            
            $hasUnprocessedDeps = false;
            if (isset($foreignKeys[$table])) {
                foreach ($foreignKeys[$table] as $fk) {
                    $refTable = $fk['REFERENCED_TABLE_NAME'];
                    if (!in_array($refTable, $processed) && $refTable != $table && in_array($refTable, $allTables)) {
                        $hasUnprocessedDeps = true;
                        break;
                    }
                }
            }
            
            if (!$hasUnprocessedDeps) {
                $currentLevelTables[] = $table;
            }
        }
        
        if (empty($currentLevelTables)) {
            foreach ($allTables as $table) {
                if (!in_array($table, $processed)) {
                    $currentLevelTables[] = $table;
                }
            }
        }
        
        $levels[$level] = $currentLevelTables;
        $processed = array_merge($processed, $currentLevelTables);
        $level++;
    }
    
    return ['levels' => $levels, 'foreignKeys' => $foreignKeys];
}

try {
    $db = Database::getInstance()->getConnection();
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_" . DB_NAME . "_{$timestamp}.sql";
    $backupPath = $backupDir . '/' . $filename;
    
    $dependencies = getTableDependencies($db);
    $levels = $dependencies['levels'];
    $allViews = getAllViews($db);
    
    $totalTables = 0;
    foreach ($levels as $levelTables) {
        $totalTables += count($levelTables);
    }
    
    // Header SQL
    $sql = "-- =====================================================\n";
    $sql .= "-- RESPONSIVE MESSAGE APP - FULL DATABASE BACKUP\n";
    $sql .= "-- =====================================================\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- Host: " . DB_HOST . ":" . DB_PORT . "\n";
    $sql .= "-- PHP Version: " . phpversion() . "\n";
    $sql .= "-- Total Tables: " . $totalTables . "\n";
    $sql .= "-- Total Views: " . count($allViews) . "\n";
    $sql .= "-- =====================================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "START TRANSACTION;\n";
    $sql .= "SET time_zone = '+07:00';\n\n";
    
    // Proses setiap level
    foreach ($levels as $levelIdx => $levelTables) {
        $levelNum = $levelIdx + 1;
        
        $sql .= "-- =====================================================\n";
        $sql .= "-- LEVEL {$levelNum}: " . count($levelTables) . " tabel\n";
        $sql .= "-- =====================================================\n\n";
        
        foreach ($levelTables as $table) {
            // DROP TABLE
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // CREATE TABLE
            $createStmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $sql .= $createRow[1] . ";\n\n";
            
            // INSERT DATA (termasuk jika kosong)
            $dataStmt = $db->query("SELECT * FROM `{$table}`");
            $columns = [];
            $colStmt = $db->query("SHOW COLUMNS FROM `{$table}`");
            while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $col['Field'];
            }
            
            $rowCount = 0;
            $insertValues = [];
            
            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped = str_replace("'", "''", $value);
                        $values[] = "'" . $escaped . "'";
                    }
                }
                $insertValues[] = "(" . implode(', ', $values) . ")";
                $rowCount++;
            }
            
            if ($rowCount > 0) {
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES \n";
                $sql .= implode(",\n", $insertValues) . ";\n\n";
            } else {
                $sql .= "-- Table `{$table}` is empty (0 rows)\n\n";
            }
        }
    }
    
    // Tambahkan VIEWS jika ada
    if (!empty($allViews)) {
        $sql .= "-- =====================================================\n";
        $sql .= "-- VIEWS (" . count($allViews) . " view)\n";
        $sql .= "-- =====================================================\n\n";
        
        foreach ($allViews as $viewName) {
            $createViewStmt = $db->query("SHOW CREATE VIEW `{$viewName}`");
            $createViewRow = $createViewStmt->fetch(PDO::FETCH_NUM);
            $sql .= "DROP VIEW IF EXISTS `{$viewName}`;\n";
            $sql .= $createViewRow[1] . ";\n\n";
        }
    }
    
    // Tambahkan FOREIGN KEY constraints di akhir
    $sql .= "-- =====================================================\n";
    $sql .= "-- FOREIGN KEY CONSTRAINTS\n";
    $sql .= "-- =====================================================\n\n";
    
    $fkStmt = $db->query("
        SELECT 
            TABLE_NAME,
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
        GROUP BY CONSTRAINT_NAME
    ");
    
    $fkCount = 0;
    while ($fk = $fkStmt->fetch(PDO::FETCH_ASSOC)) {
        $sql .= "ALTER TABLE `{$fk['TABLE_NAME']}`\n";
        $sql .= "  ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}` FOREIGN KEY (`{$fk['COLUMN_NAME']}`) \n";
        $sql .= "  REFERENCES `{$fk['REFERENCED_TABLE_NAME']}` (`{$fk['REFERENCED_COLUMN_NAME']}`);\n";
        $fkCount++;
    }
    
    if ($fkCount > 0) {
        $sql .= "\n-- {$fkCount} foreign key constraints added\n\n";
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";
    
    // Footer
    $sql .= "\n-- =====================================================\n";
    $sql .= "-- BACKUP COMPLETED SUCCESSFULLY\n";
    $sql .= "-- =====================================================\n";
    
    // Simpan file
    file_put_contents($backupPath, $sql);
    
    $fileSize = filesize($backupPath);
    
    // Catat ke log
    error_log("Backup created: $filename (" . round($fileSize / 1024, 2) . " KB)");
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => $fileSize,
        'tables' => $totalTables,
        'views' => count($allViews),
        'levels' => count($levels),
        'message' => "Backup berhasil: $filename (" . round($fileSize / 1024, 2) . " KB)"
    ]);
    
} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Backup gagal: ' . $e->getMessage()
    ]);
}
?>