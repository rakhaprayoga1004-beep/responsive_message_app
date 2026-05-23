<?php
/**
 * Dashboard API for Wakil Kepala Sekolah and Kepala Sekolah
 * File: api/wakepsek/dashboard.php
 * 
 * Disesuaikan dengan alur dari modules/wakepsek/dashboard.php
 */

// ============================================================================
// ERROR REPORTING & LOGGING
// ============================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'responsive_message_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============================================================================
// FUNGSI VALIDASI TOKEN
// ============================================================================
function validateToken($token) {
    try {
        $decoded = base64_decode($token);
        if ($decoded === false) return false;
        
        $data = json_decode($decoded, true);
        if (!$data) return false;
        
        if (isset($data['exp']) && $data['exp'] < time()) {
            return false;
        }
        
        return $data;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================================
// AUTHENTIKASI
// ============================================================================
$userId = null;
$userType = null;
$userNama = null;

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $tokenData = validateToken($token);
    if ($tokenData && isset($tokenData['user_id']) && isset($tokenData['user_type'])) {
        $userId = (int)$tokenData['user_id'];
        $userType = $tokenData['user_type'];
        $userNama = $tokenData['username'] ?? 'User';
    }
}

// Fallback: cek session
if ($userId === null && session_status() === PHP_SESSION_NONE) {
    session_start();
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
        $userId = $_SESSION['user_id'];
        $userType = $_SESSION['user_type'];
        $userNama = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User';
    }
}

if ($userId === null || $userType === null) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'debug' => 'No valid token found']);
    exit();
}

