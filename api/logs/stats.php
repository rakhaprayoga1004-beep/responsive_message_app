<?php
// api/logs/stats.php
// System Logs Statistics API

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Set header untuk JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Get parameters from query string
$logType = isset($_GET['type']) ? $_GET['type'] : 'audit'; // audit, security, errors
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 90; // Max 90 days

try {
    $stats = [];
    
    // Build WHERE clause based on log type
    $whereClause = "DATE(l.created_at) BETWEEN '$startDate' AND '$endDate'";
    
    // Add type filter based on log type
    if ($logType === 'security') {
        $whereClause .= " AND l.action_type IN ('LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'PASSWORD_CHANGE')";
    } elseif ($logType === 'errors') {
        $whereClause .= " AND l.action_type IN ('ERROR', 'EXCEPTION', 'VALIDATION_ERROR')";
    }
    // For 'audit' type, no additional filter
    
    // Total logs
    $result = $db->query("
        SELECT COUNT(*) as total 
        FROM system_logs l
        WHERE $whereClause
    ");
    $stats['total_logs'] = intval($result->fetch(PDO::FETCH_ASSOC)['total']);
    
    // Unique users
    $result = $db->query("
        SELECT COUNT(DISTINCT user_id) as unique_users 
        FROM system_logs l
        WHERE user_id IS NOT NULL 
        AND $whereClause
    ");
    $stats['unique_users'] = intval($result->fetch(PDO::FETCH_ASSOC)['unique_users']);
    
    // Unique actions
    $result = $db->query("
        SELECT COUNT(DISTINCT action_type) as unique_actions 
        FROM system_logs l
        WHERE $whereClause
    ");
    $stats['unique_actions'] = intval($result->fetch(PDO::FETCH_ASSOC)['unique_actions']);
    
    // Last 24 hours from end date
    $result = $db->query("
        SELECT COUNT(*) as last_24h 
        FROM system_logs l
        WHERE created_at >= DATE_SUB('$endDate', INTERVAL 24 HOUR)
        AND $whereClause
    ");
    $stats['last_24h'] = intval($result->fetch(PDO::FETCH_ASSOC)['last_24h']);
    
    // Modifications (CREATE, UPDATE, DELETE)
    $result = $db->query("
        SELECT COUNT(*) as modifications 
        FROM system_logs l
        WHERE action_type IN ('CREATE', 'UPDATE', 'DELETE')
        AND $whereClause
    ");
    $stats['modifications'] = intval($result->fetch(PDO::FETCH_ASSOC)['modifications']);
    
    // Failed logins
    $result = $db->query("
        SELECT COUNT(*) as failed_logins 
        FROM system_logs l
        WHERE action_type = 'LOGIN_FAILED'
        AND $whereClause
    ");
    $stats['failed_logins'] = intval($result->fetch(PDO::FETCH_ASSOC)['failed_logins']);
    
    // Oldest and newest logs
    $result = $db->query("
        SELECT MIN(created_at) as oldest, MAX(created_at) as newest 
        FROM system_logs l
        WHERE $whereClause
    ");
    $dates = $result->fetch(PDO::FETCH_ASSOC);
    $stats['oldest_log'] = $dates['oldest'];
    $stats['newest_log'] = $dates['newest'];
    
    // Daily activity for selected date range
    $dailyActivity = [];
    
    // Build daily activity query based on log type
    $dailyQuery = "
        SELECT 
            DATE(l.created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN l.action_type = 'CREATE' THEN 1 ELSE 0 END) as creates,
            SUM(CASE WHEN l.action_type = 'UPDATE' THEN 1 ELSE 0 END) as updates,
            SUM(CASE WHEN l.action_type = 'DELETE' THEN 1 ELSE 0 END) as deletes,
            SUM(CASE WHEN l.action_type = 'LOGIN' THEN 1 ELSE 0 END) as logins,
            SUM(CASE WHEN l.action_type = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as failed
        FROM system_logs l
        WHERE $whereClause
        GROUP BY DATE(l.created_at)
        ORDER BY date ASC
        LIMIT $limit
    ";
    
    $result = $db->query($dailyQuery);
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $dailyActivity[] = $row;
    }
    
    // Fill missing dates with zero values
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end);
    
    $activityMap = [];
    foreach ($dailyActivity as $activity) {
        $activityMap[$activity['date']] = $activity;
    }
    
    $filledDailyActivity = [];
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        if (isset($activityMap[$dateStr])) {
            $filledDailyActivity[] = $activityMap[$dateStr];
        } else {
            $filledDailyActivity[] = [
                'date' => $dateStr,
                'total' => 0,
                'creates' => 0,
                'updates' => 0,
                'deletes' => 0,
                'logins' => 0,
                'failed' => 0
            ];
        }
    }
    
    // Action distribution
    $actionDistribution = [];
    $totalLogs = $stats['total_logs'];
    if ($totalLogs > 0) {
        $result = $db->query("
            SELECT 
                l.action_type,
                COUNT(*) as total,
                (COUNT(*) * 100.0 / $totalLogs) as percentage
            FROM system_logs l
            WHERE $whereClause
            GROUP BY l.action_type
            ORDER BY total DESC
            LIMIT 10
        ");
        
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $actionDistribution[] = $row;
        }
    }
    
    // Top active users
    $topUsers = [];
    $result = $db->query("
        SELECT 
            u.id,
            u.nama_lengkap,
            u.user_type,
            COUNT(l.id) as activity_count,
            MAX(l.created_at) as last_activity
        FROM system_logs l
        INNER JOIN users u ON l.user_id = u.id
        WHERE l.user_id IS NOT NULL
        AND $whereClause
        GROUP BY l.user_id, u.nama_lengkap, u.user_type
        ORDER BY activity_count DESC
        LIMIT 5
    ");
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $topUsers[] = $row;
    }
    
    // ============================================
    // MESSAGE TYPE STATISTICS (PERBAIKAN: menggunakan description)
    // ============================================
    $messageTypeStats = [];
    $stmt = $db->query("
        SELECT 
            mt.id,
            mt.jenis_pesan,
            mt.description,  -- PERBAIKAN: menggunakan description
            mt.response_deadline_hours,
            mt.allow_external,
            mt.is_active,
            COUNT(m.id) as total_messages,
            SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END) as completed,
            AVG(TIMESTAMPDIFF(HOUR, m.created_at, COALESCE(mr.created_at, NOW()))) as avg_response_time
        FROM message_types mt
        LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
        LEFT JOIN message_responses mr ON m.id = mr.message_id
        GROUP BY mt.id
        ORDER BY mt.id ASC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['avg_response_time_formatted'] = $row['avg_response_time'] ? round($row['avg_response_time'], 1) . 'h' : '-';
        // Jika description null, set sebagai '-'
        if (empty($row['description'])) {
            $row['description'] = '-';
        }
        $messageTypeStats[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'daily_activity' => $filledDailyActivity,
        'action_distribution' => $actionDistribution,
        'top_users' => $topUsers,
        'message_type_stats' => $messageTypeStats,
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'log_type' => $logType
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving stats: ' . $e->getMessage()
    ]);
}
?>