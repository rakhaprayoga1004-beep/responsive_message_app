<?php
/**
 * FIX EMAIL - Script untuk memperbaiki pengiriman email di XAMPP
 * File: api/fix_email.php
 */

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek konfigurasi PHP saat ini
$smtp_config = [
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path')
];

// Lokasi php.ini
$php_ini_path = php_ini_loaded_file();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Email Configuration - XAMPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 30px; }
        .container { max-width: 1000px; }
        .config-box { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; font-family: monospace; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">🔧 Fix Email Configuration untuk XAMPP</h1>
        
        <div class="config-box">
            <h4>📊 Current PHP Mail Configuration</h4>
            <table class="table table-bordered">
                <tr>
                    <th>SMTP Server</th>
                    <td><?php echo $smtp_config['SMPT'] ?: 'localhost (default)'; ?></td>
                </tr>
                <tr>
                    <th>SMTP Port</th>
                    <td><?php echo $smtp_config['smtp_port'] ?: '25 (default)'; ?></td>
                </tr>
                <tr>
                    <th>sendmail_from</th>
                    <td><?php echo $smtp_config['sendmail_from'] ?: 'Not set'; ?></td>
                </tr>
                <tr>
                    <th>php.ini location</th>
                    <td><?php echo $php_ini_path ?: 'Not found'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="config-box">
            <h4>⚠️ Masalah Terdeteksi</h4>
            <div class="warning">
                <strong>Error:</strong> Failed to connect to mailserver at "localhost" port 25<br>
                <strong>Penyebab:</strong> XAMPP tidak memiliki mail server yang berjalan di port 25.
            </div>
        </div>
        
        <div class="config-box">
            <h4>✅ Solusi 1: Gunakan SMTP Gmail (REKOMENDASI)</h4>
            <p>Tambahkan kode ini di file PHP Anda:</p>
            <div class="code">
// Di awal script (sebelum mail())
ini_set('SMTP', 'smtp.gmail.com');
ini_set('smtp_port', 587);
ini_set('sendmail_from', 'agung.senen3@gmail.com');

// Tambahkan authentication (gunakan App Password)
ini_set('auth_username', 'agung.senen3@gmail.com');
ini_set('auth_password', 'YOUR_16_DIGIT_APP_PASSWORD');
            </div>
            
            <button class="btn btn-primary mt-3" onclick="testGmailSMTP()">
                Test Gmail SMTP
            </button>
            <div id="gmailResult" class="mt-3"></div>
        </div>
        
        <div class="config-box">
            <h4>✅ Solusi 2: Install Fake SMTP Server (MailHog)</h4>
            <p>Download dan jalankan MailHog, lalu ubah konfigurasi:</p>
            <div class="code">
// Install MailHog dari: https://github.com/mailhog/MailHog/releases
// Jalankan MailHog (akan di port 1025)

// Di PHP:
ini_set('SMTP', 'localhost');
ini_set('smtp_port', 1025);
ini_set('sendmail_from', 'noreply@localhost.local');
            </div>
            
            <button class="btn btn-success mt-3" onclick="testMailHog()">
                Test MailHog (if running)
            </button>
            <div id="mailhogResult" class="mt-3"></div>
        </div>
        
        <div class="config-box">
            <h4>✅ Solusi 3: Gunakan PHPMailer (Paling Mudah)</h4>
            <p>Download PHPMailer dan gunakan script ini:</p>
            <div class="code">
// Download PHPMailer dari: https://github.com/PHPMailer/PHPMailer
require_once 'path/to/PHPMailer/src/Exception.php';
require_once 'path/to/PHPMailer/src/PHPMailer.php';
require_once 'path/to/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'agung.senen3@gmail.com';
$mail->Password = 'YOUR_APP_PASSWORD';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('noreply@smkn12jakarta.sch.id', 'Responsive Message App');
$mail->addAddress('agung.senen3@gmail.com');
$mail->Subject = 'Test Email via PHPMailer';
$mail->Body = '&lt;h1&gt;Test&lt;/h1&gt;&lt;p&gt;Email berhasil dikirim!&lt;/p&gt;';
$mail->AltBody = 'Test email via PHPMailer';
$mail->send();
            </div>
        </div>
        
        <div class="config-box">
            <h4>✅ Solusi 4: Gunakan Mailtrap.io (Untuk Testing)</h4>
            <p>Daftar gratis di <a href="https://mailtrap.io" target="_blank">Mailtrap.io</a></p>
            <div class="code">
// Konfigurasi Mailtrap
ini_set('SMTP', 'smtp.mailtrap.io');
ini_set('smtp_port', 2525);
ini_set('sendmail_from', 'noreply@smkn12jakarta.sch.id');
ini_set('auth_username', 'your-mailtrap-username');
ini_set('auth_password', 'your-mailtrap-password');
            </div>
        </div>
    </div>
    
    <script>
    function testGmailSMTP() {
        const result = document.getElementById('gmailResult');
        result.innerHTML = 'Mengirim test email via Gmail SMTP...';
        
        // Coba kirim email via Gmail SMTP
        fetch('test_email_gmail.php')
            .then(response => response.text())
            .then(data => {
                result.innerHTML = '<div class="alert alert-success">' + data + '</div>';
            })
            .catch(error => {
                result.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
            });
    }
    
    function testMailHog() {
        const result = document.getElementById('mailhogResult');
        
        // Cek apakah MailHog berjalan
        fetch('http://localhost:8025/api/v2/messages', { mode: 'no-cors' })
            .then(() => {
                result.innerHTML = '<div class="alert alert-success">MailHog berjalan! Kirim test email...</div>';
                
                // Kirim test email via MailHog
                fetch('test_email_mailhog.php')
                    .then(response => response.text())
                    .then(data => {
                        result.innerHTML += '<div class="alert alert-info mt-2">' + data + '</div>';
                        result.innerHTML += '<div>Cek di <a href="http://localhost:8025" target="_blank">http://localhost:8025</a></div>';
                    });
            })
            .catch(() => {
                result.innerHTML = '<div class="alert alert-warning">MailHog tidak berjalan. Jalankan dulu MailHog-nya.</div>';
            });
    }
    </script>
</body>
</html>