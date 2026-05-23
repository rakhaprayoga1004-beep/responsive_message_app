<?php
// C:\xampp\htdocs\responsive-message-app\api\admin\users.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user's messages
        if (isset($_GET['id']) && isset($_GET['messages'])) {
            $userId = (int)$_GET['id'];
            
            $messages = $db->select("
                SELECT 
                    m.id,
                    m.reference_number,
                    mt.jenis_pesan,
                    m.isi_pesan,
                    m.status,
                    m.priority,
                    m.tanggal_pesan as created_at,
                    m.tanggal_respon,
                    COALESCE(r.nama_lengkap, 'Belum ada respon') as responder_name,
                    m.has_attachments
                FROM messages m
                LEFT JOIN message_types mt ON mt.id = m.jenis_pesan_id
                LEFT JOIN users r ON r.id = m.responder_id
                WHERE m.pengirim_id = ? OR m.external_sender_id = ?
                ORDER BY m.created_at DESC
            ", [$userId, $userId]);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            
        // Get single user detail
        } elseif (isset($_GET['id'])) {
            $userId = (int)$_GET['id'];
            $user = $db->select("
                SELECT 
                    id,
                    username,
                    nama_lengkap,
                    user_type,
                    email,
                    phone_number,
                    nis_nip,
                    kelas,
                    jurusan,
                    mata_pelajaran,
                    privilege_level,
                    is_active,
                    created_at,
                    last_login,
                    avatar
                FROM users
                WHERE id = ?
            ", [$userId]);
            
            if (!empty($user)) {
                echo json_encode(['success' => true, 'user' => $user[0]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            
        // Get all users
        } else {
            $users = $db->select("
                SELECT 
                    id,
                    username,
                    password_hash,
                    nama_lengkap,
                    user_type,
                    email,
                    phone_number,
                    nis_nip,
                    kelas,
                    jurusan,
                    mata_pelajaran,
                    privilege_level,
                    is_active,
                    created_at,
                    last_login,
                    avatar
                FROM users
                ORDER BY created_at DESC
            ");
            
            echo json_encode(['success' => true, 'users' => $users]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'] ?? 0;
        
        if ($userId > 0) {
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['nama_lengkap', 'user_type', 'email', 'phone_number', 'nis_nip', 'kelas', 'jurusan', 'mata_pelajaran', 'is_active', 'privilege_level'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
            
            // Update password if provided
            if (!empty($input['password'])) {
                $updateFields[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (!empty($updateFields)) {
                $params[] = $userId;
                $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $result = $db->execute($sql, $params);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new user
        $input = json_decode(file_get_contents('php://input'), true);
        
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? 'password123';
        $nama_lengkap = $input['nama_lengkap'] ?? '';
        $user_type = $input['user_type'] ?? 'Siswa';
        $email = $input['email'] ?? '';
        $phone_number = $input['phone_number'] ?? '';
        $nis_nip = $input['nis_nip'] ?? '';
        $kelas = $input['kelas'] ?? null;
        $jurusan = $input['jurusan'] ?? null;
        $mata_pelajaran = $input['mata_pelajaran'] ?? null;
        $privilege_level = $input['privilege_level'] ?? 'Limited_Lv3';
        
        $result = $db->execute("
            INSERT INTO users (username, password_hash, nama_lengkap, user_type, email, phone_number, nis_nip, kelas, jurusan, mata_pelajaran, privilege_level, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ", [$username, password_hash($password, PASSWORD_DEFAULT), $nama_lengkap, $user_type, $email, $phone_number, $nis_nip, $kelas, $jurusan, $mata_pelajaran, $privilege_level]);
        
        if ($result) {
            $userId = $db->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'User created', 'user_id' => $userId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create user']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete user
        $userId = $_GET['id'] ?? 0;
        
        if ($userId > 0 && $userId != $userData['user_id']) {
            $result = $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            echo json_encode(['success' => true, 'message' => 'User deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this user']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>