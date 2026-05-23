<?php
/**
 * API untuk manajemen Users
 * File: api/users.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

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
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Please login first'
    ]);
    exit();
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($method === 'GET') {
        // Get parameters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $userType = isset($_GET['user_type']) ? trim($_GET['user_type']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
        
        if ($limit > 100) $limit = 100;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        // Build base query
        $whereClauses = [];
        $params = [];
        
        if (!empty($search)) {
            $whereClauses[] = "(username LIKE :search OR nama_lengkap LIKE :search OR email LIKE :search OR nis_nip LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($userType) && $userType !== 'Semua') {
            $whereClauses[] = "user_type = :user_type";
            $params[':user_type'] = $userType;
        }
        
        if (!empty($status) && $status !== 'Semua') {
            if ($status === 'aktif') {
                $whereClauses[] = "is_active = 1";
            } else if ($status === 'nonaktif') {
                $whereClauses[] = "is_active = 0";
            }
        }
        
        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM users $whereSql";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Main query - PASTIKAN privilege_level DI-SELECT
        $sql = "SELECT 
                    u.id, 
                    u.username, 
                    u.user_type, 
                    u.nama_lengkap, 
                    u.email,
                    u.is_active,
                    u.nis_nip,
                    u.phone_number,
                    u.avatar as foto,
                    u.privilege_level,
                    u.kelas,
                    u.jurusan,
                    u.mata_pelajaran,
                    u.created_at,
                    u.updated_at,
                    u.last_login
                FROM users u
                $whereSql";
        
        // Sorting
        switch ($sort) {
            case 'oldest':
                $sql .= " ORDER BY u.created_at ASC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY u.nama_lengkap ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY u.nama_lengkap DESC";
                break;
            case 'id_asc':
                $sql .= " ORDER BY u.id ASC";
                break;
            case 'id_desc':
                $sql .= " ORDER BY u.id DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY u.created_at DESC";
                break;
        }
        
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format users - PASTIKAN privilege_level DIKIRIM
        $formattedUsers = [];
        foreach ($users as $user) {
            $formattedUsers[] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'status' => $user['is_active'] == 1 ? 'aktif' : 'nonaktif',
                'is_active' => $user['is_active'] == 1,
                'nis_nip' => $user['nis_nip'] ?? '',
                'no_telp' => $user['phone_number'] ?? '',
                'foto' => $user['avatar'] ?? 'default-avatar.png',
                'privilege_level' => $user['privilege_level'], // LANGSUNG DARI DATABASE
                'kelas' => $user['kelas'] ?? '',
                'jurusan' => $user['jurusan'] ?? '',
                'mata_pelajaran' => $user['mata_pelajaran'] ?? '',
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
                'last_login' => $user['last_login']
            ];
        }
        
        // Get statistics
        $stats = [];
        $statsSql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
        $statsStmt = $db->query($statsSql);
        while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['user_type']] = (int)$row['count'];
        }
        
        $activeCount = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
        $inactiveCount = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
        
        $stats['aktif'] = (int)$activeCount;
        $stats['nonaktif'] = (int)$inactiveCount;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $formattedUsers,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'stats' => $stats
        ]);
        exit();
        
    } elseif ($method === 'POST' && $action === 'status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 0;
        
        $sql = "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$isActive, $id]);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'User status updated']);
        exit();
        
    } elseif ($method === 'POST' && $action === 'reset-password') {
        $newPassword = bin2hex(random_bytes(4));
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hashedPassword, $id]);
        
        $sql = "SELECT email, nama_lengkap FROM users WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset successfully', 
            'new_password' => $newPassword,
            'email' => $user['email'] ?? null
        ]);
        exit();
        
    } else {
        ob_clean();
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}