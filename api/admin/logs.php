<?php
// api/admin/logs.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Decode token
$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData || $userData['user_type'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $actionType = $_GET['action'] ?? 'all';
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        // Build WHERE clause with table alias
        $whereConditions = [];
        $params = [];
        
        $whereConditions[] = "a.created_at BETWEEN ? AND ?";
        $params[] = $startDateTime;
        $params[] = $endDateTime;
        
        if ($actionType !== 'all') {
            $whereConditions[] = "a.action_type = ?";
            $params[] = $actionType;
        }
        
        if ($userId > 0) {
            $whereConditions[] = "a.user_id = ?";
            $params[] = $userId;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(a.table_name LIKE ? OR a.new_value LIKE ? OR u.nama_lengkap LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count using select method
        $countSql = "SELECT COUNT(*) as total FROM audit_logs a $whereClause";
        $countResult = $db->select($countSql, $params);
        $totalLogs = !empty($countResult) ? (int)$countResult[0]['total'] : 0;
        $totalPages = ceil($totalLogs / $limit);
        
        // Get logs with pagination using select method
        $sql = "SELECT 
                    a.*,
                    u.nama_lengkap as user_name,
                    u.user_type,
                    u.email as user_email
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                $whereClause
                ORDER BY a.created_at DESC
                LIMIT $limit OFFSET $offset";
        
        $logs = $db->select($sql, $params);
        
        // Get statistics using select method
        $statsSql = "SELECT 
                        COUNT(*) as total_logs,
                        COUNT(DISTINCT user_id) as unique_users,
                        COUNT(DISTINCT action_type) as unique_actions,
                        SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as last_24h,
                        SUM(CASE WHEN a.action_type IN ('UPDATE', 'DELETE') THEN 1 ELSE 0 END) as modifications,
                        SUM(CASE WHEN a.action_type = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as failed_logins,
                        MIN(a.created_at) as oldest_log,
                        MAX(a.created_at) as newest_log
                    FROM audit_logs a";
        $statsResult = $db->select($statsSql);
        $stats = !empty($statsResult) ? $statsResult[0] : [];
        
        // Get action distribution
        $actionSql = "SELECT 
                        a.action_type,
                        COUNT(*) as total,
                        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM audit_logs WHERE created_at BETWEEN '$startDateTime' AND '$endDateTime')) as percentage
                    FROM audit_logs a
                    WHERE a.created_at BETWEEN '$startDateTime' AND '$endDateTime'
                    GROUP BY a.action_type
                    ORDER BY total DESC";
        $actionDistribution = $db->select($actionSql);
        
        // ==================== DAILY ACTIVITY ====================
        $dailySql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as total,
                        COUNT(CASE WHEN action_type = 'CREATE' THEN 1 END) as creates,
                        COUNT(CASE WHEN action_type = 'UPDATE' THEN 1 END) as updates,
                        COUNT(CASE WHEN action_type = 'DELETE' THEN 1 END) as deletes,
                        COUNT(CASE WHEN action_type = 'LOGIN' THEN 1 END) as logins,
                        COUNT(CASE WHEN action_type = 'LOGIN_FAILED' THEN 1 END) as failed
                    FROM audit_logs
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC";
        $stmt = $conn->prepare($dailySql);
        $stmt->execute([$startDateTime, $endDateTime]);
        $dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top users
        $usersSql = "SELECT 
                        u.id,
                        u.nama_lengkap,
                        u.user_type,
                        COUNT(a.id) as activity_count,
                        MAX(a.created_at) as last_activity
                    FROM users u
                    JOIN audit_logs a ON u.id = a.user_id
                    WHERE a.created_at BETWEEN '$startDateTime' AND '$endDateTime'
                    GROUP BY u.id, u.nama_lengkap, u.user_type
                    ORDER BY activity_count DESC
                    LIMIT 10";
        $topUsers = $db->select($usersSql);
        
        // Get users for filter
        $userListSql = "SELECT id, nama_lengkap, user_type FROM users WHERE is_active = 1 ORDER BY nama_lengkap";
        $userList = $db->select($userListSql);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'stats' => $stats,
                'action_distribution' => $actionDistribution,
                'daily_activity' => $dailyActivity,
                'top_users' => $topUsers,
                'user_list' => $userList,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalLogs,
                    'total_pages' => $totalPages
                ]
            ]
        ]);
        
    } elseif ($method === 'DELETE') {
        $logId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($logId > 0) {
            $sql = "DELETE FROM audit_logs WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$logId]);
            echo json_encode(['success' => true, 'message' => 'Log berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'clear_old_logs') {
            $days = (int)($input['days'] ?? 90);
            $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            
            echo json_encode(['success' => true, 'message' => "Berhasil membersihkan $deleted entri log", 'deleted' => $deleted]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
        }
    }
    
} catch (Exception $e) {
    error_log("Logs API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>