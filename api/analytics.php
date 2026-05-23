<?php
/**
 * API Analytics Dashboard
 * File: api/analytics.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication
Auth::checkAuth();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'all':
            getAllAnalytics($db);
            break;
        case 'overview':
            getSystemOverview($db);
            break;
        case 'status_stats':
            getMessageStatusStats($db);
            break;
        case 'priority_stats':
            getMessagePriorityStats($db);
            break;
        case 'daily_trends':
            getDailyTrends($db);
            break;
        default:
            getAllAnalytics($db);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ============================================
// DATE RANGE FUNCTIONS
// ============================================

function getDateRange() {
    $preset = $_GET['preset'] ?? $_POST['preset'] ?? 'last30days';
    $startDate = $_GET['start_date'] ?? $_POST['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? $_POST['end_date'] ?? '';
    
    if (empty($startDate) || empty($endDate)) {
        switch ($preset) {
            case 'today': 
                $startDate = date('Y-m-d'); 
                $endDate = date('Y-m-d'); 
                break;
            case 'yesterday': 
                $startDate = date('Y-m-d', strtotime('-1 day')); 
                $endDate = date('Y-m-d', strtotime('-1 day')); 
                break;
            case 'last7days': 
                $startDate = date('Y-m-d', strtotime('-7 days')); 
                $endDate = date('Y-m-d'); 
                break;
            case 'last30days': 
                $startDate = date('Y-m-d', strtotime('-30 days')); 
                $endDate = date('Y-m-d'); 
                break;
            case 'last90days': 
                $startDate = date('Y-m-d', strtotime('-90 days')); 
                $endDate = date('Y-m-d'); 
                break;
            case 'thisMonth': 
                $startDate = date('Y-m-01'); 
                $endDate = date('Y-m-d'); 
                break;
            case 'lastMonth': 
                $startDate = date('Y-m-01', strtotime('-1 month')); 
                $endDate = date('Y-m-t', strtotime('-1 month')); 
                break;
            case 'thisYear': 
                $startDate = date('Y-01-01'); 
                $endDate = date('Y-m-d'); 
                break;
            default: 
                $startDate = date('Y-m-d', strtotime('-30 days')); 
                $endDate = date('Y-m-d');
        }
    }
    
    return [
        'start' => $startDate . ' 00:00:00',
        'end' => $endDate . ' 23:59:59',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'preset' => $preset
    ];
}

// ============================================
// GET ALL ANALYTICS
// ============================================

function getAllAnalytics($db) {
    $range = getDateRange();
    
    // Get overview
    $sql = "SELECT 
                COUNT(*) as total_messages,
                AVG(CASE WHEN tanggal_respon IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) 
                    ELSE NULL END) as avg_response_time,
                COUNT(CASE WHEN status IN ('Disetujui', 'Selesai') THEN 1 END) as resolved_messages,
                COUNT(DISTINCT external_sender_id) as external_senders,
                COUNT(CASE WHEN is_external = 1 THEN 1 END) as external_messages
            FROM messages 
            WHERE created_at BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get trend
    $periodDays = max(1, (strtotime($range['end_date']) - strtotime($range['start_date'])) / 86400);
    $prevStartDate = date('Y-m-d', strtotime($range['start_date'] . ' - ' . $periodDays . ' days'));
    $prevEndDate = date('Y-m-d', strtotime($range['end_date'] . ' - ' . $periodDays . ' days'));
    
    $sql = "SELECT COUNT(*) as total FROM messages WHERE created_at BETWEEN ? AND ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$prevStartDate . ' 00:00:00', $prevEndDate . ' 23:59:59']);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $trend = ($prev['total'] ?? 0) > 0 
        ? round(((($overview['total_messages'] ?? 0) - ($prev['total'] ?? 0)) / ($prev['total'] ?? 1)) * 100, 1)
        : 0;
    
    $overview['trend_percentage'] = $trend;
    $overview['trend_direction'] = $trend >= 0 ? 'up' : 'down';
    
    // Get status stats
    $totalSql = "SELECT COUNT(*) as total FROM messages WHERE created_at BETWEEN ? AND ?";
    $totalStmt = $db->prepare($totalSql);
    $totalStmt->execute([$range['start'], $range['end']]);
    $totalMessages = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;
    
    $sql = "SELECT 
                COALESCE(status, 'Unknown') as status,
                COUNT(*) as total
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
            ORDER BY total DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $statusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $colors = [
        'Pending' => '#ffc107',
        'Disetujui' => '#28a745',
        'Ditolak' => '#dc3545',
        'Diproses' => '#0d6efd',
        'Dibaca' => '#17a2b8',
        'Selesai' => '#6c757d'
    ];
    
    foreach ($statusStats as &$row) {
        $row['percentage'] = $totalMessages > 0 ? round(($row['total'] / $totalMessages) * 100, 1) : 0;
        $row['color'] = $colors[$row['status']] ?? '#6c757d';
    }
    
    // Get priority stats
    $sql = "SELECT 
                COALESCE(priority, 'Normal') as priority,
                COUNT(*) as total
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY priority
            ORDER BY 
                CASE COALESCE(priority, 'Normal')
                    WHEN 'Urgent' THEN 1
                    WHEN 'High' THEN 2
                    WHEN 'Medium' THEN 3
                    WHEN 'Low' THEN 4
                    ELSE 5
                END";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $priorityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $priorityColors = [
        'Urgent' => '#dc3545',
        'High' => '#fd7e14',
        'Medium' => '#ffc107',
        'Low' => '#28a745',
        'Normal' => '#6c757d'
    ];
    
    foreach ($priorityStats as &$row) {
        $row['percentage'] = $totalMessages > 0 ? round(($row['total'] / $totalMessages) * 100, 1) : 0;
        $row['color'] = $priorityColors[$row['priority']] ?? '#6c757d';
    }
    
    // Get daily trends (last 30 days)
    $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_external = 1 THEN 1 ELSE 0 END) as external_messages
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate moving average
    $movingAvg = [];
    foreach ($dailyTrends as $i => $trend) {
        $sum = 0;
        $count = 0;
        for ($j = max(0, $i - 6); $j <= $i; $j++) {
            $sum += (int)($dailyTrends[$j]['total_messages'] ?? 0);
            $count++;
        }
        $movingAvg[] = $count > 0 ? round($sum / $count, 1) : 0;
    }
    
    // Get response time distribution
    $sql = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 1 THEN '0-1 jam'
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 6 THEN '1-6 jam'
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 12 THEN '6-12 jam'
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 24 THEN '12-24 jam'
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 48 THEN '24-48 jam'
                    WHEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) <= 72 THEN '48-72 jam'
                    ELSE '>72 jam'
                END as response_range,
                COUNT(*) as total
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
                AND tanggal_respon IS NOT NULL
            GROUP BY response_range
            ORDER BY 
                CASE response_range
                    WHEN '0-1 jam' THEN 1
                    WHEN '1-6 jam' THEN 2
                    WHEN '6-12 jam' THEN 3
                    WHEN '12-24 jam' THEN 4
                    WHEN '24-48 jam' THEN 5
                    WHEN '48-72 jam' THEN 6
                    ELSE 7
                END";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $responseTimeDist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get message types
    $sql = "SELECT 
                mt.jenis_pesan,
                COUNT(m.id) as total,
                SUM(CASE WHEN m.is_external = 1 THEN 1 ELSE 0 END) as external_count,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_count,
                AVG(CASE WHEN m.tanggal_respon IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL END) as avg_response_time
            FROM message_types mt
            LEFT JOIN messages m ON mt.id = m.jenis_pesan_id 
                AND m.created_at BETWEEN ? AND ?
            GROUP BY mt.id, mt.jenis_pesan
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $messageTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teacher performance
    $sql = "SELECT 
                u.nama_lengkap,
                COUNT(m.id) as messages_handled,
                COUNT(mr.id) as responses_given,
                AVG(TIMESTAMPDIFF(HOUR, m.created_at, COALESCE(mr.created_at, m.tanggal_respon, m.created_at))) as avg_response_time,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_messages,
                ROUND(AVG(CASE 
                    WHEN m.tanggal_respon IS NOT NULL 
                    AND TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) <= COALESCE(mt.response_deadline_hours, 72)
                    THEN 1 ELSE 0 END) * 100, 1) as sla_compliance
            FROM users u
            LEFT JOIN messages m ON u.id = m.responder_id 
                AND m.created_at BETWEEN ? AND ?
            LEFT JOIN message_responses mr ON u.id = mr.responder_id 
                AND mr.created_at BETWEEN ? AND ?
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE u.user_type LIKE 'Guru_%'
            GROUP BY u.id, u.nama_lengkap
            HAVING messages_handled > 0
            ORDER BY resolved_messages DESC, messages_handled DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $range['start'], $range['end'],
        $range['start'], $range['end']
    ]);
    $teacherPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user growth
    $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users,
                SUM(CASE WHEN user_type LIKE 'Guru_%' THEN 1 ELSE 0 END) as new_teachers,
                SUM(CASE WHEN user_type = 'Siswa' THEN 1 ELSE 0 END) as new_students
            FROM users 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 30";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get external senders
    $sql = "SELECT 
                COUNT(DISTINCT es.id) as total_senders,
                COUNT(m.id) as total_messages,
                AVG(TIMESTAMPDIFF(HOUR, m.created_at, COALESCE(m.tanggal_respon, m.created_at))) as avg_response_time,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_messages,
                COUNT(DISTINCT m.jenis_pesan_id) as message_types_used
            FROM external_senders es
            LEFT JOIN messages m ON es.id = m.external_sender_id 
                AND m.created_at BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $externalSenders = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get SLA compliance
    $sql = "SELECT 
                COUNT(*) as total_resolved,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) <= 
                        COALESCE(mt.response_deadline_hours, 72) 
                    THEN 1 ELSE 0 
                END) as within_sla,
                AVG(CASE 
                    WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) > 
                        COALESCE(mt.response_deadline_hours, 72) 
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL 
                END) as avg_overdue_hours,
                COUNT(DISTINCT m.responder_id) as responders_count
            FROM messages m
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE m.created_at BETWEEN ? AND ?
                AND m.status IN ('Disetujui', 'Ditolak', 'Selesai')
                AND m.tanggal_respon IS NOT NULL";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$range['start'], $range['end']]);
    $sla = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $complianceRate = ($sla['total_resolved'] ?? 0) > 0 
        ? round(($sla['within_sla'] ?? 0) / ($sla['total_resolved'] ?? 1) * 100, 1)
        : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'overview' => $overview,
            'status_stats' => $statusStats,
            'priority_stats' => $priorityStats,
            'daily_trends' => [
                'daily' => $dailyTrends,
                'moving_avg' => $movingAvg
            ],
            'response_time' => $responseTimeDist,
            'message_types' => $messageTypes,
            'teacher_performance' => $teacherPerformance,
            'user_growth' => $userGrowth,
            'external_senders' => $externalSenders,
            'sla_compliance' => [
                'total_resolved' => (int)($sla['total_resolved'] ?? 0),
                'within_sla' => (int)($sla['within_sla'] ?? 0),
                'compliance_rate' => $complianceRate,
                'avg_overdue_hours' => round((float)($sla['avg_overdue_hours'] ?? 0), 1),
                'responders_count' => (int)($sla['responders_count'] ?? 0)
            ]
        ]
    ]);
}