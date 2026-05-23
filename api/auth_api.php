<?php
/**
 * Authentication API for Mobile Apps
 * File: api/auth_api.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting for authentication endpoints
if (!checkAuthRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Try again later.']);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        
        case 'register':
            handleRegister();
            break;
        
        case 'refresh':
            handleRefreshToken();
            break;
        
        case 'logout':
            handleLogout();
            break;
        
        case 'forgot_password':
            handleForgotPassword();
            break;
        
        case 'reset_password':
            handleResetPassword();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} elseif ($method === 'GET') {
    switch ($action) {
        case 'profile':
            handleGetProfile();
            break;
        
        case 'validate_token':
            handleValidateToken();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * Handle user login
 */
function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        return;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Check login attempts
    if (!checkLoginAttempts($data['username'])) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Account temporarily locked. Try again later.']);
        return;
    }
    
    // Find user
    $sql = "SELECT * FROM users WHERE (username = :username OR email = :username) AND is_active = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':username' => Security::sanitize($data['username'])]);
    $user = $stmt->fetch();
    
    if (!$user) {
        logLoginAttempt($data['username'], false);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        return;
    }
    
    // Verify password
    if (!Security::verifyPassword($data['password'], $user['password_hash'])) {
        logLoginAttempt($data['username'], false);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        return;
    }
    
    // Generate tokens
    $accessToken = generateAccessToken($user['id']);
    $refreshToken = generateRefreshToken($user['id']);
    
    // Update last login
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([':id' => $user['id']]);
    
    // Log successful login
    logLoginAttempt($data['username'], true);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'nama_lengkap' => $user['nama_lengkap'],
                'user_type' => $user['user_type'],
                'nis_nip' => $user['nis_nip'],
                'kelas' => $user['kelas'],
                'jurusan' => $user['jurusan'],
                'privilege_level' => $user['privilege_level']
            ],
            'tokens' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 3600, // 1 hour
                'token_type' => 'Bearer'
            ]
        ]
    ]);
}

/**
 * Handle user registration
 */
function handleRegister() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $errors = validateRegistrationData($data);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }
    
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    try {
        // Check if user exists
        $checkSql = "SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([
            ':username' => Security::sanitize($data['username']),
            ':email' => Security::sanitize($data['email'], 'email')
        ]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
            return;
        }
        
        // Create user
        $sql = "
            INSERT INTO users (
                username, password_hash, email, user_type, nis_nip,
                nama_lengkap, kelas, jurusan, phone_number, privilege_level,
                is_active, created_at, updated_at
            ) VALUES (
                :username, :password_hash, :email, :user_type, :nis_nip,
                :nama_lengkap, :kelas, :jurusan, :phone_number, :privilege_level,
                1, NOW(), NOW()
            )
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':username' => Security::sanitize($data['username']),
            ':password_hash' => Security::hashPassword($data['password']),
            ':email' => Security::sanitize($data['email'], 'email'),
            ':user_type' => Security::sanitize($data['user_type']),
            ':nis_nip' => Security::sanitize($data['nis_nip']),
            ':nama_lengkap' => Security::sanitize($data['nama_lengkap']),
            ':kelas' => isset($data['kelas']) ? Security::sanitize($data['kelas']) : null,
            ':jurusan' => isset($data['jurusan']) ? Security::sanitize($data['jurusan']) : null,
            ':phone_number' => isset($data['phone_number']) ? Security::sanitize($data['phone_number']) : null,
            ':privilege_level' => $data['privilege_level'] ?? 'Limited_Lv3'
        ]);
        
        $userId = $db->lastInsertId();
        
        // Generate tokens
        $accessToken = generateAccessToken($userId);
        $refreshToken = generateRefreshToken($userId);
        
        // Create audit log
        createAuditLog($userId, 'REGISTER', 'users', $userId, null, [
            'username' => $data['username'],
            'user_type' => $data['user_type']
        ]);
        
        $db->commit();
        
        // Get created user
        $userSql = "SELECT * FROM users WHERE id = :id";
        $userStmt = $db->prepare($userSql);
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'user_type' => $user['user_type'],
                    'nis_nip' => $user['nis_nip']
                ],
                'tokens' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => 3600,
                    'token_type' => 'Bearer'
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Registration failed']);
    }
}

