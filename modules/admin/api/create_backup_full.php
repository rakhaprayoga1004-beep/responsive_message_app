<?php
/**
 * FULL DATABASE BACKUP - SAMA PERSIS DENGAN VERSI PHP UTAMA
 * File: modules/admin/api/create_backup_full.php
 * 
 * Backup mencakup SEMUA tabel (termasuk yang kosong) dengan urutan dependensi yang benar
 */

// Error reporting - matikan display error untuk JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Tentukan root path
$rootPath = dirname(__DIR__, 3); // modules/admin/api -> modules/admin -> modules -> root

// Load config
$configPath = $rootPath . '/config/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Config file not found: ' . $configPath]);
    exit;
}

require_once $configPath;

// Koneksi database langsung (tanpa class Database)
$host = DB_HOST;
$port = DB_PORT;
$dbname = DB_NAME;
$username = DB_USER;
$password = DB_PASS;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
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

// Fungsi untuk format file size
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

try {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_" . DB_NAME . "_{$timestamp}.sql";
    $backupDir = $rootPath . '/backups';
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupPath = $backupDir . '/' . $filename;
    
    $dependencies = getTableDependencies($db);
    $levels = $dependencies['levels'];
    $allViews = getAllViews($db);
    
    $totalTables = 0;
    foreach ($levels as $levelTables) {
        $totalTables += count($levelTables);
    }
    
    // Header SQL lengkap
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
    
    $sql .= "-- =====================================================\n";
    $sql .= "-- SETUP AWAL\n";
    $sql .= "-- =====================================================\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "START TRANSACTION;\n";
    $sql .= "SET time_zone = '+07:00';\n\n";
    
    // Proses setiap level dependensi
    foreach ($levels as $levelIdx => $levelTables) {
        $levelNum = $levelIdx + 1;
        
        if ($levelIdx == 0) {
            $levelDesc = 'TABEL TANPA FOREIGN KEY DEPENDENSI (LEVEL 1) - EKSEKUSI PERTAMA';
        } elseif ($levelIdx == 1) {
            $levelDesc = 'TABEL DENGAN FOREIGN KEY KE LEVEL 1 (LEVEL 2)';
        } elseif ($levelIdx == 2) {
            $levelDesc = 'TABEL DENGAN FOREIGN KEY KE LEVEL 1 & 2 (LEVEL 3)';
        } else {
            $levelDesc = 'SISA TABEL DENGAN FOREIGN KEY (LEVEL ' . $levelNum . ')';
        }
        
        $sql .= "-- =====================================================\n";
        $sql .= "-- LEVEL {$levelNum}: {$levelDesc}\n";
        $sql .= "-- =====================================================\n";
        $sql .= "-- Jumlah tabel dalam level ini: " . count($levelTables) . "\n";
        $sql .= "-- =====================================================\n\n";
        
        foreach ($levelTables as $table) {
            $sql .= "-- =====================================================\n";
            $sql .= "-- Tabel: `{$table}`\n";
            $sql .= "-- =====================================================\n";
            
            // DROP TABLE IF EXISTS
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // CREATE TABLE (struktur lengkap)
            $createStmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $sql .= $createRow[1] . ";\n\n";
            
            // INSERT DATA (semua data, termasuk jika kosong tetap diproses)
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
                $sql .= "-- {$rowCount} rows inserted into `{$table}`\n\n";
            } else {
                $sql .= "-- Table `{$table}` is empty (0 rows)\n\n";
            }
        }
        
        $sql .= "\n-- =====================================================\n";
        $sql .= "-- END OF LEVEL {$levelNum}\n";
        $sql .= "-- =====================================================\n\n";
    }
    
    // Tambahkan VIEWS (dibuat setelah semua tabel)
    if (!empty($allViews)) {
        $sql .= "-- =====================================================\n";
        $sql .= "-- PEMBUATAN VIEWS (" . count($allViews) . " view)\n";
        $sql .= "-- =====================================================\n";
        $sql .= "-- Views dibuat setelah semua tabel selesai dibuat\n";
        $sql .= "-- =====================================================\n\n";
        
        foreach ($allViews as $viewName) {
            $createViewStmt = $db->query("SHOW CREATE VIEW `{$viewName}`");
            $createViewRow = $createViewStmt->fetch(PDO::FETCH_NUM);
            $sql .= "-- View: `{$viewName}`\n";
            $sql .= "DROP VIEW IF EXISTS `{$viewName}`;\n";
            $sql .= $createViewRow[1] . ";\n\n";
        }
    }
    
    // Tambahkan semua FOREIGN KEY constraints di akhir
    $sql .= "-- =====================================================\n";
    $sql .= "-- PENAMBAHAN SEMUA FOREIGN KEY CONSTRAINTS\n";
    $sql .= "-- =====================================================\n";
    $sql .= "-- Foreign keys ditambahkan setelah semua data terisi\n";
    $sql .= "-- =====================================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Ambil semua foreign key
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
        ORDER BY TABLE_NAME, CONSTRAINT_NAME
    ");
    
    $fkAdded = [];
    $fkCount = 0;
    while ($fk = $fkStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $fk['TABLE_NAME'] . '_' . $fk['CONSTRAINT_NAME'];
        if (!in_array($key, $fkAdded)) {
            $sql .= "ALTER TABLE `{$fk['TABLE_NAME']}`\n";
            $sql .= "  ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}` FOREIGN KEY (`{$fk['COLUMN_NAME']}`) \n";
            $sql .= "  REFERENCES `{$fk['REFERENCED_TABLE_NAME']}` (`{$fk['REFERENCED_COLUMN_NAME']}`) ON DELETE CASCADE ON UPDATE CASCADE;\n";
            $fkAdded[] = $key;
            $fkCount++;
        }
    }
    
    if ($fkCount > 0) {
        $sql .= "\n-- {$fkCount} foreign key constraints added\n\n";
    } else {
        $sql .= "-- No foreign key constraints found\n\n";
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";
    
    // Footer
    $sql .= "\n-- =====================================================\n";
    $sql .= "-- BACKUP COMPLETED SUCCESSFULLY\n";
    $sql .= "-- =====================================================\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Total Tables: " . $totalTables . "\n";
    $sql .= "-- Total Views: " . count($allViews) . "\n";
    $sql .= "-- Total Foreign Keys: " . $fkCount . "\n";
    $sql .= "-- =====================================================\n";
    
    // Simpan file SQL
    file_put_contents($backupPath, $sql);
    
    if (file_exists($backupPath) && filesize($backupPath) > 0) {
        $size = filesize($backupPath);
        
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'path' => $backupPath,
            'size' => $size,
            'tables' => $totalTables,
            'views' => count($allViews),
            'levels' => count($levels),
            'message' => "Backup berhasil: {$filename} (" . formatFileSize($size) . ") - {$totalTables} tabel, " . count($allViews) . " view"
        ]);
    } else {
        throw new Exception("Gagal menulis file backup");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Backup gagal: ' . $e->getMessage()
    ]);
}
?>