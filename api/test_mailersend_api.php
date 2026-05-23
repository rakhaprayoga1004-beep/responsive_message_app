<?php
/**
 * Test MailerSend API Token
 * File: api/test_mailersend_api.php
 * 
 * Menggunakan API token: mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// API Token Anda
$api_token = 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213';

// Fungsi kirim email via MailerSend API
function sendViaMailerSendAPI($to_email, $to_name, $subject, $html_content, $text_content = '') {
    global $api_token;
    
    // Data yang akan dikirim
    $data = [
        'from' => [
            'email' => 'MS_UtNHAw@trial-ynl7argjokjl2k8n.mlsender.net', // Email sender dari MailerSend
            'name' => 'Responsive Message App'
        ],
        'to' => [
            [
                'email' => $to_email,
                'name' => $to_name
            ]
        ],
        'subject' => $subject,
        'html' => $html_content,
        'text' => $text_content ?: strip_tags($html_content)
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailersend.com/v1/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['to_email'] ?? 'agung.senen3@gmail.com';
    $to_name = $_POST['to_name'] ?? 'Agung';
    $subject = $_POST['subject'] ?? 'Test MailerSend API - ' . date('Y-m-d H:i:s');
    $message = $_POST['message'] ?? 'Test email via MailerSend API';
    
    $html_content = "<h2>Test Email</h2>
                     <p>{$message}</p>
                     <p>Waktu: " . date('Y-m-d H:i:s') . "</p>
                     <hr>
                     <p><small>Dikirim via MailerSend API</small></p>";
    
    $result = sendViaMailerSendAPI($to_email, $to_name, $subject, $html_content, $message);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test MailerSend API</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .api-token { background: #f4f4f4; padding: 10px; font-family: monospace; border-radius: 5px; margin-bottom: 20px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test MailerSend API</h1>
        
        <div class="api-token">
            <strong>API Token:</strong> mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213
        </div>
        
        <div class="info">
            <strong>ℹ️ Info Penting:</strong>
            <ul>
                <li>Anda perlu <strong>domain terverifikasi</strong> atau gunakan <strong>sender email default</strong> dari MailerSend</li>
                <li>Sender email default: <code>MS_UtNHAw@trial-ynl7argjokjl2k8n.mlsender.net</code></li>
                <li>Ganti nanti dengan domain Anda sendiri setelah verifikasi</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>To Email:</label>
                <input type="email" name="to_email" value="agung.senen3@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label>To Name:</label>
                <input type="text" name="to_name" value="Agung">
            </div>
            
            <div class="form-group">
                <label>Subject:</label>
                <input type="text" name="subject" value="Test MailerSend - <?php echo date('Y-m-d H:i:s'); ?>">
            </div>
            
            <div class="form-group">
                <label>Message:</label>
                <textarea name="message" rows="5">Test email via MailerSend API. Jika Anda menerima ini, berarti berhasil!</textarea>
            </div>
            
            <button type="submit">Kirim Email via API</button>
        </form>
        
        <?php if (isset($result)): ?>
            <div class="<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <h3>Hasil:</h3>
                <p>HTTP Code: <?php echo $result['http_code']; ?></p>
                <?php if ($result['success']): ?>
                    <p>✅ Email berhasil dikirim via MailerSend API!</p>
                    <p>📨 Cek inbox agung.senen3@gmail.com</p>
                <?php else: ?>
                    <p>❌ Gagal: <?php echo $result['error'] ?: $result['response']; ?></p>
                    
                    <h4>Troubleshooting:</h4>
                    <ul>
                        <li>Pastikan API token valid</li>
                        <li>Pastikan sender email valid (ganti dengan domain terverifikasi)</li>
                        <li>Cek kuota: trial 100 email/hari</li>
                    </ul>
                <?php endif; ?>
                
                <pre><?php print_r($result); ?></pre>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <h3>📝 Kode untuk Integrasi di response.php (Web API)</h3>
        <pre style="background: #f4f4f4; padding: 15px;">
// Di modules/guru/response.php, tambahkan fungsi ini

function sendEmailViaMailerSend($to_email, $to_name, $subject, $html_content, $text_content = '') {
    
    $api_token = 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213';
    
    $data = [
        'from' => [
            'email' => 'MS_UtNHAw@trial-ynl7argjokjl2k8n.mlsender.net', // Ganti dengan domain Anda nanti
            'name' => 'Responsive Message App'
        ],
        'to' => [
            [
                'email' => $to_email,
                'name' => $to_name
            ]
        ],
        'subject' => $subject,
        'html' => $html_content,
        'text' => $text_content ?: strip_tags($html_content)
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailersend.com/v1/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode >= 200 && $httpCode < 300);
}
        </pre>
        
        <h3>📝 Kode untuk Integrasi di response.php (SMTP)</h3>
        <pre style="background: #f4f4f4; padding: 15px;">
// Di modules/guru/response.php, gunakan PHPMailer dengan konfigurasi ini

$mail->isSMTP();
$mail->Host       = 'smtp.mailersend.net';
$mail->Port       = 587;
$mail->SMTPAuth   = true;
$mail->Username   = 'MS_UtNHAw@trial-ynl7argjokjl2k8n.mlsender.net'; // Email sender Anda
$mail->Password   = 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213'; // API token sebagai password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

$mail->setFrom('MS_UtNHAw@trial-ynl7argjokjl2k8n.mlsender.net', 'Responsive Message App');
        </pre>
    </div>
</body>
</html>