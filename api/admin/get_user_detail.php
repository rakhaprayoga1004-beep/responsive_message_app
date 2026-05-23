<?php
/**
 * get_user_detail.php - Get user detail with message statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Ambil token dari header Authorization
$headers = getallheaders();
$token = null;

// Debug: Log semua header
error_log("=== get_user_detail.php DEBUG ===");
error_log("All headers: " . print_r($headers, true));

// Cek berbagai kemungkinan format header
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    error_log("Authorization header: " . $authHeader);
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        error_log("Token extracted from Authorization header: " . substr($token, 0, 20) . "...");
    }
} elseif (isset($headers['authorization'])) {
    $authHeader = $headers['authorization'];
    error_log("authorization header (lowercase): " . $authHeader);
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        error_log("Token extracted from authorization header: " . substr($token, 0, 20) . "...");
    }
}

// Cek juga dari GET parameter
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
    error_log("Token from GET parameter: " . substr($token, 0, 20) . "...");
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token tidak ditemukan']);
    exit();
}

// Koneksi database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Verifikasi token
$query = "SELECT user_id FROM api_tokens WHERE token = :token AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
$stmt = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();

error_log("Token query rows: " . $stmt->rowCount());

if ($stmt->rowCount() == 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token tidak valid atau sudah kadaluarsa']);
    exit();
}

// Ambil user_id dari parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit();
}

// Ambil data user
$userQuery = "SELECT * FROM users WHERE id = :id";
$userStmt = $db->prepare($userQuery);
$userStmt->bindParam(':id', $userId);
$userStmt->execute();

if ($userStmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    exit();
}

$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Ambil statistik pesan
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected
    FROM messages 
    WHERE pengirim_id = :user_id
";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->bindParam(':user_id', $userId);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Ambil daftar pesan terbaru
$messagesQuery = "
    SELECT 
        m.id,
        m.isi_pesan,
        m.status,
        m.created_at,
        mt.jenis_pesan
    FROM messages m
    LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
    WHERE m.pengirim_id = :user_id
    ORDER BY m.created_at DESC
    LIMIT 10
";
$messagesStmt = $db->prepare($messagesQuery);
$messagesStmt->bindParam(':user_id', $userId);
$messagesStmt->execute();
$messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Format messages dengan nomor urut
$formattedMessages = [];
$no = 1;
foreach ($messages as $msg) {
    $formattedMessages[] = [
        'no' => $no++,
        'id' => $msg['id'],
        'isi_pesan' => $msg['isi_pesan'],
        'status' => $msg['status'],
        'created_at' => $msg['created_at'],
        'jenis_pesan' => $msg['jenis_pesan'],
        'formatted_date' => date('d/m/Y H:i', strtotime($msg['created_at'])),
    ];
}

echo json_encode([
    'success' => true,
    'user' => $user,
    'stats_cards' => [
        'total' => (int)($stats['total'] ?? 0),
        'pending' => (int)($stats['pending'] ?? 0),
        'approved' => (int)($stats['approved'] ?? 0),
        'rejected' => (int)($stats['rejected'] ?? 0),
    ],
    'messages' => $formattedMessages,
]);
?>