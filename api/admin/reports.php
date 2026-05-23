<?php
/**
 * Admin Reports API - COMPLETE VERSION WITH CORRECT COLUMN NAMES
 * File: api/admin/reports.php
 */

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Header untuk CORS dan JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load konfigurasi
require_once '../../config/config.php';
require_once '../../config/database.php';

// ============================================
// DEBUG LOGGING
// ============================================
error_log("=== REPORTS API CALLED ===");

// ============================================
// AUTHENTICATION
// ============================================
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

error_log("Auth Header: " . $authHeader);

$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
        error_log("User authenticated: " . $userData['username']);
    } else {
        error_log("Token invalid or expired");
    }
}

if (!$userData || !in_array($userData['user_type'], ['Admin', 'Super_Admin'])) {
    error_log("Unauthorized - user_type: " . ($userData['user_type'] ?? 'null'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ============================================
// GET PARAMETERS
// ============================================
$reportType = $_GET['report_type'] ?? 'dashboard';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userTypeFilter = $_GET['user_type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$messageTypeFilter = $_GET['message_type'] ?? 'all';
$groupBy = $_GET['group_by'] ?? 'day';
$export = $_GET['export'] ?? '';

// Validate dates
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all data for dashboard
    $data = [
        'system_overview' => getSystemOverview($db, $startDate, $endDate),
        'message_status' => getMessageStatusStats($db, $startDate, $endDate),
        'message_priority' => getMessagePriorityStats($db, $startDate, $endDate),
        'daily_trends' => getDailyTrends($db, $startDate, $endDate),
        'response_time_distribution' => getResponseTimeDistribution($db, $startDate, $endDate),
        'user_growth' => getUserGrowth($db, $startDate, $endDate),
        'message_type_stats' => getMessageTypeStats($db, $startDate, $endDate),
        'teacher_performance' => getTeacherPerformance($db, $startDate, $endDate),
        'external_senders' => getExternalSendersStats($db, $startDate, $endDate),
        'sla_compliance' => getSLACompliance($db, $startDate, $endDate),
        'details' => getMessageDetails($db, $startDate, $endDate, $userTypeFilter, $statusFilter, $priorityFilter, $messageTypeFilter)
    ];
    
    error_log("Details count: " . count($data['details']));
    
    // Handle export if requested
    if ($export && !empty($data)) {
        exportReport($data, $export, $reportType, $startDate, $endDate);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_type' => $userTypeFilter,
            'status' => $statusFilter,
            'priority' => $priorityFilter,
            'message_type' => $messageTypeFilter,
            'group_by' => $groupBy
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Reports API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ============================================
// GET MESSAGE DETAILS - DIPERBAIKI
// ============================================
function getMessageDetails($db, $startDate, $endDate, $userTypeFilter, $statusFilter, $priorityFilter, $messageTypeFilter) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    // Build WHERE conditions based on filters
    $conditions = ["m.created_at BETWEEN ? AND ?"];
    $params = [$startDateTime, $endDateTime];
    
    // Filter by user type - menggunakan is_external
    if ($userTypeFilter !== 'all') {
        if ($userTypeFilter === 'External') {
            $conditions[] = "m.is_external = 1";
        } else {
            // Untuk internal users, join dengan tabel users
            $conditions[] = "m.is_external = 0 AND u.user_type = ?";
            $params[] = $userTypeFilter;
        }
    }
    
    if ($statusFilter !== 'all') {
        $conditions[] = "m.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($priorityFilter !== 'all') {
        $conditions[] = "m.priority = ?";
        $params[] = $priorityFilter;
    }
    
    if ($messageTypeFilter !== 'all' && $messageTypeFilter !== '' && $messageTypeFilter !== 'null') {
        $conditions[] = "m.jenis_pesan_id = ?";
        $params[] = $messageTypeFilter;
    }
    
    $whereClause = implode(" AND ", $conditions);
    
    $sql = "SELECT 
                m.id,
                m.reference_number,
                m.tanggal_pesan,
                COALESCE(mt.jenis_pesan, 'Lainnya') as jenis_pesan,
                m.pengirim_nama,
                m.isi_pesan,
                m.status,
                m.priority,
                m.tanggal_respon,
                m.created_at,
                m.is_external,
                -- Tipe pengirim berdasarkan is_external
                CASE 
                    WHEN m.is_external = 1 THEN 'External'
                    ELSE COALESCE(u.user_type, 'Unknown')
                END as pengirim_tipe,
                -- Response time dalam format readable
                CASE 
                    WHEN m.tanggal_respon IS NOT NULL AND m.tanggal_respon != '0000-00-00 00:00:00'
                    THEN TIMESTAMPDIFF(HOUR, m.tanggal_pesan, m.tanggal_respon)
                    ELSE NULL 
                END as response_hours,
                -- Ringkasan pesan (100 karakter pertama)
                LEFT(REPLACE(REPLACE(m.isi_pesan, '\n', ' '), '\r', ' '), 100) as isi_pesan_ringkas
            FROM messages m
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            LEFT JOIN users u ON m.pengirim_id = u.id
            WHERE $whereClause
            ORDER BY m.tanggal_pesan DESC
            LIMIT 500";
    
    error_log("Details SQL: " . $sql);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response time
    foreach ($results as &$row) {
        if ($row['response_hours'] !== null && $row['response_hours'] > 0) {
            if ($row['response_hours'] < 24) {
                $row['response_time'] = $row['response_hours'] . ' jam';
            } elseif ($row['response_hours'] < 168) {
                $days = floor($row['response_hours'] / 24);
                $hours = $row['response_hours'] % 24;
                if ($hours > 0) {
                    $row['response_time'] = $days . ' hari ' . $hours . ' jam';
                } else {
                    $row['response_time'] = $days . ' hari';
                }
            } else {
                $weeks = floor($row['response_hours'] / 168);
                $row['response_time'] = $weeks . ' minggu';
            }
        } else {
            $row['response_time'] = '-';
        }
        
        // Format response date for display
        if ($row['tanggal_respon'] && $row['tanggal_respon'] != '0000-00-00 00:00:00') {
            $row['response_date'] = $row['tanggal_respon'];
        } else {
            $row['response_date'] = '-';
        }
    }
    
    error_log("Details found: " . count($results));
    
    return $results;
}

// ============================================
// EXISTING DATA FUNCTIONS - DIPERBAIKI
// ============================================

function getSystemOverview($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_external = 1 THEN 1 ELSE 0 END) as external_messages,
                COUNT(DISTINCT CASE WHEN is_external = 1 THEN external_sender_id END) as external_senders,
                AVG(CASE WHEN tanggal_respon IS NOT NULL AND tanggal_respon != '0000-00-00 00:00:00'
                    THEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) 
                    ELSE NULL END) as avg_response_time,
                SUM(CASE WHEN status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_messages
            FROM messages 
            WHERE created_at BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total_messages' => (int)($result['total_messages'] ?? 0),
        'external_messages' => (int)($result['external_messages'] ?? 0),
        'external_senders' => (int)($result['external_senders'] ?? 0),
        'avg_response_time' => round((float)($result['avg_response_time'] ?? 0), 1),
        'resolved_messages' => (int)($result['resolved_messages'] ?? 0),
        'trend_percentage' => 0
    ];
}

function getMessageStatusStats($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                COALESCE(status, 'Unknown') as status,
                COUNT(*) as total
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
            ORDER BY 
                CASE status
                    WHEN 'Pending' THEN 1
                    WHEN 'Dibaca' THEN 2
                    WHEN 'Diproses' THEN 3
                    WHEN 'Disetujui' THEN 4
                    WHEN 'Ditolak' THEN 5
                    WHEN 'Selesai' THEN 6
                    ELSE 7
                END";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['color'] = getStatusColor($row['status']);
    }
    
    return $results;
}

function getMessagePriorityStats($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                COALESCE(priority, 'Normal') as priority,
                COUNT(*) as total
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY priority
            ORDER BY 
                CASE priority
                    WHEN 'Urgent' THEN 1
                    WHEN 'High' THEN 2
                    WHEN 'Medium' THEN 3
                    WHEN 'Low' THEN 4
                    ELSE 5
                END";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['color'] = getPriorityColor($row['priority']);
    }
    
    return $results;
}

