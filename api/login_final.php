<?php
// C:\xampp\htdocs\responsive-message-app\api\login_final.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    error_log("Login attempt - Username: " . $username);
    
    // Gunakan MySQLi
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Escape string
    $username = $conn->real_escape_string($username);
    
    $sql = "SELECT 
                id, 
                username, 
                password_hash, 
                user_type, 
                nama_lengkap, 
                email, 
                is_active, 
                privilege_level,
                phone_number,
                avatar
            FROM users 
            WHERE username = '$username' AND is_active = 1 
            LIMIT 1";
    
    error_log("SQL: " . $sql);
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        error_log("User found: " . $user['username']);
        
        if (password_verify($password, $user['password_hash'])) {
            error_log("Password valid");
            
            // SIMPAN SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['login_time'] = time();
            
            error_log("Session saved - ID: " . session_id());
            error_log("Session data: " . print_r($_SESSION, true));
            
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
            $conn->query($updateSql);
            
            // Hapus password hash
            unset($user['password_hash']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $user,
                'token' => bin2hex(random_bytes(32)),
                'session_id' => session_id() // Kirim session ID ke client
            ]);
            
        } else {
            error_log("Password salah");
            echo json_encode([
                'success' => false,
                'message' => 'Username atau password salah'
            ]);
        }
    } else {
        error_log("User not found: " . $username);
        echo json_encode([
            'success' => false,
            'message' => 'Username atau password salah'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}