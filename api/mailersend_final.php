<?php
/**
 * MailerSend Final - Menggunakan Trial Domain
 * File: api/mailersend_final.php
 * 
 * Domain: test-q3enl6k0o7542vwr.mlsender.net
 * Domain ID: 3m5jgro6voxgdpyo
 * API Token: mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Konfigurasi MailerSend
$config = [
    'api_token' => 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213',
    'domain' => 'test-q3enl6k0o7542vwr.mlsender.net',
    'domain_id' => '3m5jgro6voxgdpyo',
    'from_email' => 'noreply@test-q3enl6k0o7542vwr.mlsender.net',
    'from_name' => 'Responsive Message App'
];

// Fungsi kirim email via MailerSend API
function sendMailerSendEmail($to_email, $to_name, $subject, $html_content, $text_content = '') {
    global $config;
    
    $data = [
        'from' => [
            'email' => $config['from_email'],
            'name' => $config['from_name']
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
            'Authorization: Bearer ' . $config['api_token'],
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
    $subject = $_POST['subject'] ?? 'Test MailerSend - ' . date('Y-m-d H:i:s');
    $message = $_POST['message'] ?? 'Test email via MailerSend dengan trial domain';
    
    // Buat HTML content yang bagus
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0b4d8a; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; }
            .footer { background: #e9ecef; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>SMKN 12 Jakarta</h2>
                <p>Responsive Message App</p>
            </div>
            <div class='content'>
                <h3>Halo {$to_name},</h3>
                <p>{$message}</p>
                <p>Waktu: " . date('d/m/Y H:i:s') . "</p>
                <hr>
                <p><small>Email ini dikirim via MailerSend dengan trial domain.</small></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " SMKN 12 Jakarta. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $result = sendMailerSendEmail($to_email, $to_name, $subject, $html_content, $message);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MailerSend Final - Responsive Message App</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #0b4d8a; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #dc3545; }
        .config-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px dashed #0b4d8a; }
        .config-item { margin-bottom: 10px; font-family: monospace; }
        .config-label { font-weight: bold; color: #0b4d8a; display: inline-block; width: 120px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 MailerSend Integration - FINAL</h1>
        
        <div class="info">
            <strong>✅ Trial Domain Aktif:</strong> <code>test-q3enl6k0o7542vwr.mlsender.net</code>
        </div>
        
        <div class="config-box">
            <h4>🔧 Konfigurasi Saat Ini:</h4>
            <div class="config-item">
                <span class="config-label">API Token:</span> 
                <code>mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213</code>
            </div>
            <div class="config-item">
                <span class="config-label">Domain:</span> 
                <code>test-q3enl6k0o7542vwr.mlsender.net</code>
            </div>
            <div class="config-item">
                <span class="config-label">Domain ID:</span> 
                <code>3m5jgro6voxgdpyo</code>
            </div>
            <div class="config-item">
                <span class="config-label">From Email:</span> 
                <code>noreply@test-q3enl6k0o7542vwr.mlsender.net</code>
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>📨 To Email:</label>
                <input type="email" name="to_email" value="agung.senen3@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label>👤 To Name:</label>
                <input type="text" name="to_name" value="Agung">
            </div>
            
            <div class="form-group">
                <label>📝 Subject:</label>
                <input type="text" name="subject" value="Test MailerSend - <?php echo date('Y-m-d H:i:s'); ?>">
            </div>
            
            <div class="form-group">
                <label>💬 Message:</label>
                <textarea name="message" rows="5">Test email via MailerSend dengan trial domain. Jika Anda menerima email ini, berarti integrasi BERHASIL!</textarea>
            </div>
            
            <button type="submit">🚀 Kirim Email Test</button>
        </form>
        
        <?php if (isset($result)): ?>
            <div class="<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <h3>Hasil Pengiriman:</h3>
                <?php if ($result['success']): ?>
                    <p style="font-size: 16px;">✅ <strong>BERHASIL!</strong> Email terkirim ke <strong><?php echo htmlspecialchars($_POST['to_email']); ?></strong></p>
                    <p>📨 Cek inbox atau folder spam di Gmail Anda.</p>
                <?php else: ?>
                    <p style="font-size: 16px;">❌ <strong>GAGAL:</strong> <?php echo htmlspecialchars($result['error'] ?: $result['response']); ?></p>
                    
                    <?php if (strpos($result['response'], 'verified') !== false): ?>
                        <p><strong>Tips:</strong> Domain trial seharusnya sudah verified. Coba refresh halaman.</p>
                    <?php elseif (strpos($result['response'], 'limit') !== false): ?>
                        <p><strong>Tips:</strong> Trial hanya 100 email/hari. Tunggu reset.</p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <details>
                    <summary>📋 Detail Response</summary>
                    <pre><?php print_r($result); ?></pre>
                </details>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <h3>📝 Kode untuk Integrasi di modules/guru/response.php</h3>
        <pre style="background: #f4f4f4; padding: 15px;">
// ============================================================================
// KONFIGURASI MAILERSEND UNTUK RESPONSE.PHP
// ============================================================================

// Tambahkan di bagian atas file response.php (setelah require_once)
define('MAILERSEND_API_TOKEN', 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213');
define('MAILERSEND_DOMAIN', 'test-q3enl6k0o7542vwr.mlsender.net');
define('MAILERSEND_FROM_EMAIL', 'noreply@test-q3enl6k0o7542vwr.mlsender.net');
define('MAILERSEND_FROM_NAME', 'Responsive Message App');

// Fungsi untuk mengirim email via MailerSend
function sendMailerSendEmail($to_email, $to_name, $subject, $html_content) {
    
    $data = [
        'from' => [
            'email' => MAILERSEND_FROM_EMAIL,
            'name' => MAILERSEND_FROM_NAME
        ],
        'to' => [
            [
                'email' => $to_email,
                'name' => $to_name
            ]
        ],
        'subject' => $subject,
        'html' => $html_content
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailersend.com/v1/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MAILERSEND_API_TOKEN,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode >= 200 && $httpCode < 300);
}

// Gunakan di fungsi sendEmailNotification yang sudah ada
function sendEmailNotification($email, $nama, $reference, $status, $catatan, $guru_nama, $guru_type, $guru_email = '') {
    
    // Buat HTML email
    $html = "&lt;h2&gt;Respons Pesan #{$reference}&lt;/h2&gt;...";
    
    $subject = "Respons Pesan #{$reference} - SMKN 12 Jakarta";
    
    $sent = sendMailerSendEmail($email, $nama, $subject, $html);
    
    return ['success' => $sent, 'sent' => $sent];
}
        </pre>
        
        <div class="info">
            <h4>📊 Status Akun Trial MailerSend:</h4>
            <ul>
                <li><strong>Domain:</strong> ✅ Terverifikasi (trial domain)</li>
                <li><strong>API Token:</strong> ✅ Aktif</li>
                <li><strong>Kuota:</strong> 100 email/hari (trial)</li>
                <li><strong>Recipient Limit:</strong> 5 unique recipients</li>
            </ul>
        </div>
    </div>
</body>
</html>