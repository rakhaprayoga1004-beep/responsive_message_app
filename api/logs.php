<?php
// api/logs.php
// System Logs Management API

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set header untuk JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
$user = verifyAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user has admin privileges
if (!in_array($user['user_type'], ['Admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
    exit();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGetLogs($db, $user);
} elseif ($method === 'POST') {
    handlePostLogs($db, $user);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGetLogs($db, $user) {
    // Get query parameters
    $logType = isset($_GET['type']) ? $_GET['type'] : 'audit';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $actionType = isset($_GET['action']) ? $_GET['action'] : 'all';
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $export = isset($_GET['export']) ? $_GET['export'] : '';
    
    // Calculate offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    // Date range filter
    $whereConditions[] = "DATE(l.created_at) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
    
    // Log type filter (based on action_type categories)
    if ($logType === 'security') {
        $whereConditions[] = "l.action_type IN ('LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'PASSWORD_CHANGE')";
    } elseif ($logType === 'errors') {
        $whereConditions[] = "l.action_type IN ('ERROR', 'EXCEPTION', 'VALIDATION_ERROR')";
    }
    
    // Action type filter
    if ($actionType !== 'all') {
        $whereConditions[] = "l.action_type = :action_type";
        $params[':action_type'] = $actionType;
    }
    
    // User filter
    if ($userId > 0) {
        $whereConditions[] = "l.user_id = :user_id";
        $params[':user_id'] = $userId;
    }
    
    // Search filter
    if (!empty($search)) {
        $whereConditions[] = "(l.description LIKE :search OR l.table_name LIKE :search OR u.nama_lengkap LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // If export requested, return data in specified format
    if (!empty($export) && in_array($export, ['csv', 'excel', 'pdf'])) {
        exportLogs($db, $whereClause, $params, $export);
        return;
    }
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE $whereClause
    ";
    
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($total / $limit);
    
    // Get logs data
    $query = "
        SELECT 
            l.id,
            l.created_at,
            l.user_id,
            l.action_type,
            l.table_name,
            l.record_id,
            l.old_value,
            l.new_value,
            l.description,
            l.ip_address,
            l.user_agent,
            u.nama_lengkap AS user_name,
            u.user_type,
            TIMESTAMPDIFF(HOUR, l.created_at, NOW()) AS hours_ago
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE $whereClause
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process logs to format data
    foreach ($logs as &$log) {
        // Format description - prioritize description field, then new_value, then old_value
        if (!empty($log['description'])) {
            // Already has description
        } elseif (!empty($log['new_value'])) {
            $log['description'] = $log['new_value'];
        } elseif (!empty($log['old_value'])) {
            $log['description'] = $log['old_value'];
        } else {
            $log['description'] = 'No description available';
        }
        
        // Limit description length
        if (strlen($log['description']) > 500) {
            $log['description'] = substr($log['description'], 0, 500) . '...';
        }
        
        // Format IP address
        if (empty($log['ip_address'])) {
            $log['ip_address'] = 'Unknown';
        }
        
        // Format user agent
        if (!empty($log['user_agent']) && strlen($log['user_agent']) > 100) {
            $log['user_agent'] = substr($log['user_agent'], 0, 100) . '...';
        }
        
        // Format user name
        if (empty($log['user_name'])) {
            $log['user_name'] = 'System';
        }
        
        // Format user type
        if (empty($log['user_type'])) {
            $log['user_type'] = 'System';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'total' => intval($total),
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages
    ]);
}

function handlePostLogs($db, $user) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    $action = isset($input['action']) ? $input['action'] : '';
    
    if ($action === 'clear_old_logs') {
        clearOldLogs($db, $user, $input);
    } elseif ($action === 'delete_log') {
        deleteLog($db, $user, $input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
}

function clearOldLogs($db, $user, $input) {
    $days = isset($input['days']) ? intval($input['days']) : 30;
    
    if ($days <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid days parameter']);
        return;
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get count of logs to be deleted
        $countQuery = "SELECT COUNT(*) as total FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $countStmt = $db->prepare($countQuery);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        $countStmt->execute();
        $deletedCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Delete old logs
        $deleteQuery = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindValue(':days', $days, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Log this action using direct insert (since Functions class might not be available)
        $logQuery = "
            INSERT INTO system_logs (user_id, action_type, description, ip_address, user_agent) 
            VALUES (:user_id, 'CLEANUP', :description, :ip, :ua)
        ";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            ':user_id' => $user['id'],
            ':description' => "Cleared logs older than $days days. Deleted $deletedCount records.",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully cleared $deletedCount logs older than $days days",
            'deleted_count' => intval($deletedCount)
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error clearing logs: ' . $e->getMessage()]);
    }
}

function deleteLog($db, $user, $input) {
    $logId = isset($input['log_id']) ? intval($input['log_id']) : 0;
    
    if ($logId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
        return;
    }
    
    try {
        // Get log details before deletion
        $getQuery = "SELECT * FROM system_logs WHERE id = :id";
        $getStmt = $db->prepare($getQuery);
        $getStmt->bindValue(':id', $logId, PDO::PARAM_INT);
        $getStmt->execute();
        $log = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Log not found']);
            return;
        }
        
        // Delete the log
        $deleteQuery = "DELETE FROM system_logs WHERE id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindValue(':id', $logId, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Log deletion action
        $logQuery = "
            INSERT INTO system_logs (user_id, action_type, description, ip_address, user_agent) 
            VALUES (:user_id, 'DELETE_LOG', :description, :ip, :ua)
        ";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            ':user_id' => $user['id'],
            ':description' => "Deleted log entry ID $logId (Action: {$log['action_type']}, User ID: {$log['user_id']})",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Log entry deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting log: ' . $e->getMessage()]);
    }
}

function exportLogs($db, $whereClause, $params, $format) {
    // Get all logs for export
    $query = "
        SELECT 
            l.id,
            l.created_at,
            u.nama_lengkap AS user_name,
            u.user_type,
            l.action_type,
            l.table_name,
            l.record_id,
            COALESCE(l.description, l.new_value, l.old_value, 'No description') AS description,
            l.ip_address,
            l.user_agent
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($logs);
    } elseif ($format === 'excel') {
        exportExcel($logs);
    } elseif ($format === 'pdf') {
        exportPDF($logs);
    }
}

function exportCSV($logs) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'ID', 'Timestamp', 'User', 'User Type', 'Action', 'Table', 'Record ID', 'Description', 'IP Address', 'User Agent'
    ]);
    
    // Data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['user_name'] ?? 'System',
            $log['user_type'] ?? 'System',
            $log['action_type'],
            $log['table_name'] ?? '-',
            $log['record_id'] ?? '-',
            $log['description'] ?? '-',
            $log['ip_address'] ?? '-',
            $log['user_agent'] ?? '-'
        ]);
    }
    
    fclose($output);
    exit();
}

