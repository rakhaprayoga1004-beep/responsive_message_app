<?php
/**
 * API Dashboard Endpoint
 * File: api/dashboard.php
 * Method: GET
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token diperlukan']);
    exit;
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $db = Database::getInstance()->getConnection();
    
    // Verify user by token
    $stmt = $db->prepare("SELECT id, user_type, nama_lengkap FROM users WHERE api_token = :token AND is_active = 1");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Token tidak valid');
    }
    
    $guruId = $user['id'];
    $guruType = $user['user_type'];
    $guruName = $user['nama_lengkap'];
    
    // Map guru type to message type
    $typeMap = [
        'Guru_BK' => 'Konsultasi/Konseling',
        'Guru_Humas' => 'Kehumasan',
        'Guru_Kurikulum' => 'Kurikulum',
        'Guru_Kesiswaan' => 'Kesiswaan',
        'Guru_Sarana' => 'Sarana Prasarana',
        'Guru' => 'Umum',
        'Admin' => 'Administrasi',
        'Wakil_Kepala' => 'Manajemen',
        'Kepala_Sekolah' => 'Kepemimpinan'
    ];
    
    $assignedType = $typeMap[$guruType] ?? '';
    
    // Get time filter
    $timeFilter = $_GET['time'] ?? 'all';
    $startDate = '1970-01-01';
    
    switch ($timeFilter) {
        case '7days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $startDate = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
    }
    
    // Get message type ID
    $messageTypeId = 0;
    $typeStmt = $db->prepare("SELECT id FROM message_types WHERE jenis_pesan = :jenis_pesan");
    $typeStmt->execute([':jenis_pesan' => $assignedType]);
    $messageType = $typeStmt->fetch();
    if ($messageType) {
        $messageTypeId = $messageType['id'];
    }
    
    // Get statistics
    $stats = [
        'total_assigned' => 0,
        'pending' => 0,
        'dibaca' => 0,
        'diproses' => 0,
        'disetujui' => 0,
        'ditolak' => 0,
        'selesai' => 0,
        'expired' => 0,
        'avg_response_time' => 0,
        'total_responses' => 0
    ];
    
    // Query for messages responded by this guru
    $sql1 = "
        SELECT m.id, m.status, m.created_at, mr.created_at as response_date
        FROM messages m
        INNER JOIN message_responses mr ON m.id = mr.message_id AND mr.responder_id = :guru_id
        WHERE 1=1
    ";
    
    $params1 = [':guru_id' => $guruId];
    if ($timeFilter !== 'all') {
        $sql1 .= " AND m.created_at >= :start_date";
        $params1[':start_date'] = $startDate;
    }
    
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute($params1);
    $messagesFromResponses = $stmt1->fetchAll();
    
    // Query for messages by type
    $sql2 = "
        SELECT m.id, m.status, m.created_at, NULL as response_date
        FROM messages m
        WHERE m.jenis_pesan_id = :type_id
    ";
    
    $params2 = [':type_id' => $messageTypeId];
    if ($timeFilter !== 'all') {
        $sql2 .= " AND m.created_at >= :start_date";
        $params2[':start_date'] = $startDate;
    }
    
    $messagesFromType = [];
    if ($messageTypeId > 0) {
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute($params2);
        $messagesFromType = $stmt2->fetchAll();
    }
    
    // Combine and deduplicate
    $allMessagesById = [];
    foreach ($messagesFromResponses as $msg) {
        $allMessagesById[$msg['id']] = $msg;
    }
    foreach ($messagesFromType as $msg) {
        if (!isset($allMessagesById[$msg['id']])) {
            $allMessagesById[$msg['id']] = $msg;
        }
    }
    $allMessages = array_values($allMessagesById);
    
    // Calculate statistics
    $stats['total_assigned'] = count($allMessages);
    
    foreach ($allMessages as $msg) {
        switch ($msg['status']) {
            case 'Pending': $stats['pending']++; break;
            case 'Dibaca': $stats['dibaca']++; break;
            case 'Diproses': $stats['diproses']++; break;
            case 'Disetujui': $stats['disetujui']++; break;
            case 'Ditolak': $stats['ditolak']++; break;
            case 'Selesai': $stats['selesai']++; break;
        }
        
        if (in_array($msg['status'], ['Pending', 'Dibaca', 'Diproses'])) {
            $created = strtotime($msg['created_at']);
            if ((time() - $created) > 72 * 3600) {
                $stats['expired']++;
            }
        }
        
        if (!empty($msg['response_date'])) {
            $stats['total_responses']++;
            $responseTime = (strtotime($msg['response_date']) - strtotime($msg['created_at'])) / 3600;
            $stats['avg_response_time'] += $responseTime;
        }
    }
    
    if ($stats['total_responses'] > 0) {
        $stats['avg_response_time'] = round($stats['avg_response_time'] / $stats['total_responses'], 1);
    }
    
    // Status distribution
    $statusDistribution = [];
    $statusColors = [
        'Pending' => '#ffc107',
        'Dibaca' => '#17a2b8',
        'Diproses' => '#0d6efd',
        'Disetujui' => '#198754',
        'Ditolak' => '#dc3545',
        'Selesai' => '#6c757d'
    ];
    
    $allStatuses = ['Pending', 'Dibaca', 'Diproses', 'Disetujui', 'Ditolak', 'Selesai'];
    $totalStatus = max(1, $stats['total_assigned']);
    
    foreach ($allStatuses as $status) {
        $count = $stats[strtolower($status)] ?? 0;
        $statusDistribution[] = [
            'status' => $status,
            'count' => $count,
            'percentage' => round(($count / $totalStatus) * 100, 1),
            'color' => $statusColors[$status] ?? '#6c757d'
        ];
    }
    
    // Get trends data
    $trendsSql = "
        SELECT 
            DATE(m.created_at) as date,
            SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN m.status = 'Dibaca' THEN 1 ELSE 0 END) as dibaca_count,
            SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END) as diproses_count,
            SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END) as disetujui_count,
            SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END) as ditolak_count,
            SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END) as selesai_count
        FROM messages m
        WHERE (m.jenis_pesan_id = :type_id OR EXISTS (SELECT 1 FROM message_responses mr WHERE mr.message_id = m.id AND mr.responder_id = :guru_id))
    ";
    
    $trendsParams = [':guru_id' => $guruId, ':type_id' => $messageTypeId];
    if ($timeFilter !== 'all') {
        $trendsSql .= " AND m.created_at >= :start_date";
        $trendsParams[':start_date'] = $startDate;
    }
    $trendsSql .= " GROUP BY DATE(m.created_at) ORDER BY date ASC LIMIT 15";
    
    $trendsStmt = $db->prepare($trendsSql);
    $trendsStmt->execute($trendsParams);
    $trends = $trendsStmt->fetchAll();
    
    $chartLabels = [];
    $chartPendingData = [];
    $chartDibacaData = [];
    $chartDiprosesData = [];
    $chartDisetujuiData = [];
    $chartDitolakData = [];
    $chartSelesaiData = [];
    
    foreach ($trends as $trend) {
        $chartLabels[] = date('d M', strtotime($trend['date']));
        $chartPendingData[] = (int)$trend['pending_count'];
        $chartDibacaData[] = (int)$trend['dibaca_count'];
        $chartDiprosesData[] = (int)$trend['diproses_count'];
        $chartDisetujuiData[] = (int)$trend['disetujui_count'];
        $chartDitolakData[] = (int)$trend['ditolak_count'];
        $chartSelesaiData[] = (int)$trend['selesai_count'];
    }
    
    // Get recent activity
    $recentSql = "
        SELECT 
            m.id as message_id,
            m.isi_pesan as content,
            m.status,
            m.created_at as message_date,
            COALESCE(u.nama_lengkap, m.pengirim_nama, 'Pengirim Tidak Dikenal') as sender_name,
            COALESCE(u.user_type, 'Internal') as sender_type,
            COALESCE(u.nis_nip, m.pengirim_nis_nip, '-') as sender_info,
            mr.catatan_respon as response_content,
            (SELECT COUNT(*) FROM wakepsek_reviews WHERE message_id = m.id) as review_count
        FROM messages m
        LEFT JOIN users u ON m.pengirim_id = u.id
        LEFT JOIN message_responses mr ON m.id = mr.message_id AND mr.responder_id = :guru_id
        WHERE (m.jenis_pesan_id = :type_id OR EXISTS (SELECT 1 FROM message_responses mr2 WHERE mr2.message_id = m.id AND mr2.responder_id = :guru_id2))
    ";
    
    $recentParams = [
        ':guru_id' => $guruId,
        ':guru_id2' => $guruId,
        ':type_id' => $messageTypeId
    ];
    if ($timeFilter !== 'all') {
        $recentSql .= " AND m.created_at >= :start_date";
        $recentParams[':start_date'] = $startDate;
    }
    $recentSql .= " ORDER BY m.created_at DESC LIMIT 20";
    
    $recentStmt = $db->prepare($recentSql);
    $recentStmt->execute($recentParams);
    $recentActivity = $recentStmt->fetchAll();
    
    $response['success'] = true;
    $response['data'] = [
        'user' => [
            'id' => $guruId,
            'name' => $guruName,
            'type' => $guruType,
            'assigned_type' => $assignedType
        ],
        'stats' => $stats,
        'status_distribution' => $statusDistribution,
        'trends' => [
            'labels' => $chartLabels,
            'pending' => $chartPendingData,
            'dibaca' => $chartDibacaData,
            'diproses' => $chartDiprosesData,
            'disetujui' => $chartDisetujuiData,
            'ditolak' => $chartDitolakData,
            'selesai' => $chartSelesaiData
        ],
        'recent_activity' => $recentActivity,
        'time_filter' => $timeFilter
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);