<?php
// C:\xampp\htdocs\responsive-message-app\api\api_login_flutter.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Generate CSRF token jika belum ada (untuk kompatibilitas)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$input = json_decode(file_get_contents('php://input'), true);

// Initialize login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$remember = isset($input['remember']) && $input['remember'] == '1';
$captcha_code = trim($input['captcha_code'] ?? '');

// Validate inputs
$errors = [];

if (empty($username)) {
    $errors[] = 'Username harus diisi.';
}

if (empty($password)) {
    $errors[] = 'Password harus diisi.';
}

// Check if captcha is required
$show_captcha = ($_SESSION['login_attempts'] >= 3);
if ($show_captcha && empty($captcha_code)) {
    $errors[] = 'Kode keamanan (CAPTCHA) harus diisi.';
}

// If validation errors
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors),
        'require_captcha' => $show_captcha,
        'login_attempts' => $_SESSION['login_attempts']
    ]);
    exit;
}

try {
    // Database connection menggunakan PDO seperti di login.php asli
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare SQL statement persis seperti di login.php asli
    $stmt = $conn->prepare("
        SELECT 
            id, 
            username, 
            password_hash, 
            user_type, 
            nama_lengkap, 
            email, 
            is_active,
            last_login,
            nis_nip,
            kelas,
            jurusan,
            mata_pelajaran,
            privilege_level,
            phone_number,
            avatar
        FROM users 
        WHERE (username = :username OR email = :username)
        AND is_active = 1
    ");
    
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Login successful - reset attempts
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['captcha_code']);
            
            // Set session variables (untuk kompatibilitas dengan sistem lain)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nis_nip'] = $user['nis_nip'];
            $_SESSION['kelas'] = $user['kelas'];
            $_SESSION['jurusan'] = $user['jurusan'];
            $_SESSION['mata_pelajaran'] = $user['mata_pelajaran'];
            $_SESSION['privilege_level'] = $user['privilege_level'];
            $_SESSION['phone_number'] = $user['phone_number'];
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET last_login = NOW()
                WHERE id = :user_id
            ");
            $update_stmt->execute([':user_id' => $user['id']]);
            
            // Set remember me jika diminta
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Simpan token di database jika tabel remember_tokens ada
                try {
                    $token_stmt = $conn->prepare("
                        INSERT INTO remember_tokens (user_id, token, expires_at) 
                        VALUES (:user_id, :token, :expires_at)
                        ON DUPLICATE KEY UPDATE 
                        token = :token, 
                        expires_at = :expires_at
                    ");
                    $token_stmt->execute([
                        ':user_id' => $user['id'],
                        ':token' => hash('sha256', $token),
                        ':expires_at' => date('Y-m-d H:i:s', $expiry)
                    ]);
                } catch (Exception $e) {
                    error_log("Remember token error: " . $e->getMessage());
                }
            }
            
            // Tentukan redirect path berdasarkan user type (sama persis dengan login.php)
            $redirect_path = '';
            switch($user['user_type']) {
                case 'Admin':
                    $redirect_path = '/modules/admin/dashboard.php';
                    break;
                case 'Kepala_Sekolah':
                case 'Wakil_Kepala':
                    $redirect_path = '/modules/wakepsek/dashboard.php';
                    break;
                case 'Guru_BK':
                case 'Guru_Humas':
                case 'Guru_Kurikulum':
                case 'Guru_Kesiswaan':
                case 'Guru_Sarana':
                    $redirect_path = '/modules/guru/followup.php';
                    break;
                case 'Guru':
                case 'Siswa':
                case 'Orang_Tua':
                    $redirect_path = '/modules/user/send_message.php';
                    break;
                default:
                    $redirect_path = '/index.php';
            }
            
            // Hapus password_hash dari output
            unset($user['password_hash']);
            
            // Generate token untuk Flutter
            $auth_token = bin2hex(random_bytes(32));
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => $user,
                'token' => $auth_token,
                'csrf_token' => $_SESSION['csrf_token'],
                'redirect_path' => $redirect_path,
                'login_attempts' => 0,
                'require_captcha' => false
            ]);
            
        } else {
            // Password salah
            $_SESSION['login_attempts']++;
            $require_captcha = $_SESSION['login_attempts'] >= 3;
            
            // Generate captcha jika diperlukan
            if ($require_captcha && !isset($_SESSION['captcha_code'])) {
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $captcha_text = '';
                for ($i = 0; $i < 6; $i++) {
                    $captcha_text .= $chars[rand(0, strlen($chars) - 1)];
                }
                $_SESSION['captcha_code'] = $captcha_text;
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Username atau password salah',
                'login_attempts' => $_SESSION['login_attempts'],
                'require_captcha' => $require_captcha,
                'captcha_code' => $_SESSION['captcha_code'] ?? null
            ]);
        }
    } else {
        // User tidak ditemukan
        $_SESSION['login_attempts']++;
        $require_captcha = $_SESSION['login_attempts'] >= 3;
        
        // Generate captcha jika diperlukan
        if ($require_captcha && !isset($_SESSION['captcha_code'])) {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $captcha_text = '';
            for ($i = 0; $i < 6; $i++) {
                $captcha_text .= $chars[rand(0, strlen($chars) - 1)];
            }
            $_SESSION['captcha_code'] = $captcha_text;
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Username atau password salah',
            'login_attempts' => $_SESSION['login_attempts'],
            'require_captcha' => $require_captcha,
            'captcha_code' => $_SESSION['captcha_code'] ?? null
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem. Silakan coba lagi nanti.',
        'error_detail' => $e->getMessage()
    ]);
}