function exportExcel($logs) {
    // For Excel, we'll use CSV with .xls extension
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '苦heet';
    echo '<th>ID</th><th>Timestamp</th><th>User</th><th>User Type</th><th>Action</th><th>Table</th><th>Record ID</th><th>Description</th><th>IP Address</th><th>User Agent</th>';
    echo '</tr>';
    
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($log['id']) . '</td>';
        echo '<td>' . htmlspecialchars($log['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($log['user_name'] ?? 'System') . '</td>';
        echo '<td>' . htmlspecialchars($log['user_type'] ?? 'System') . '</td>';
        echo '<td>' . htmlspecialchars($log['action_type']) . '</td>';
        echo '<td>' . htmlspecialchars($log['table_name'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($log['record_id'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($log['description'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($log['ip_address'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($log['user_agent'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

function exportPDF($logs) {
    // For PDF, we'll return HTML that can be printed to PDF
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="system_logs_' . date('Y-m-d_H-i-s') . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>System Logs Export</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #0B4D8A; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #0B4D8A; color: white; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '@media print { .no-print { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<h1>System Logs Report</h1>';
    echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Total Records: ' . count($logs) . '</p>';
    echo '<div class="no-print">';
    echo '<button onclick="window.print()">Print / Save as PDF</button>';
    echo '<button onclick="window.close()">Close</button>';
    echo '</div>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th><th>Timestamp</th><th>User</th><th>User Type</th><th>Action</th><th>Table</th><th>Record ID</th><th>Description</th><th>IP Address</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($log['id']) . '</td>';
        echo '<td>' . htmlspecialchars($log['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($log['user_name'] ?? 'System') . '</td>';
        echo '<td>' . htmlspecialchars($log['user_type'] ?? 'System') . '</td>';
        echo '<td>' . htmlspecialchars($log['action_type']) . '</td>';
        echo '<td>' . htmlspecialchars($log['table_name'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($log['record_id'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(substr($log['description'] ?? '-', 0, 200)) . '</td>';
        echo '<td>' . htmlspecialchars($log['ip_address'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}
?>