<?php
// api.php
session_start();
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        // Untuk DEBUG, matikan CSRF dulu
        // $csrf_token = $_POST['csrf_token'] ?? '';
        // if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        //     echo json_encode([
        //         'status' => 'error',
        //         'message' => 'CSRF token tidak valid'
        //     ]);
        //     exit;
        // }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Query ke database
        $query = "SELECT * FROM users WHERE username = ? AND password = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            
            // Generate new CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Login berhasil',
                'data' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'email' => $user['email'] ?? '',
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Username atau password salah'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
}
?>