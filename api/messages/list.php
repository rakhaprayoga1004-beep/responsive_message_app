<?php
// C:\xampp\htdocs\responsive-message-app\api\messages\list.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Koneksi database
$host = 'localhost';
$port = '3307';
$dbname = 'responsive_message_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $userData['user_id'];
$userType = $userData['user_type'];

// Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$status = isset($_GET['status']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
$type = isset($_GET['type']) && $_GET['type'] !== 'all' ? (int)$_GET['type'] : null;
$priority = isset($_GET['priority']) && $_GET['priority'] !== 'all' ? $_GET['priority'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

try {
    $whereConditions = ["1=1"];
    $params = [];
    
    if ($userType !== 'Admin') {
        $whereConditions[] = "m.pengirim_id = :user_id";
        $params[':user_id'] = $userId;
    }
    
    if ($status) {
        $whereConditions[] = "m.status = :status";
        $params[':status'] = $status;
    }
    
    if ($type) {
        $whereConditions[] = "m.jenis_pesan_id = :type";
        $params[':type'] = $type;
    }
    
    if ($priority) {
        $whereConditions[] = "m.priority = :priority";
        $params[':priority'] = $priority;
    }
    
    if ($search) {
        $whereConditions[] = "(m.isi_pesan LIKE :search OR m.pengirim_nama LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM messages m WHERE $whereClause";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    // Get messages with pagination
    $offset = ($page - 1) * $perPage;
    $sql = "
        SELECT 
            m.*,
            mt.jenis_pesan,
            (SELECT COUNT(*) FROM message_responses WHERE message_id = m.id) as response_count,
            (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        WHERE $whereClause
        ORDER BY m.created_at DESC
        LIMIT :offset, :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
    
    // Get message types for filter
    $typeStmt = $pdo->query("SELECT id, jenis_pesan FROM message_types WHERE is_active = 1 ORDER BY jenis_pesan");
    $messageTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
        'message_types' => $messageTypes
    ]);
    
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>