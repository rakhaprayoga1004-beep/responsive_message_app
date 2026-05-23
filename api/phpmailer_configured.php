<?php
/**
 * PHPMailer Configuration dengan Gmail SMTP
 * File: api/phpmailer_configured.php
 */

// Tampilkan semua error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek apakah PHPMailer sudah terinstall
$phpmailer_paths = [
    '../vendor/PHPMailer/src/Exception.php',
    '../vendor/phpmailer/phpmailer/src/Exception.php',
    '../PHPMailer/src/Exception.php'
];

$found = false;
foreach ($phpmailer_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $found = true;
        break;
    }
}

if (!$found) {
    die("❌ PHPMailer tidak ditemukan! Download dari: https://github.com/PHPMailer/PHPMailer");
}

require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHPMailer Configuration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 30px; }
        .container { max-width: 800px; }
        .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .step { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; font-family: monospace; border-left: 4px solid #0d6efd; }
        .success { color: #28a745; }
        .danger { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">📧 Konfigurasi PHPMailer untuk Gmail</h1>
        
        <?php
        // Cek status PHPMailer
        echo '<div class="card">';
        echo '<h4>📊 Status PHPMailer</h4>';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo '<div class="alert alert-success">✅ PHPMailer berhasil di-load</div>';
        } else {
            echo '<div class="alert alert-danger">❌ PHPMailer gagal di-load</div>';
        }
        echo '</div>';
        ?>

        <div class="card">
            <h4>🔐 Cara Mendapatkan App Password Gmail</h4>
            <div class="step">
                <ol>
                    <li>Aktifkan 2-Factor Authentication (2FA) di akun Gmail Anda</li>
                    <li>Buka: <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
                    <li>Pilih "Mail" dan "Other" (beri nama "Responsive Message App")</li>
                    <li>Copy password 16 digit yang dihasilkan</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <h4>✉️ Test Kirim Email</h4>
            <form method="POST" action="">
                <div class="mb-3">
                    <label>Email Tujuan:</label>
                    <input type="email" name="to_email" class="form-control" value="agung.senen3@gmail.com" required>
                </div>
                <div class="mb-3">
                    <label>Subject:</label>
                    <input type="text" name="subject" class="form-control" value="Test PHPMailer - <?php echo date('Y-m-d H:i:s'); ?>">
                </div>
                <div class="mb-3">
                    <label>Pesan:</label>
                    <textarea name="message" class="form-control" rows="5">Halo, ini adalah email test dari Responsive Message App menggunakan PHPMailer.&#10;&#10;Waktu: <?php echo date('Y-m-d H:i:s'); ?>&#10;&#10;Jika Anda menerima email ini, berarti konfigurasi berhasil!</textarea>
                </div>
                <div class="mb-3">
                    <label>App Password (16 digit):</label>
                    <input type="password" name="app_password" class="form-control" placeholder="xxxx xxxx xxxx xxxx" required>
                    <small class="text-muted">Masukkan App Password 16 digit (dengan atau tanpa spasi)</small>
                </div>
                <button type="submit" name="send" class="btn btn-primary">Kirim Email Test</button>
            </form>
            
            <?php
            if (isset($_POST['send'])) {
                $to_email = $_POST['to_email'];
                $subject = $_POST['subject'];
                $message = nl2br(htmlspecialchars($_POST['message']));
                $app_password = str_replace(' ', '', $_POST['app_password']); // Hapus spasi
                
                echo '<div class="mt-4">';
                echo '<h5>Hasil Pengiriman:</h5>';
                
                $mail = new PHPMailer(true);
                
                try {
                    // Server settings
                    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
                    $mail->Debugoutput = function($str, $level) {
                        echo "<pre class='text-info'>$str</pre>";
                    };
                    
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'agung.senen3@gmail.com';
                    $mail->Password   = $app_password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    
                    // Recipients
                    $mail->setFrom('noreply@smkn12jakarta.sch.id', 'Responsive Message App');
                    $mail->addAddress($to_email);
                    $mail->addReplyTo('agung.senen3@gmail.com', 'Admin');
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { max-width: 600px; margin: 0 auto; }
                            .header { background: #0b4d8a; color: white; padding: 20px; text-align: center; }
                            .content { padding: 30px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Responsive Message App - SMKN 12 Jakarta</h2>
                            </div>
                            <div class='content'>
                                $message
                                <hr>
                                <p><small>Email ini dikirim otomatis oleh sistem.</small></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
                    
                    $mail->send();
                    echo '<div class="alert alert-success">✅ Email berhasil dikirim ke ' . htmlspecialchars($to_email) . '</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '❌ Gagal mengirim email<br>';
                    echo 'Error: ' . $mail->ErrorInfo;
                    echo '</div>';
                    
                    // Troubleshooting tips
                    echo '<div class="alert alert-warning mt-3">';
                    echo '<strong>Tips Troubleshooting:</strong><br>';
                    echo '<ul>';
                    echo '<li>Pastikan App Password benar (16 digit, tanpa spasi)</li>';
                    echo '<li>Pastikan 2FA sudah diaktifkan di akun Gmail</li>';
                    echo '<li>Coba buka: https://myaccount.google.com/lesssecureapps (pastikan diizinkan)</li>';
                    echo '<li>Cek apakah ada notifikasi keamanan dari Google di email Anda</li>';
                    echo '</ul>';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>

        <div class="card">
            <h4>📝 Kode Lengkap untuk Digunakan</h4>
            <div class="code">
// Gunakan kode ini di api/email.php
function sendEmailViaPHPMailer($to, $subject, $htmlContent, $textContent = null) {
    require_once '../vendor/PHPMailer/src/Exception.php';
    require_once '../vendor/PHPMailer/src/PHPMailer.php';
    require_once '../vendor/PHPMailer/src/SMTP.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'agung.senen3@gmail.com';
        $mail->Password   = 'YOUR_16_DIGIT_APP_PASSWORD';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@smkn12jakarta.sch.id', 'Responsive Message App');
        $mail->addAddress($to);
        $mail->addReplyTo('agung.senen3@gmail.com', 'Admin');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = $textContent ?? strip_tags($htmlContent);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
            </div>
        </div>
    </div>
</body>
</html>