function getDailyTrends($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_external = 1 THEN 1 ELSE 0 END) as external_messages,
                SUM(CASE WHEN status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected
            FROM messages 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 30";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate 7-day moving average
    $movingAvg = [];
    for ($i = 0; $i < count($results); $i++) {
        $sum = 0;
        $count = 0;
        for ($j = max(0, $i - 6); $j <= $i; $j++) {
            $sum += (int)($results[$j]['total_messages'] ?? 0);
            $count++;
        }
        $movingAvg[] = $count > 0 ? round($sum / $count, 1) : 0;
    }
    
    foreach ($results as $i => &$row) {
        $row['moving_avg'] = $movingAvg[$i] ?? 0;
    }
    
    return $results;
}

function getResponseTimeDistribution($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
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
                AND tanggal_respon != '0000-00-00 00:00:00'
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
    $stmt->execute([$startDateTime, $endDateTime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserGrowth($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users,
                SUM(CASE WHEN user_type LIKE 'Guru_%' THEN 1 ELSE 0 END) as new_teachers,
                SUM(CASE WHEN user_type = 'Siswa' THEN 1 ELSE 0 END) as new_students,
                SUM(CASE WHEN user_type = 'External' THEN 1 ELSE 0 END) as new_external
            FROM users 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 30";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMessageTypeStats($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                mt.jenis_pesan,
                COUNT(m.id) as total,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as disetujui,
                SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as ditolak,
                SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN m.is_external = 1 THEN 1 ELSE 0 END) as external_count,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_count,
                AVG(CASE WHEN m.tanggal_respon IS NOT NULL AND m.tanggal_respon != '0000-00-00 00:00:00'
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
    $stmt->execute([$startDateTime, $endDateTime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTeacherPerformance($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                u.id,
                u.nama_lengkap,
                u.user_type,
                COUNT(DISTINCT m.id) as messages_handled,
                COUNT(DISTINCT mr.id) as responses_given,
                AVG(CASE WHEN m.tanggal_respon IS NOT NULL AND m.tanggal_respon != '0000-00-00 00:00:00'
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL END) as avg_response_time,
                SUM(CASE WHEN m.status IN ('Disetujui', 'Selesai') THEN 1 ELSE 0 END) as resolved_messages,
                COUNT(DISTINCT CASE WHEN m.is_external = 1 THEN m.id END) as external_handled,
                ROUND(AVG(CASE 
                    WHEN m.tanggal_respon IS NOT NULL 
                    AND TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) <= COALESCE(mt.response_deadline_hours, 72)
                    THEN 100 ELSE 0 END), 1) as sla_compliance
            FROM users u
            LEFT JOIN messages m ON u.id = m.responder_id 
                AND m.created_at BETWEEN ? AND ?
            LEFT JOIN message_responses mr ON u.id = mr.responder_id 
                AND mr.created_at BETWEEN ? AND ?
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE u.user_type LIKE 'Guru_%'
            GROUP BY u.id, u.nama_lengkap, u.user_type
            HAVING messages_handled > 0
            ORDER BY resolved_messages DESC, messages_handled DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime, $startDateTime, $endDateTime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExternalSendersStats($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                COUNT(DISTINCT es.id) as total_senders,
                COUNT(m.id) as total_messages,
                AVG(CASE WHEN m.tanggal_respon IS NOT NULL AND m.tanggal_respon != '0000-00-00 00:00:00'
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL END) as avg_response_time,
                COUNT(DISTINCT m.jenis_pesan_id) as message_types_used
            FROM external_senders es
            LEFT JOIN messages m ON es.id = m.external_sender_id 
                AND m.created_at BETWEEN ? AND ?
            WHERE es.created_at BETWEEN ? AND ?
                OR m.id IS NOT NULL";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime, $startDateTime, $endDateTime]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total_senders' => (int)($result['total_senders'] ?? 0),
        'total_messages' => (int)($result['total_messages'] ?? 0),
        'avg_response_time' => round((float)($result['avg_response_time'] ?? 0), 1),
        'message_types_used' => (int)($result['message_types_used'] ?? 0)
    ];
}

function getSLACompliance($db, $startDate, $endDate) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    $sql = "SELECT 
                COUNT(*) as total_resolved,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) <= 
                        COALESCE(mt.response_deadline_hours, 72) 
                    THEN 1 ELSE 0 
                END) as within_sla,
                COUNT(DISTINCT m.responder_id) as responders_count
            FROM messages m
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE m.created_at BETWEEN ? AND ?
                AND m.status IN ('Disetujui', 'Ditolak', 'Selesai')
                AND m.tanggal_respon IS NOT NULL
                AND m.tanggal_respon != '0000-00-00 00:00:00'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDateTime, $endDateTime]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $complianceRate = ($result['total_resolved'] ?? 0) > 0 
        ? round(($result['within_sla'] ?? 0) / ($result['total_resolved'] ?? 1) * 100, 1)
        : 0;
    
    return [
        'total_resolved' => (int)($result['total_resolved'] ?? 0),
        'within_sla' => (int)($result['within_sla'] ?? 0),
        'compliance_rate' => $complianceRate,
        'responders_count' => (int)($result['responders_count'] ?? 0),
        'avg_response_time' => 0
    ];
}

function getStatusColor($status) {
    return match($status) {
        'Disetujui' => '#28a745',
        'Ditolak' => '#dc3545',
        'Pending' => '#ffc107',
        'Diproses' => '#0d6efd',
        'Dibaca' => '#17a2b8',
        'Selesai' => '#6c757d',
        default => '#6c757d'
    };
}

function getPriorityColor($priority) {
    return match($priority) {
        'Urgent' => '#dc3545',
        'High' => '#fd7e14',
        'Medium' => '#ffc107',
        'Low' => '#28a745',
        default => '#6c757d'
    };
}

function exportReport($data, $format, $reportType, $startDate, $endDate) {
    while (ob_get_level()) ob_end_clean();
    
    $filename = 'laporan_' . $reportType . '_' . date('Ymd_His');
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, [APP_NAME . ' - LAPORAN ' . strtoupper($reportType)]);
        fputcsv($output, ["Periode: " . date('d/m/Y', strtotime($startDate)) . " - " . date('d/m/Y', strtotime($endDate))]);
        fputcsv($output, ["Dibuat: " . date('d/m/Y H:i:s')]);
        fputcsv($output, []);
        
        // System overview
        fputcsv($output, ['SYSTEM OVERVIEW']);
        fputcsv($output, ['Total Messages', $data['system_overview']['total_messages']]);
        fputcsv($output, ['External Messages', $data['system_overview']['external_messages']]);
        fputcsv($output, ['External Senders', $data['system_overview']['external_senders']]);
        fputcsv($output, ['Avg Response Time', $data['system_overview']['avg_response_time'] . ' hours']);
        fputcsv($output, ['Resolved', $data['system_overview']['resolved_messages']]);
        fputcsv($output, []);
        
        // Message Status
        fputcsv($output, ['MESSAGE STATUS']);
        fputcsv($output, ['Status', 'Total']);
        foreach ($data['message_status'] as $row) {
            fputcsv($output, [$row['status'], $row['total']]);
        }
        fputcsv($output, []);
        
        // Daily Trends
        fputcsv($output, ['DAILY TRENDS']);
        fputcsv($output, ['Date', 'Total Messages', 'Completed', 'Pending', 'Rejected']);
        foreach ($data['daily_trends'] as $row) {
            fputcsv($output, [$row['date'], $row['total_messages'], $row['completed'] ?? 0, $row['pending'] ?? 0, $row['rejected'] ?? 0]);
        }
        fputcsv($output, []);
        
        // Message Details
        if (!empty($data['details'])) {
            fputcsv($output, ['MESSAGE DETAILS']);
            fputcsv($output, ['ID', 'Ref Number', 'Date', 'Type', 'Sender', 'Sender Type', 'Status', 'Priority', 'Message', 'Response Time']);
            foreach ($data['details'] as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['reference_number'],
                    $row['tanggal_pesan'],
                    $row['jenis_pesan'],
                    $row['pengirim_nama'],
                    $row['pengirim_tipe'],
                    $row['status'],
                    $row['priority'],
                    $row['isi_pesan_ringkas'],
                    $row['response_time']
                ]);
            }
        }
        
        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        
        echo json_encode([
            'title' => APP_NAME . ' - Laporan ' . strtoupper($reportType),
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'generated_at' => date('Y-m-d H:i:s'),
            'data' => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}
?>