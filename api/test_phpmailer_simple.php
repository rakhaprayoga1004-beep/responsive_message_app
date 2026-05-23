<?php
/**
 * Simple PHPMailer Test
 * File: api/test_phpmailer_simple.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cari lokasi PHPMailer
$possible_paths = [
    '../vendor/PHPMailer/src/PHPMailer.php',
    '../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    '../PHPMailer/src/PHPMailer.php'
];

$loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once str_replace('PHPMailer.php', 'Exception.php', $path);
        require_once $path;
        require_once str_replace('PHPMailer.php', 'SMTP.php', $path);
        $loaded = true;
        echo "✅ PHPMailer ditemukan di: $path<br>";
        break;
    }
}

if (!$loaded) {
    die("❌ PHPMailer tidak ditemukan! Download dari: https://github.com/PHPMailer/PHPMailer");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "<h3>Test Pengiriman Email ke agung.senen3@gmail.com</h3>";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Tampilkan debug
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'agung.senen3@gmail.com';
    
    // Minta App Password dari user
    if (isset($_POST['app_password'])) {
        $mail->Password = $_POST['app_password'];
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@smkn12jakarta.sch.id', 'Responsive Message App');
        $mail->addAddress('agung.senen3@gmail.com');
        $mail->addReplyTo('agung.senen3@gmail.com', 'Admin');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Test PHPMailer - ' . date('Y-m-d H:i:s');
        $mail->Body    = '<h2>Test Email</h2><p>Email berhasil dikirim via PHPMailer!</p><p>Waktu: ' . date('Y-m-d H:i:s') . '</p>';
        $mail->AltBody = 'Test email via PHPMailer';
        
        $mail->send();
        echo '<div style="color: green; font-weight: bold;">✅ Email berhasil dikirim!</div>';
    } else {
        // Tampilkan form input App Password
        ?>
        <form method="POST">
            <label>Masukkan App Password Gmail (16 digit):</label>
            <input type="password" name="app_password" required 
                   placeholder="xxxx xxxx xxxx xxxx" 
                   style="width: 300px; padding: 10px; margin: 10px 0;">
            <small style="display: block; color: #666;">
                Dapatkan di: https://myaccount.google.com/apppasswords
            </small>
            <button type="submit" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px;">
                Kirim Test Email
            </button>
        </form>
        <?php
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "❌ Gagal: {$mail->ErrorInfo}";
    echo "</div>";
}
?>