$allowedTypes = ['Wakil_Kepala', 'Kepala_Sekolah'];
if (!in_array($userType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Access denied for user type: ' . $userType]);
    exit();
}

// ============================================================================
// KONEKSI DATABASE
// ============================================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $db = new PDO($dsn, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// ============================================================================
// HANDLE POST (Submit Review)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'submit_review') {
        $messageId = (int)($input['message_id'] ?? 0);
        $catatan = trim($input['catatan'] ?? '');
        
        if ($messageId <= 0 || empty($catatan)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        
        // Cek apakah sudah di-review
        $tableName = ($userType === 'Kepala_Sekolah') ? 'kepsek_reviews' : 'wakepsek_reviews';
        $checkStmt = $db->prepare("SELECT id FROM $tableName WHERE message_id = ? AND reviewer_id = ?");
        $checkStmt->execute([$messageId, $userId]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Already reviewed']);
            exit();
        }
        
        // Simpan review
        $insertStmt = $db->prepare("INSERT INTO $tableName (message_id, reviewer_id, catatan, created_at) VALUES (?, ?, ?, NOW())");
        
        if ($insertStmt->execute([$messageId, $userId, $catatan])) {
            echo json_encode(['success' => true, 'message' => 'Review saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save review']);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// ============================================================================
// PARAMETER FILTER
// ============================================================================
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? 'all';
$guruFilter = $_GET['guru'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// ============================================================================
// AMBIL DAFTAR RESPONDER TYPE DARI MESSAGE_TYPES
// ============================================================================
$responderTypes = [];
$responderTypeLabels = [
    'Guru_BK' => 'Bimbingan Konseling',
    'Guru_Kesiswaan' => 'Kesiswaan',
    'Guru_Humas' => 'Hubungan Masyarakat',
    'Guru_Kurikulum' => 'Kurikulum',
    'Guru_Sarana' => 'Sarana Prasarana',
    'Guru' => 'Guru Umum'
];

try {
    $typeStmt = $db->query("
        SELECT DISTINCT responder_type 
        FROM message_types 
        WHERE responder_type IS NOT NULL 
        AND responder_type != ''
        ORDER BY responder_type
    ");
    $responderTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error loading responder types: " . $e->getMessage());
}

// ============================================================================
// AMBIL DAFTAR GURU RESPONDER
// ============================================================================
$guruList = [];

try {
    if (!empty($responderTypes)) {
        $placeholders = implode(',', array_fill(0, count($responderTypes), '?'));
        
        $guruStmt = $db->prepare("
            SELECT id, nama_lengkap, user_type 
            FROM users 
            WHERE user_type IN ($placeholders)
            AND is_active = 1
            ORDER BY 
                CASE user_type
                    WHEN 'Guru_BK' THEN 1
                    WHEN 'Guru_Kesiswaan' THEN 2
                    WHEN 'Guru_Humas' THEN 3
                    WHEN 'Guru_Kurikulum' THEN 4
                    WHEN 'Guru_Sarana' THEN 5
                    WHEN 'Guru' THEN 6
                    ELSE 7
                END,
                nama_lengkap
        ");
        $guruStmt->execute($responderTypes);
        $guruList = $guruStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $guruStmt = $db->query("
            SELECT id, nama_lengkap, user_type 
            FROM users 
            WHERE user_type IN ('Guru_BK', 'Guru_Humas', 'Guru_Kurikulum', 'Guru_Kesiswaan', 'Guru_Sarana', 'Guru')
            AND is_active = 1
            ORDER BY user_type, nama_lengkap
        ");
        $guruList = $guruStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error loading guru list: " . $e->getMessage());
}

// ============================================================================
// STATISTIK
// ============================================================================
$stats = [
    'total_responded' => 0,
    'pending_review' => 0,
    'reviewed' => 0,
    'avg_response_time' => 0,
    'total_guru' => count($guruList)
];

try {
    if ($userType === 'Wakil_Kepala') {
        // Untuk Wakepsek: Total pesan yang sudah direspons guru
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            INNER JOIN message_responses mr ON m.id = mr.message_id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['total_responded'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Pesan yang sudah direspons guru tapi belum direview wakepsek
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            INNER JOIN message_responses mr ON m.id = mr.message_id
            LEFT JOIN wakepsek_reviews wr ON m.id = wr.message_id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            AND wr.id IS NULL
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['pending_review'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Pesan yang sudah direview oleh Wakepsek
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            INNER JOIN wakepsek_reviews wr ON m.id = wr.message_id
            INNER JOIN users reviewer ON wr.reviewer_id = reviewer.id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            AND reviewer.user_type = 'Wakil_Kepala'
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['reviewed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Rata-rata waktu respons guru
        $stmt = $db->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, m.created_at, mr.created_at)) as avg_time
            FROM messages m
            INNER JOIN message_responses mr ON m.id = mr.message_id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            AND mr.created_at IS NOT NULL
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['avg_response_time'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0, 1);
        
    } else { // Kepala_Sekolah
        // Untuk Kepsek: Total pesan yang sudah direspons guru ATAU sudah direview Wakepsek
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            WHERE (
                EXISTS (SELECT 1 FROM message_responses mr WHERE mr.message_id = m.id)
                OR 
                EXISTS (SELECT 1 FROM wakepsek_reviews wr WHERE wr.message_id = m.id)
            )
            AND DATE(m.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['total_responded'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Pesan yang sudah direspons guru/telah direview Wakepsek tapi belum direview Kepsek
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            WHERE (
                EXISTS (SELECT 1 FROM message_responses mr WHERE mr.message_id = m.id)
                OR 
                EXISTS (SELECT 1 FROM wakepsek_reviews wr WHERE wr.message_id = m.id)
            )
            AND NOT EXISTS (
                SELECT 1 FROM wakepsek_reviews wr2 
                WHERE wr2.message_id = m.id 
                AND wr2.reviewer_id = ?
            )
            AND DATE(m.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $dateFrom, $dateTo]);
        $stats['pending_review'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Pesan yang sudah direview oleh Kepsek
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT m.id) as total
            FROM messages m
            INNER JOIN wakepsek_reviews wr ON m.id = wr.message_id
            WHERE wr.reviewer_id = ?
            AND DATE(m.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $dateFrom, $dateTo]);
        $stats['reviewed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Rata-rata waktu review (guru + wakepsek)
        $stmt = $db->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, m.created_at, wr.created_at)) as avg_time
            FROM messages m
            INNER JOIN wakepsek_reviews wr ON m.id = wr.message_id
            WHERE DATE(m.created_at) BETWEEN ? AND ?
            AND wr.reviewer_id IN (SELECT id FROM users WHERE user_type = 'Wakil_Kepala')
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $stats['avg_response_time'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0, 1);
    }
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}

// ============================================================================
// GURU PERFORMANCE DATA
// ============================================================================
$guruChartData = [];

try {
    $responderConditions = [];
    if (!empty($responderTypes)) {
        foreach ($responderTypes as $type) {
            $responderConditions[] = "u.user_type = '" . addslashes($type) . "'";
        }
    } else {
        $responderConditions[] = "u.user_type IN ('Guru_BK', 'Guru_Humas', 'Guru_Kurikulum', 'Guru_Kesiswaan', 'Guru_Sarana', 'Guru')";
    }
    
    $responderWhere = implode(' OR ', $responderConditions);
    
    $chartStmt = $db->query("
        SELECT 
            u.id,
            u.nama_lengkap,
            u.user_type,
            COUNT(DISTINCT m.id) as total_messages,
            SUM(CASE WHEN m.status = 'Pending' AND m.expired_at > NOW() THEN 1 ELSE 0 END) as pending_messages,
            SUM(CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END) as responded_messages,
            SUM(CASE WHEN m.expired_at < NOW() AND m.status != 'Selesai' THEN 1 ELSE 0 END) as expired_messages,
            AVG(TIMESTAMPDIFF(HOUR, m.created_at, mr.created_at)) as avg_response_hours
        FROM users u
        LEFT JOIN message_responses mr ON u.id = mr.responder_id
        LEFT JOIN messages m ON mr.message_id = m.id
        WHERE ($responderWhere)
        AND u.is_active = 1
        AND (m.created_at BETWEEN '$dateFrom 00:00:00' AND '$dateTo 23:59:59' OR m.created_at IS NULL)
        GROUP BY u.id
        HAVING total_messages > 0
        ORDER BY responded_messages DESC
        LIMIT 15
    ");
    $guruChartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Guru chart error: " . $e->getMessage());
}

// ============================================================================
// MESSAGE TYPE STATS
// ============================================================================
$messageTypeChartData = [];

try {
    $typeChartStmt = $db->prepare("
        SELECT 
            mt.id,
            mt.jenis_pesan,
            mt.responder_type,
            COUNT(DISTINCT m.id) as total_messages,
            SUM(CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END) as responded_messages
        FROM message_types mt
        LEFT JOIN messages m ON mt.id = m.jenis_pesan_id AND DATE(m.created_at) BETWEEN ? AND ?
        LEFT JOIN message_responses mr ON m.id = mr.message_id
        GROUP BY mt.id
        HAVING total_messages > 0
        ORDER BY total_messages DESC
    ");
    $typeChartStmt->execute([$dateFrom, $dateTo]);
    $messageTypeChartData = $typeChartStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Message type error: " . $e->getMessage());
}

// ============================================================================
// DAFTAR MESSAGES
// ============================================================================
$messages = [];
$totalMessages = 0;

try {
    // Query utama berdasarkan user type
    if ($userType === 'Wakil_Kepala') {
        $sql = "
            SELECT 
                m.id,
                m.reference_number,
                m.isi_pesan,
                m.created_at,
                m.status,
                COALESCE(u.nama_lengkap, m.pengirim_nama, 'Unknown') as pengirim_nama_display,
                COALESCE(u.user_type, m.pengirim_tipe, 'External') as pengirim_tipe,
                mt.jenis_pesan as message_type,
                mt.responder_type as expected_responder_type,
                mr.id as response_id,
                mr.responder_id as guru_responder_id,
                guru.nama_lengkap as guru_responder_nama,
                guru.user_type as guru_responder_type,
                mr.catatan_respon as guru_response,
                mr.status as guru_response_status,
                mr.created_at as guru_response_date,
                wr.id as review_id,
                wr.catatan as review_catatan,
                wr.created_at as review_date,
                (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
            FROM messages m
            INNER JOIN message_responses mr ON m.id = mr.message_id
            LEFT JOIN users u ON m.pengirim_id = u.id
            LEFT JOIN external_senders es ON m.external_sender_id = es.id
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            LEFT JOIN users guru ON mr.responder_id = guru.id
            LEFT JOIN wakepsek_reviews wr ON m.id = wr.message_id AND wr.reviewer_id = ?
            WHERE DATE(m.created_at) BETWEEN ? AND ?
        ";
        $params = [$userId, $dateFrom, $dateTo];
    } else {
        $sql = "
            SELECT 
                m.id,
                m.reference_number,
                m.isi_pesan,
                m.created_at,
                m.status,
                COALESCE(u.nama_lengkap, m.pengirim_nama, 'Unknown') as pengirim_nama_display,
                COALESCE(u.user_type, m.pengirim_tipe, 'External') as pengirim_tipe,
                mt.jenis_pesan as message_type,
                mt.responder_type as expected_responder_type,
                mr.id as response_id,
                mr.responder_id as guru_responder_id,
                guru.nama_lengkap as guru_responder_nama,
                guru.user_type as guru_responder_type,
                mr.catatan_respon as guru_response,
                mr.status as guru_response_status,
                mr.created_at as guru_response_date,
                wr_wakepsek.id as wakepsek_review_id,
                wakepsek.nama_lengkap as wakepsek_reviewer_nama,
                wr_wakepsek.catatan as wakepsek_review_catatan,
                wr_wakepsek.created_at as wakepsek_review_date,
                wr_kepsek.id as kepsek_review_id,
                wr_kepsek.catatan as kepsek_review_catatan,
                wr_kepsek.created_at as kepsek_review_date,
                (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
            FROM messages m
            LEFT JOIN message_responses mr ON m.id = mr.message_id
            LEFT JOIN users u ON m.pengirim_id = u.id
            LEFT JOIN external_senders es ON m.external_sender_id = es.id
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            LEFT JOIN users guru ON mr.responder_id = guru.id
            LEFT JOIN wakepsek_reviews wr_wakepsek ON m.id = wr_wakepsek.message_id 
                AND wr_wakepsek.reviewer_id IN (SELECT id FROM users WHERE user_type = 'Wakil_Kepala')
            LEFT JOIN users wakepsek ON wr_wakepsek.reviewer_id = wakepsek.id
            LEFT JOIN wakepsek_reviews wr_kepsek ON m.id = wr_kepsek.message_id AND wr_kepsek.reviewer_id = ?
            WHERE DATE(m.created_at) BETWEEN ? AND ?
        ";
        $params = [$userId, $dateFrom, $dateTo];
    }
    
    // Status filter
    if ($userType === 'Wakil_Kepala') {
        if ($statusFilter === 'pending') {
            $sql .= " AND wr.id IS NULL";
        } elseif ($statusFilter === 'reviewed') {
            $sql .= " AND wr.id IS NOT NULL";
        }
    } else {
        if ($statusFilter === 'pending') {
            $sql .= " AND (wr_wakepsek.id IS NOT NULL AND wr_kepsek.id IS NULL)";
        } elseif ($statusFilter === 'reviewed') {
            $sql .= " AND wr_kepsek.id IS NOT NULL";
        } elseif ($statusFilter === 'completed') {
            $sql .= " AND mr.id IS NOT NULL AND wr_wakepsek.id IS NOT NULL AND wr_kepsek.id IS NOT NULL";
        }
    }
    
    // Guru filter
    if ($guruFilter !== 'all' && !empty($guruFilter)) {
        $sql .= " AND mr.responder_id = ?";
        $params[] = $guruFilter;
    }
    
    // Search filter
    if (!empty($search)) {
        $sql .= " AND (m.isi_pesan LIKE ? OR guru.nama_lengkap LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Count total
    $countSql = preg_replace('/SELECT .*? FROM/', 'SELECT COUNT(DISTINCT m.id) as total FROM', $sql);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalMessages = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Add pagination
    $sql .= " ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Messages error: " . $e->getMessage());
    $messages = [];
}

// ============================================================================
// GURU PERFORMANCE STATS
// ============================================================================
$guruPerformanceStats = [
    'top_performer' => '-',
    'top_performer_count' => 0,
    'fastest_responder' => '-',
    'fastest_time' => 0,
    'total_responded_all' => 0,
    'avg_response_all' => 0,
    'completion_rate' => 0
];

if (!empty($guruChartData)) {
    $guruPerformanceStats['total_responded_all'] = array_sum(array_column($guruChartData, 'responded_messages'));
    $guruPerformanceStats['avg_response_all'] = round(array_sum(array_column($guruChartData, 'avg_response_hours')) / count($guruChartData), 1);
    
    // Top performer
    $topCount = 0;
    $topName = '-';
    foreach ($guruChartData as $guru) {
        if ($guru['responded_messages'] > $topCount) {
            $topCount = $guru['responded_messages'];
            $topName = $guru['nama_lengkap'];
        }
    }
    if ($topCount > 0) {
        $guruPerformanceStats['top_performer'] = $topName;
        $guruPerformanceStats['top_performer_count'] = $topCount;
    }
    
    // Fastest responder
    $fastestTime = 999;
    $fastestName = '-';
    foreach ($guruChartData as $guru) {
        $avgTime = (float)($guru['avg_response_hours'] ?? 0);
        if ($avgTime > 0 && $avgTime < $fastestTime) {
            $fastestTime = $avgTime;
            $fastestName = $guru['nama_lengkap'];
        }
    }
    if ($fastestTime < 999) {
        $guruPerformanceStats['fastest_responder'] = $fastestName;
        $guruPerformanceStats['fastest_time'] = round($fastestTime, 1);
    }
    
    // Completion rate
    $totalMessages = array_sum(array_column($guruChartData, 'total_messages'));
    $totalResponded = array_sum(array_column($guruChartData, 'responded_messages'));
    $guruPerformanceStats['completion_rate'] = $totalMessages > 0 ? round(($totalResponded / $totalMessages) * 100, 1) : 0;
}

// ============================================================================
// RESPONSE
// ============================================================================
$response = [
    'success' => true,
    'data' => [
        'stats' => $stats,
        'guru_stats' => $guruPerformanceStats,
        'guru_performances' => $guruChartData,
        'message_type_stats' => $messageTypeChartData,
        'guru_list' => $guruList,
        'messages' => $messages,
        'total_messages' => $totalMessages,
        'total_pages' => ceil($totalMessages / $perPage),
        'current_page' => $page,
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ]
];

echo json_encode($response);
exit();
?>