<?php
/**
 * get_users.php - Get list of users with pagination and filters
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

// Ambil token dari header Authorization atau GET parameter
$token = null;

// Debug: Log all headers
error_log("=== get_users.php DEBUG ===");

// Method 1: Dari header Authorization
if (function_exists('getallheaders')) {
    $headers = getallheaders();
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
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            error_log("Token extracted from authorization header: " . substr($token, 0, 20) . "...");
        }
    }
}

// Method 2: Dari GET parameter
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
    error_log("Token from GET parameter: " . substr($token, 0, 20) . "...");
}

// Method 3: Dari input JSON (POST)
if (!$token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['token'])) {
        $token = $input['token'];
        error_log("Token from POST body: " . substr($token, 0, 20) . "...");
    }
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
$query = "SELECT user_id, expires_at FROM api_tokens WHERE token = :token AND is_active = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();

error_log("Token query rows: " . $stmt->rowCount());

if ($stmt->rowCount() == 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token tidak valid']);
    exit();
}

$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
$userId = $tokenData['user_id'];

// Cek expired
if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token sudah kadaluarsa']);
    exit();
}

// Update last_used_at
$updateUsed = "UPDATE api_tokens SET last_used_at = NOW() WHERE token = :token";
$updateStmt = $db->prepare($updateUsed);
$updateStmt->bindParam(':token', $token);
$updateStmt->execute();

// Ambil parameter
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$userType = isset($_GET['user_type']) ? trim($_GET['user_type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.username LIKE :search OR u.nama_lengkap LIKE :search OR u.email LIKE :search OR u.nis_nip LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($userType)) {
    $whereConditions[] = "u.user_type = :user_type";
    $params[':user_type'] = $userType;
}

if (!empty($status)) {
    $isActive = ($status === 'aktif') ? 1 : 0;
    $whereConditions[] = "u.is_active = :is_active";
    $params[':is_active'] = $isActive;
}

$whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

// Build ORDER BY clause
switch ($sort) {
    case 'oldest':
        $orderBy = "u.id ASC";
        break;
    case 'name_asc':
        $orderBy = "u.nama_lengkap ASC";
        break;
    case 'name_desc':
        $orderBy = "u.nama_lengkap DESC";
        break;
    case 'newest':
    default:
        $orderBy = "u.id DESC";
        break;
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get users data
$query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.user_type,
        u.nis_nip,
        u.nama_lengkap,
        u.kelas,
        u.jurusan,
        u.mata_pelajaran,
        u.privilege_level,
        u.phone_number,
        u.avatar,
        u.is_active,
        u.last_login,
        u.created_at,
        u.updated_at
    FROM users u
    $whereClause
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics by user type
$statsQuery = "
    SELECT 
        user_type,
        COUNT(*) as count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
    FROM users
    GROUP BY user_type
";
$statsStmt = $db->query($statsQuery);
$stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => [
        'users' => $users,
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
        'stats' => $stats
    ]
]);
?>