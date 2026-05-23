<?php
/**
 * Clear Session - Hapus semua token CSRF lama
 * File: clear_session.php
 * 
 * JALANKAN SEKALI SAJA! 
 * Akses: http://localhost:8080/responsive-message-app/clear_session.php
 */

session_start();

// Hapus semua token CSRF lama
unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_time']);

// Hapus form token lama
unset($_SESSION['form_token']);
unset($_SESSION['form_token_time']);
unset($_SESSION['honeypot_name']);

// Reset login attempts
unset($_SESSION['login_attempts']);
unset($_SESSION['captcha_code']);

// Tapi jangan hapus user login
// Biarkan $_SESSION['user_id'] dan $_SESSION['user_type'] tetap ada

echo "<h1>✅ Session Cleared!</h1>";
echo "<p>CSRF tokens and old session data have been removed.</p>";
echo "<p><a href='index.php'>Go to Index</a> | <a href='login.php'>Go to Login</a></p>";

// Tampilkan session yang tersisa
echo "<h3>Current Session:</h3>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    if (!in_array($key, ['user_id', 'user_type', 'username', 'nama_lengkap'])) {
        echo "$key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}
echo "</pre>";
?>