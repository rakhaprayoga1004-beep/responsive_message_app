<?php
/**
 * Konfigurasi Aplikasi
 * File: config/config.php
 * 
 * VERSI: 3.2 - BASE_URL Terpusat (Mudah Maintenance)
 * - Cukup ubah $BASE_URL_IP di satu tempat
 */

// ============================================================================
// BASE URL MASTER (UBAH DI SINI SAJA UNTUK MAINTENANCE)
// ============================================================================
// Untuk mengubah domain/ip, cukup ubah variabel ini:
$BASE_URL_IP = '192.168.18.7';  // Ganti dengan IP/Domain Anda
$BASE_URL_PORT = '8090';        // Port Apache
$BASE_URL_PROTOCOL = 'http';    // 'http' atau 'https'

// Auto generate BASE_URL
define('BASE_URL', $BASE_URL_PROTOCOL . '://' . $BASE_URL_IP . ':' . $BASE_URL_PORT . '/responsive-message-app/');

// Error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Tentukan BASE_PATH
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));
define('BASE_PATH', ROOT_PATH . '/');

// Path konfigurasi
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('BACKUP_PATH', ROOT_PATH . '/backups/');
define('INCLUDE_PATH', ROOT_PATH . '/includes/');
define('CLASS_PATH', ROOT_PATH . '/classes/');
define('MODEL_PATH', ROOT_PATH . '/models/');
define('MODULE_PATH', ROOT_PATH . '/modules/');
define('ASSET_PATH', ROOT_PATH . '/assets/');

// Konfigurasi database
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'responsive_message_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Konfigurasi aplikasi
define('APP_NAME', 'Responsive Message SMKN 12 Jakarta');
define('APP_VERSION', '1.0.0');
define('SSL_URL', 'https://localhost:444/responsive-message-app/');

// ============================================================================
// KONSTANTA DEFAULT UNTUK AUTHENTICATION
// ============================================================================
define('DEFAULT_PRIVILEGE_LEVEL', 'Limited_Lv3');
define('SESSION_LIFETIME', 7200);
define('SESSION_NAME', 'RMSESSID');
define('CSRF_TOKEN_LIFETIME', 1800);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('PASSWORD_MIN_LENGTH', 8);

// Fungsi helper untuk URL
function app_url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . $path;
}

function asset_url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . 'assets/' . $path;
}

function module_url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . 'modules/' . $path;
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_name(SESSION_NAME);
    session_start();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// ============================================================================
// MAILERSEND CONFIGURATION
// ============================================================================
define('MAILERSEND_API_TOKEN', 'mlsn.a4e70a19ff00a659620ddf13fa13ea30662bb0199fa07f13ad391b43507025fa');
define('MAILERSEND_DOMAIN', 'test-r9084zv6rpjgw63d.mlsender.net');
define('MAILERSEND_DOMAIN_ID', '69oxl5ejo22l785k');
define('MAILERSEND_FROM_EMAIL', 'noreply@test-r9084zv6rpjgw63d.mlsender.net');
define('MAILERSEND_FROM_NAME', 'SMKN 12 Jakarta - Aplikasi Pesan Responsif');

// ============================================================================
// SMTP CONFIGURATION
// ============================================================================
define('SMTP_HOST', 'smtp.mailersend.net');
define('SMTP_PORT', 587);
define('SMTP_USER', 'MS_SBZwCT@test-r9084zv6rpjgw63d.mlsender.net');
define('SMTP_PASS', 'mssp.U9Sci64.yzkq340dem6ld796.wqvxBa3');
define('SMTP_SECURE', 'tls');
define('SMTP_FROM', 'noreply@test-r9084zv6rpjgw63d.mlsender.net');
define('SMTP_FROM_NAME', 'SMKN 12 Jakarta - Aplikasi Pesan Responsif');

// ============================================================================
// FONNTE WHATSAPP GATEWAY CONFIGURATION
// ============================================================================
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('FONNTE_API_KEY', 'FS2cq8FckmaTegxtZpFB');
define('FONNTE_DEVICE', '6285174207795');
define('FONNTE_PHONE_DEFAULT', '6281319055440');
define('FONNTE_COUNTRY_CODE', '62');

// ============================================================================
// WHATSAPP API (Alias untuk Fonnte)
// ============================================================================
define('WHATSAPP_API_URL', FONNTE_API_URL);
define('WHATSAPP_TOKEN', FONNTE_API_KEY);

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================
define('EMAIL_ENABLED', true);
define('EMAIL_FROM_NAME', MAILERSEND_FROM_NAME);
define('EMAIL_FROM_EMAIL', MAILERSEND_FROM_EMAIL);
define('EMAIL_USE_SMTP', false);

// ============================================================================
// DEBUG MODE
// ============================================================================
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// ============================================================================
// LOG CONFIGURATION
// ============================================================================
if (DEBUG_MODE) {
    error_log("=== CONFIGURATION LOADED ===");
    error_log("BASE_URL: " . BASE_URL);
    error_log("BASE_URL_IP: " . $BASE_URL_IP);
}
?>