/**
 * Handle token refresh
 */
function handleRefreshToken() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['refresh_token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Refresh token required']);
        return;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Validate refresh token
    $sql = "
        SELECT rt.*, u.* 
        FROM refresh_tokens rt
        LEFT JOIN users u ON rt.user_id = u.id
        WHERE rt.token = :token 
        AND rt.expires_at > NOW()
        AND u.is_active = 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':token' => $data['refresh_token']]);
    $tokenData = $stmt->fetch();
    
    if (!$tokenData) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid refresh token']);
        return;
    }
    
    // Generate new tokens
    $newAccessToken = generateAccessToken($tokenData['user_id']);
    $newRefreshToken = generateRefreshToken($tokenData['user_id']);
    
    // Delete old refresh token
    $deleteSql = "DELETE FROM refresh_tokens WHERE token = :token";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->execute([':token' => $data['refresh_token']]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer'
        ]
    ]);
}

/**
 * Generate access token
 */
function generateAccessToken($userId) {
    $db = Database::getInstance()->getConnection();
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Store in database
    $sql = "
        INSERT INTO access_tokens 
        (user_id, token_hash, expires_at, created_at) 
        VALUES (:user_id, :token_hash, :expires_at, NOW())
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $hashedToken,
        ':expires_at' => $expiresAt
    ]);
    
    return $token;
}

/**
 * Generate refresh token
 */
function generateRefreshToken($userId) {
    $db = Database::getInstance()->getConnection();
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
    
    // Store in database
    $sql = "
        INSERT INTO refresh_tokens 
        (user_id, token_hash, expires_at, created_at) 
        VALUES (:user_id, :token_hash, :expires_at, NOW())
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $hashedToken,
        ':expires_at' => $expiresAt
    ]);
    
    return $token;
}

/**
 * Validate registration data
 */
function validateRegistrationData($data) {
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($data['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if (empty($data['confirm_password'])) {
        $errors[] = 'Confirm password is required';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($data['nama_lengkap'])) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($data['user_type'])) {
        $errors[] = 'User type is required';
    }
    
    if (empty($data['nis_nip'])) {
        $errors[] = 'NIS/NIP is required';
    }
    
    return $errors;
}

/**
 * Check login attempts
 */
function checkLoginAttempts($username) {
    $db = Database::getInstance()->getConnection();
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $sql = "
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE (username = :username OR ip_address = :ip)
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND success = 0
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':ip' => $ip
    ]);
    
    $result = $stmt->fetch();
    return $result['attempts'] < 5; // Allow 5 failed attempts in 15 minutes
}

/**
 * Log login attempt
 */
function logLoginAttempt($username, $success) {
    $db = Database::getInstance()->getConnection();
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $sql = "
        INSERT INTO login_attempts 
        (username, ip_address, success, attempt_time) 
        VALUES (:username, :ip, :success, NOW())
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':ip' => $ip,
        ':success' => $success ? 1 : 0
    ]);
}

/**
 * Check authentication rate limit
 */
function checkAuthRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'auth_rate_' . $ip;
    
    $cacheDir = '../cache/rate_limit/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($key) . '.json';
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (time() - $data['timestamp'] < 900) { // 15 minute window
            if ($data['count'] >= 10) { // 10 attempts per 15 minutes
                return false;
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'timestamp' => time()];
        }
    } else {
        $data = ['count' => 1, 'timestamp' => time()];
    }
    
    file_put_contents($cacheFile, json_encode($data));
    return true;
}

// Note: Remaining functions (handleLogout, handleForgotPassword, handleResetPassword,
// handleGetProfile, handleValidateToken, createAuditLog) should be implemented
// based on your specific requirements.