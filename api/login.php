<?php
/**
 * Login API - Untuk autentikasi user
 * Menggabungkan fungsi dari login_final.php dengan session management
 * File: responsive-message-app/api/login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';

try {
    // Cek content type untuk menentukan cara baca input
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    $username = '';
    $password = '';
    $remember = false;
    
    if (strpos($contentType, 'application/json') !== false) {
        // Input dari JSON (untuk kompatibilitas dengan Flutter yang mungkin kirim JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $remember = isset($input['remember']) && $input['remember'] == '1';
    } else {
        // Input dari form data (x-www-form-urlencoded)
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
    }
    
    error_log("Login attempt - Username: " . $username);
    error_log("Content-Type: " . $contentType);
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username dan password harus diisi');
    }
    
    // Koneksi MySQLi (mengikuti pola dari login_final.php)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Escape string untuk keamanan
    $username = $conn->real_escape_string($username);
    
    // Query untuk mencari user
    $sql = "SELECT 
                id, 
                username, 
                password_hash as password, 
                user_type, 
                nama_lengkap as nama, 
                email, 
                is_active, 
                privilege_level,
                phone_number,
                avatar,
                nis_nip,
                kelas,
                jurusan,
                mata_pelajaran
            FROM users 
            WHERE (username = '$username' OR email = '$username') AND is_active = 1 
            LIMIT 1";
    
    error_log("SQL Query: " . $sql);
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        error_log("User ditemukan: " . $user['username']);
        
        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            error_log("Password valid");
            
            // SET SESSION - INI YANG PENTING!
            $session = SessionManager::getInstance();
            $session->setSession(
                $user['id'],
                $user['user_type'] ?? 'User',
                $user['nama'] ?? $user['username']
            );
            
            error_log("Session created: " . session_id());
            
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
            $conn->query($updateSql);
            
            // Hapus password dari output
            unset($user['password']);
            
            // Kirim response sukses dengan session_id
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil',
                'session_id' => session_id(),
                'user_id' => $user['id'],
                'user_type' => $user['user_type'] ?? 'User',
                'user_name' => $user['nama'] ?? $user['username'],
                'username' => $user['username'],
                'email' => $user['email'],
                'token' => bin2hex(random_bytes(32)), // Untuk kompatibilitas
                'user' => $user // Data lengkap user
            ]);
            
        } else {
            error_log("Password salah");
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Username atau password salah'
            ]);
        }
    } else {
        error_log("User tidak ditemukan: " . $username);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Username atau password salah'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>