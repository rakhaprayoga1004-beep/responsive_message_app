<?php
/**
 * API untuk detail user dengan data lengkap
 * Lokasi: /responsive-message-app/api/user_detail.php
 * Version: 7.0 - FINAL with complete data matching users.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/api_error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

$configFile = dirname(__DIR__) . '/config/config.php';
$databaseFile = dirname(__DIR__) . '/config/database.php';

if (!file_exists($configFile)) {
    sendJsonResponse(['success' => false, 'message' => 'Config file not found'], 500);
}

if (!file_exists($databaseFile)) {
    sendJsonResponse(['success' => false, 'message' => 'Database file not found'], 500);
}

try {
    require_once $configFile;
    require_once $databaseFile;
} catch (Exception $e) {
    sendJsonResponse(['success' => false, 'message' => 'Error loading config: ' . $e->getMessage()], 500);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    } else {
        session_name('PHPSESSID');
    }
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized - Please login first'], 401);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    }
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID user tidak valid'], 400);
    }
    
    // ==========================================================================
    // 1. GET USER DETAILS - SAMA PERSIS DENGAN users.php
    // ==========================================================================
    $sql = "SELECT 
                id, 
                username, 
                user_type, 
                nama_lengkap, 
                email,
                is_active,
                nis_nip,
                phone_number,
                avatar as foto,
                privilege_level,
                kelas,
                jurusan,
                mata_pelajaran,
                created_at,
                updated_at,
                last_login
            FROM users 
            WHERE id = :id
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendJsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
    }
    
    $isResponder = in_array($user['user_type'], [
        'Admin', 'Guru_BK', 'Guru_Humas', 'Guru_Kurikulum', 
        'Guru_Kesiswaan', 'Guru_Sarana', 'Wakil_Kepala', 'Kepala_Sekolah'
    ]);
    
    // ==========================================================================
    // 2. SENT MESSAGES STATISTICS
    // ==========================================================================
    $sentStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected
            FROM messages 
            WHERE pengirim_id = :user_id
        ");
        $stmt->execute([':user_id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $sentStats = [
                'total' => (int)$result['total'],
                'pending' => (int)$result['pending'],
                'approved' => (int)$result['approved'],
                'rejected' => (int)$result['rejected']
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting sent stats: " . $e->getMessage());
    }
    
    // ==========================================================================
    // 3. GET ALL MESSAGES WITH DETAILS - SAMA PERSIS DENGAN users.php
    // ==========================================================================
    $allMessagesList = [];
    try {
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.isi_pesan,
                m.status,
                m.created_at as tanggal_pesan,
                mt.jenis_pesan
            FROM messages m
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE m.pengirim_id = :user_id
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':user_id' => $id]);
        $messagesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $counter = 1;
        foreach ($messagesResult as $msg) {
            $allMessagesList[] = [
                'id' => (int)$msg['id'],
                'no' => $counter++,
                'jenis_pesan' => $msg['jenis_pesan'] ?? 'Umum',
                'isi_pesan' => $msg['isi_pesan'],
                'status' => $msg['status'] ?? 'Pending',
                'tanggal_pesan' => $msg['tanggal_pesan'],
                'formatted_date' => date('d/m/Y H:i', strtotime($msg['tanggal_pesan']))
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting messages: " . $e->getMessage());
    }
    
    // ==========================================================================
    // 4. FORMAT RESPONSE - SAMA PERSIS DENGAN users.php
    // ==========================================================================
    
    $userTypeLabels = [
        'Siswa' => 'Siswa',
        'Guru' => 'Guru',
        'Guru_BK' => 'Guru Bimbingan Konseling',
        'Guru_Humas' => 'Guru Hubungan Masyarakat',
        'Guru_Kurikulum' => 'Guru Kurikulum',
        'Guru_Kesiswaan' => 'Guru Kesiswaan',
        'Guru_Sarana' => 'Guru Sarana Prasarana',
        'Orang_Tua' => 'Orang Tua/Wali',
        'Admin' => 'Administrator',
        'Wakil_Kepala' => 'Wakil Kepala Sekolah',
        'Kepala_Sekolah' => 'Kepala Sekolah',
        'External' => 'Eksternal'
    ];
    
    $privilegeLabels = [
        'Full_Access' => 'Akses Penuh',
        'Limited_Lv1' => 'Akses Terbatas Level 1',
        'Limited_Lv2' => 'Akses Terbatas Level 2',
        'Limited_Lv3' => 'Akses Terbatas Level 3'
    ];
    
    function formatDateTime($dateTime) {
        if (empty($dateTime)) return null;
        return date('d F Y H:i', strtotime($dateTime));
    }
    
    $statusColors = [
        'Pending' => 'warning',
        'Diproses' => 'info',
        'Disetujui' => 'success',
        'Ditolak' => 'danger',
        'Selesai' => 'secondary',
        'Dibaca' => 'primary'
    ];
    
    // Format user data - SAMA PERSIS DENGAN FORMAT DI users.php
    $formattedUser = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'nama_lengkap' => $user['nama_lengkap'],
        'email' => $user['email'],
        'user_type' => $user['user_type'],
        'user_type_label' => $userTypeLabels[$user['user_type']] ?? $user['user_type'],
        'status' => $user['is_active'] == 1 ? 'aktif' : 'nonaktif',
        'is_active' => $user['is_active'] == 1,
        'nis_nip' => $user['nis_nip'] ?? '',
        'no_telp' => $user['phone_number'] ?? '',
        'foto' => $user['foto'] ?? 'default-avatar.png',
        'privilege_level' => $user['privilege_level'], // LANGSUNG DARI DATABASE
        'kelas' => $user['kelas'] ?? '',
        'jurusan' => $user['jurusan'] ?? '',
        'mata_pelajaran' => $user['mata_pelajaran'] ?? '',
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at'],
        'last_login' => $user['last_login']
    ];
    
    // Build final response
    $responseData = [
        'success' => true,
        'data' => $formattedUser,
        'stats_cards' => [
            'total' => $sentStats['total'],
            'pending' => $sentStats['pending'],
            'approved' => $sentStats['approved'],
            'rejected' => $sentStats['rejected']
        ],
        'messages' => $allMessagesList,
        'status_colors' => $statusColors,
        'is_responder' => $isResponder
    ];
    
    // Log untuk debugging
    error_log("=== User Detail API Response ===");
    error_log("User ID: $id");
    error_log("Privilege Level: " . ($user['privilege_level'] ?? 'NULL'));
    error_log("Messages Count: " . count($allMessagesList));
    error_log("Stats: total={$sentStats['total']}, pending={$sentStats['pending']}, approved={$sentStats['approved']}, rejected={$sentStats['rejected']}");
    error_log("================================");
    
    sendJsonResponse($responseData);
    
} catch (Exception $e) {
    error_log("User detail error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}