<?php
/**
 * TEST EMAIL API
 * File: api/test_email.php
 * 
 * TEST SUPER LENGKAP untuk memastikan email berfungsi dengan baik
 * dan terintegrasi dengan modules/guru/response.php
 * 
 * Fitur:
 * - Test koneksi email dengan berbagai metode
 * - Test pengiriman ke alamat agung.senen3@gmail.com
 * - Debug lengkap dengan log detail
 * - Test integrasi dengan response.php
 */

// Aktifkan error reporting maksimal
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../debug_email.log');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// ============================================================================
// DEBUG FUNCTION - SUPER LENGKAP
// ============================================================================
$debug_steps = [];
$step_counter = 0;

function debug_step($title, $data = null, $type = 'info') {
    global $debug_steps, $step_counter;
    $step_counter++;
    
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'main';
    $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 'unknown';
    
    $current_date = date('Y-m-d H:i:s');
    $microtime = microtime(true);
    
    $debug_steps[] = [
        'step' => $step_counter,
        'time' => date('H:i:s'),
        'microtime' => $microtime,
        'title' => $title,
        'data' => $data,
        'type' => $type,
        'caller' => $caller,
        'line' => $line,
        'date' => $current_date
    ];
    
    // Log ke file - disimpan permanen
    $log_message = "[EMAIL_TEST][{$current_date}][STEP {$step_counter}][{$caller}:{$line}] {$title}";
    if ($data !== null) {
        $log_message .= " - " . print_r($data, true);
    }
    error_log($log_message);
    
    // Simpan juga ke file khusus debug
    $debug_file = __DIR__ . '/../debug_email_test.log';
    $fp = fopen($debug_file, 'a');
    fwrite($fp, $log_message . "\n");
    fclose($fp);
}

// Header HTML untuk tampilan
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email API - Responsive Message App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(145deg, #0b4d8a, #1a73e8);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .navbar h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f2f5;
            padding: 1.2rem 1.5rem;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.4rem;
        }
        .card-body {
            padding: 2rem;
        }
        .test-section {
            background: #f8fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .test-section h4 {
            color: #0b4d8a;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .btn-test {
            background: linear-gradient(145deg, #1a73e8, #0d47a1);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26,115,232,0.4);
            color: white;
        }
        .btn-test:disabled {
            opacity: 0.6;
            transform: none;
        }
        .result-box {
            background: white;
            border-left: 4px solid #1a73e8;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .result-box.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .result-box.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .result-box.warning {
            border-left-color: #ffc107;
            background: #fff9e6;
        }
        .config-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .config-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e2e8f0;
        }
        .config-table td, .config-table th {
            padding: 12px 15px;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .debug-panel {
            background: #1e1e2f;
            border-radius: 15px;
            color: #e0e0e0;
            font-family: 'Consolas', monospace;
            border: 1px solid #2d2d44;
        }
        .debug-header {
            background: #2d2d44;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
            cursor: pointer;
        }
        .debug-content {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        .debug-entry {
            margin-bottom: 15px;
            padding: 12px;
            border-left: 4px solid;
            background: rgba(255,255,255,0.05);
            border-radius: 0 5px 5px 0;
            font-size: 13px;
        }
        .debug-entry.success { border-left-color: #28a745; }
        .debug-entry.error { border-left-color: #dc3545; }
        .debug-entry.warning { border-left-color: #ffc107; }
        .debug-data {
            background: #000;
            padding: 12px;
            border-radius: 5px;
            margin-top: 8px;
            color: #0f0;
            overflow-x: auto;
        }
        .code-block {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-family: 'Consolas', monospace;
            font-size: 13px;
        }
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 12px 25px;
        }
        .nav-tabs .nav-link.active {
            color: #1a73e8;
            border-bottom: 3px solid #1a73e8;
            background: transparent;
        }
        .tab-content {
            padding: 25px 0;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="d-flex align-items-center">
            <i class="fas fa-envelope fa-2x me-3"></i>
            <h1>TEST EMAIL API - SMKN 12 Jakarta</h1>
        </div>
        <div>
            <span class="badge bg-warning p-2">
                <i class="fas fa-bug me-1"></i> DEBUG MODE
            </span>
        </div>
    </div>

    <div class="container">
        <!-- INFO CARD -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Test Email</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="fas fa-envelope me-2"></i>
                            <strong>Target Email:</strong> agung.senen3@gmail.com
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-external-link-alt me-2"></i>
                            <strong>Terintegrasi dengan:</strong> modules/guru/response.php
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Waktu Test:</strong> <?php echo date('d-m-Y H:i:s'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS NAVIGATION -->
        <ul class="nav nav-tabs" id="testTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                    <i class="fas fa-plug me-2"></i>Test Dasar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="integration-tab" data-bs-toggle="tab" data-bs-target="#integration" type="button" role="tab">
                    <i class="fas fa-link me-2"></i>Test Integrasi Response
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="debug-tab" data-bs-toggle="tab" data-bs-target="#debug" type="button" role="tab">
                    <i class="fas fa-bug me-2"></i>Debug Log
                    <span class="badge bg-primary ms-2"><?php echo $step_counter; ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="testTabsContent">
            <!-- TAB 1: TEST DASAR EMAIL -->
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plug me-2 text-primary"></i>Test Dasar Email API</h3>
                    </div>
                    <div class="card-body">
                        <div class="test-section">
                            <h4>1. Test Koneksi Email Server</h4>
                            <p class="text-muted">Memeriksa apakah konfigurasi email berfungsi dengan baik</p>
                            
                            <?php
                            debug_step("TEST KONEKSI EMAIL - Memulai test koneksi");
                            
                            // Cek konfigurasi
                            $config_checks = [
                                'EMAIL_ENABLED' => defined('EMAIL_ENABLED') ? EMAIL_ENABLED : 'Not defined',
                                'EMAIL_USE_SMTP' => defined('EMAIL_USE_SMTP') ? EMAIL_USE_SMTP : 'Not defined',
                                'EMAIL_FROM_EMAIL' => defined('EMAIL_FROM_EMAIL') ? EMAIL_FROM_EMAIL : 'noreply@smkn12jakarta.sch.id',
                                'EMAIL_FROM_NAME' => defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'Responsive Message App',
                                'EMAIL_API_KEYS' => defined('EMAIL_API_KEYS') ? substr(EMAIL_API_KEYS, 0, 20) . '...' : 'Not defined'
                            ];
                            
                            debug_step("Konfigurasi email", $config_checks);
                            ?>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h5>Konfigurasi Saat Ini:</h5>
                                    <table class="config-table">
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Value</th>
                                        </tr>
                                        <?php foreach ($config_checks as $key => $value): ?>
                                        <tr>
                                            <td><?php echo $key; ?></td>
                                            <td>
                                                <?php if ($value === true || $value === 1): ?>
                                                    <span class="badge bg-success">Enabled</span>
                                                <?php elseif ($value === false || $value === 0): ?>
                                                    <span class="badge bg-danger">Disabled</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($value); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST" action="" id="testConnectionForm">
                                        <input type="hidden" name="action" value="test_connection">
                                        <button type="submit" class="btn-test w-100" id="testConnectionBtn">
                                            <i class="fas fa-sync-alt me-2"></i>Test Koneksi Email
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <?php
                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_connection') {
                                debug_step("TEST KONEKSI - Dipicu oleh user");
                                
                                echo '<div class="result-box">';
                                echo '<h5><i class="fas fa-cog me-2"></i>Hasil Test Koneksi:</h5>';
                                
                                // Simulasi test koneksi
                                $mail_function_exists = function_exists('mail');
                                $smtp_available = true; // Ganti dengan cek SMTP jika diperlukan
                                
                                debug_step("Hasil pengecekan fungsi PHP", [
                                    'mail_function_exists' => $mail_function_exists,
                                    'smtp_available' => $smtp_available
                                ]);
                                
                                if ($mail_function_exists) {
                                    echo '<div class="alert alert-success">✓ Fungsi mail() tersedia</div>';
                                } else {
                                    echo '<div class="alert alert-danger">✗ Fungsi mail() tidak tersedia</div>';
                                }
                                
                                if ($config_checks['EMAIL_ENABLED'] === true || $config_checks['EMAIL_ENABLED'] === 1) {
                                    echo '<div class="alert alert-success">✓ Email service enabled</div>';
                                } else {
                                    echo '<div class="alert alert-warning">⚠ Email service disabled - akan menggunakan simulasi</div>';
                                }
                                
                                echo '<p class="mt-3"><strong>Kesimpulan:</strong> ';
                                if ($mail_function_exists) {
                                    echo 'Server siap mengirim email menggunakan fungsi mail()';
                                } else {
                                    echo 'Fungsi mail() tidak tersedia, gunakan SMTP atau simulasi';
                                }
                                echo '</p>';
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                        
                        <div class="test-section">
                            <h4>2. Test Kirim Email Sederhana</h4>
                            <p class="text-muted">Mengirim email test ke agung.senen3@gmail.com</p>
                            
                            <form method="POST" action="" class="row g-3">
                                <input type="hidden" name="action" value="send_simple">
                                <div class="col-md-8">
                                    <input type="email" class="form-control" name="test_email" value="agung.senen3@gmail.com" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn-test w-100" id="sendSimpleBtn">
                                        <i class="fas fa-paper-plane me-2"></i>Kirim Test Email
                                    </button>
                                </div>
                            </form>
                            
                            <?php
                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_simple') {
                                $test_email = $_POST['test_email'] ?? 'agung.senen3@gmail.com';
                                
                                debug_step("TEST KIRIM EMAIL SEDERHANA", [
                                    'to' => $test_email,
                                    'time' => date('Y-m-d H:i:s')
                                ]);
                                
                                echo '<div class="result-box mt-3">';
                                echo '<h5><i class="fas fa-envelope me-2"></i>Hasil Pengiriman:</h5>';
                                
                                $subject = "Test Email dari Responsive Message App - " . date('d/m/Y H:i');
                                $message = "
                                <html>
                                <head>
                                    <title>Test Email</title>
                                </head>
                                <body>
                                    <h2>Test Email dari Responsive Message App</h2>
                                    <p>Halo, ini adalah email test dari sistem Responsive Message App SMKN 12 Jakarta.</p>
                                    <p>Waktu pengiriman: " . date('d-m-Y H:i:s') . "</p>
                                    <p>Jika Anda menerima email ini, berarti fungsi email berjalan dengan baik!</p>
                                    <hr>
                                    <p><small>Email ini dikirim untuk keperluan testing.</small></p>
                                </body>
                                </html>
                                ";
                                
                                // Headers
                                $headers = "MIME-Version: 1.0\r\n";
                                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                                $headers .= "From: Responsive Message App <noreply@smkn12jakarta.sch.id>\r\n";
                                
                                // Kirim email
                                $sent = mail($test_email, $subject, $message, $headers);
                                
                                debug_step("Hasil pengiriman mail()", [
                                    'sent' => $sent,
                                    'error' => error_get_last()
                                ]);
                                
                                if ($sent) {
                                    echo '<div class="alert alert-success">';
                                    echo '<i class="fas fa-check-circle me-2"></i>';
                                    echo 'Email berhasil dikirim ke ' . htmlspecialchars($test_email);
                                    echo '</div>';
                                    
                                    echo '<div class="alert alert-info mt-2">';
                                    echo '<i class="fas fa-info-circle me-2"></i>';
                                    echo 'Cek folder inbox atau spam di ' . htmlspecialchars($test_email);
                                    echo '</div>';
                                } else {
                                    echo '<div class="alert alert-danger">';
                                    echo '<i class="fas fa-exclamation-circle me-2"></i>';
                                    echo 'Gagal mengirim email. Error: ' . print_r(error_get_last(), true);
                                    echo '</div>';
                                    
                                    // Tawarkan simulasi
                                    echo '<div class="alert alert-warning">';
                                    echo '<i class="fas fa-sim-card me-2"></i>';
                                    echo 'Menggunakan mode SIMULASI: Email akan dicatat dalam log.';
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                        
                        <div class="test-section">
                            <h4>3. Test API Endpoint email.php</h4>
                            <p class="text-muted">Menguji endpoint API email.php dengan berbagai action</p>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-primary w-100" onclick="testApiEndpoint('status')">
                                        <i class="fas fa-heartbeat me-2"></i>Test Status
                                    </button>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-success w-100" onclick="testApiEndpoint('test')">
                                        <i class="fas fa-flask me-2"></i>Test Connection
                                    </button>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-warning w-100" onclick="testApiEndpoint('send_test')">
                                        <i class="fas fa-paper-plane me-2"></i>Test Send
                                    </button>
                                </div>
                            </div>
                            
                            <div id="apiResult" class="result-box mt-3" style="display: none;">
                                <h5><i class="fas fa-code me-2"></i>API Response:</h5>
                                <pre id="apiResponseData" class="code-block mt-2"></pre>
                            </div>
                            
                            <div class="mt-3">
                                <h5>Endpoint URL:</h5>
                                <div class="code-block">
                                    <?php 
                                    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/responsive-message-app/api/email.php';
                                    echo htmlspecialchars($base_url); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB 2: TEST INTEGRASI DENGAN RESPONSE.PHP -->
            <div class="tab-pane fade" id="integration" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-link me-2 text-primary"></i>Test Integrasi dengan modules/guru/response.php</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        debug_step("TEST INTEGRASI - Memulai test integrasi dengan response.php");
                        
                        // Cek apakah ada data pesan untuk test
                        try {
                            $db = Database::getInstance()->getConnection();
                            
                            debug_step("Database connection", ['connected' => !empty($db)]);
                            
                            // Cari pesan external terbaru untuk test
                            $stmt = $db->prepare("
                                SELECT m.*, es.nama_lengkap, es.email, es.phone_number
                                FROM messages m
                                LEFT JOIN external_senders es ON m.external_sender_id = es.id
                                WHERE m.is_external = 1 
                                AND (es.email = 'agung.senen3@gmail.com' OR es.email IS NOT NULL)
                                ORDER BY m.created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute();
                            $test_message = $stmt->fetch();
                            
                            debug_step("Mencari pesan test", [
                                'found' => !empty($test_message),
                                'data' => $test_message
                            ]);
                            
                            if (!$test_message) {
                                // Buat pesan test jika tidak ada
                                debug_step("Membuat pesan test baru", [], 'warning');
                                
                                // Cek external sender
                                $senderStmt = $db->prepare("
                                    SELECT id FROM external_senders WHERE email = 'agung.senen3@gmail.com'
                                ");
                                $senderStmt->execute();
                                $sender = $senderStmt->fetch();
                                
                                if (!$sender) {
                                    debug_step("Membuat external sender baru");
                                    
                                    $insertSender = $db->prepare("
                                        INSERT INTO external_senders (nama_lengkap, email, phone_number, identitas, created_at)
                                        VALUES ('Agung Test', 'agung.senen3@gmail.com', '08129469754', 'External Test', NOW())
                                    ");
                                    $insertSender->execute();
                                    $sender_id = $db->lastInsertId();
                                    
                                    debug_step("External sender created", ['sender_id' => $sender_id]);
                                } else {
                                    $sender_id = $sender['id'];
                                }
                                
                                // Get message type ID
                                $typeStmt = $db->prepare("SELECT id FROM message_types WHERE jenis_pesan LIKE '%Konsultasi%' LIMIT 1");
                                $typeStmt->execute();
                                $type = $typeStmt->fetch();
                                $type_id = $type ? $type['id'] : 1;
                                
                                // Generate reference number
                                $ref = 'EXT-TEST-' . date('Ymd') . '-' . rand(1000, 9999);
                                
                                // Buat pesan test
                                $insertMsg = $db->prepare("
                                    INSERT INTO messages (
                                        reference_number, external_sender_id, jenis_pesan_id, 
                                        is_external, isi_pesan, priority, status, 
                                        created_at, ip_address, user_agent
                                    ) VALUES (
                                        :ref, :sender_id, :type_id,
                                        1, :isi, 'Medium', 'Pending',
                                        NOW(), '127.0.0.1', 'Test Script'
                                    )
                                ");
                                $insertMsg->execute([
                                    ':ref' => $ref,
                                    ':sender_id' => $sender_id,
                                    ':type_id' => $type_id,
                                    ':isi' => 'Ini adalah pesan test untuk menguji integrasi email. Mohon direspons.'
                                ]);
                                
                                $message_id = $db->lastInsertId();
                                
                                debug_step("Pesan test dibuat", [
                                    'message_id' => $message_id,
                                    'reference' => $ref
                                ]);
                                
                                // Ambil ulang data pesan
                                $stmt = $db->prepare("
                                    SELECT m.*, es.nama_lengkap, es.email, es.phone_number
                                    FROM messages m
                                    LEFT JOIN external_senders es ON m.external_sender_id = es.id
                                    WHERE m.id = :id
                                ");
                                $stmt->execute([':id' => $message_id]);
                                $test_message = $stmt->fetch();
                            }
                            
                            debug_step("Data pesan untuk test", [
                                'message_id' => $test_message['id'],
                                'reference' => $test_message['reference_number'],
                                'nama' => $test_message['nama_lengkap'],
                                'email' => $test_message['email'],
                                'phone' => $test_message['phone_number']
                            ]);
                            ?>
                            
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Data Pesan Test:</strong>
                                <ul class="mt-2 mb-0">
                                    <li><strong>ID Pesan:</strong> <?php echo $test_message['id']; ?></li>
                                    <li><strong>Referensi:</strong> <?php echo $test_message['reference_number']; ?></li>
                                    <li><strong>Pengirim:</strong> <?php echo htmlspecialchars($test_message['nama_lengkap']); ?></li>
                                    <li><strong>Email:</strong> <?php echo htmlspecialchars($test_message['email']); ?></li>
                                    <li><strong>WhatsApp:</strong> <?php echo htmlspecialchars($test_message['phone_number']); ?></li>
                                </ul>
                            </div>
                            
                            <div class="test-section">
                                <h4>1. Test Kirim Respons Manual</h4>
                                <p class="text-muted">Mengirim respons melalui modules/guru/response.php</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5><i class="fas fa-envelope me-2 text-primary"></i>Test Email Notification</h5>
                                                <form method="POST" action="../modules/guru/response.php?id=<?php echo $test_message['id']; ?>" target="_blank">
                                                    <input type="hidden" name="action" value="submit_response">
                                                    <input type="hidden" name="message_id" value="<?php echo $test_message['id']; ?>">
                                                    <input type="hidden" name="status" value="Disetujui">
                                                    <input type="hidden" name="catatan_respon" value="Test respons dari integrasi test. Email akan dikirim ke agung.senen3@gmail.com">
                                                    
                                                    <button type="submit" class="btn-test w-100 mb-3">
                                                        <i class="fas fa-paper-plane me-2"></i>Kirim Respons & Email
                                                    </button>
                                                </form>
                                                
                                                <p class="small text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Akan membuka halaman response.php di tab baru
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5><i class="fab fa-whatsapp me-2 text-success"></i>Test WhatsApp Notification</h5>
                                                <form method="POST" action="../modules/guru/response.php?id=<?php echo $test_message['id']; ?>" target="_blank">
                                                    <input type="hidden" name="action" value="submit_response">
                                                    <input type="hidden" name="message_id" value="<?php echo $test_message['id']; ?>">
                                                    <input type="hidden" name="status" value="Diproses">
                                                    <input type="hidden" name="catatan_respon" value="Test WhatsApp notification ke 08129469754">
                                                    
                                                    <button type="submit" class="btn-test w-100 mb-3" style="background: linear-gradient(145deg, #25D366, #128C7E);">
                                                        <i class="fab fa-whatsapp me-2"></i>Kirim Respons & WA
                                                    </button>
                                                </form>
                                                
                                                <p class="small text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Akan mengirim notifikasi ke 08129469754
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="test-section">
                                <h4>2. Test Quick Action (Approve/Reject)</h4>
                                <p class="text-muted">Menguji quick action yang juga mengirim notifikasi</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <form method="POST" action="../modules/guru/followup.php" target="_blank">
                                            <input type="hidden" name="action" value="quick_approve">
                                            <input type="hidden" name="message_id" value="<?php echo $test_message['id']; ?>">
                                            
                                            <button type="submit" class="btn btn-success w-100 mb-3 p-3">
                                                <i class="fas fa-check-circle me-2"></i>
                                                Quick Approve (dengan notifikasi)
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="POST" action="../modules/guru/followup.php" target="_blank">
                                            <input type="hidden" name="action" value="quick_reject">
                                            <input type="hidden" name="message_id" value="<?php echo $test_message['id']; ?>">
                                            
                                            <button type="submit" class="btn btn-danger w-100 mb-3 p-3">
                                                <i class="fas fa-times-circle me-2"></i>
                                                Quick Reject (dengan notifikasi)
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="test-section">
                                <h4>3. Test API Langsung ke email.php</h4>
                                <p class="text-muted">Mengirim request langsung ke API endpoint</p>
                                
                                <div class="code-block">
                                    <pre>POST /responsive-message-app/api/email.php?action=send_response
Headers:
  X-API-Key: [your-api-key]
  Content-Type: application/json

Body:
{
    "to": "agung.senen3@gmail.com",
    "message_id": <?php echo $test_message['id']; ?>,
    "response_id": 1,
    "message": "Test message from API"
}</pre>
                                </div>
                                
                                <button class="btn-test mt-3" onclick="testEmailApiDirect()">
                                    <i class="fas fa-bolt me-2"></i>Test API Direct
                                </button>
                                
                                <div id="directApiResult" class="result-box mt-3" style="display: none;"></div>
                            </div>
                            
                        <?php 
                        } catch (Exception $e) {
                            debug_step("ERROR dalam test integrasi", [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ], 'error');
                            
                            echo '<div class="alert alert-danger">';
                            echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                            echo 'Error: ' . htmlspecialchars($e->getMessage());
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- TAB 3: DEBUG LOG -->
            <div class="tab-pane fade" id="debug" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bug me-2 text-primary"></i>Debug Log Lengkap</h3>
                    </div>
                    <div class="card-body">
                        <div class="debug-panel">
                            <div class="debug-header" onclick="toggleDebugContent()">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-white">
                                        <i class="fas fa-list me-2"></i>
                                        DEBUG STEPS - <?php echo count($debug_steps); ?> entries
                                    </h5>
                                    <i class="fas fa-chevron-down text-white" id="debugToggleIcon"></i>
                                </div>
                            </div>
                            <div class="debug-content" id="debugContent">
                                <?php foreach ($debug_steps as $step): ?>
                                    <div class="debug-entry <?php echo $step['type']; ?>">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>
                                                <span class="debug-step">STEP <?php echo str_pad($step['step'], 2, '0', STR_PAD_LEFT); ?></span>
                                                <span class="debug-message"><?php echo htmlspecialchars($step['title']); ?></span>
                                            </span>
                                            <span class="debug-time"><?php echo $step['time']; ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                            <span class="debug-caller"><?php echo $step['caller']; ?>:<?php echo $step['line']; ?></span>
                                        </div>
                                        <?php if ($step['data'] !== null): ?>
                                            <div class="debug-data">
                                                <pre style="margin:0; color: #0f0;"><?php echo htmlspecialchars(print_r($step['data'], true)); ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Log File:</h5>
                            <div class="code-block">
                                <strong>debug_email_test.log</strong><br>
                                <small class="text-muted">Lokasi: <?php echo __DIR__ . '/../debug_email_test.log'; ?></small>
                            </div>
                            <button class="btn btn-outline-primary mt-2" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Debug
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="text-center mt-4 text-muted">
            <small>
                <i class="fas fa-code me-1"></i>
                Responsive Message App - SMKN 12 Jakarta | Test Email API v1.0
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle debug content
        function toggleDebugContent() {
            const content = document.getElementById('debugContent');
            const icon = document.getElementById('debugToggleIcon');
            
            if (content) {
                if (content.style.display === 'none' || content.style.display === '') {
                    content.style.display = 'block';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    content.style.display = 'none';
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        }
        
        // Test API endpoint
        function testApiEndpoint(action) {
            const resultDiv = document.getElementById('apiResult');
            const responsePre = document.getElementById('apiResponseData');
            
            resultDiv.style.display = 'block';
            responsePre.innerHTML = 'Loading...';
            
            let url = 'email.php?action=' + action;
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-API-Key': 'test_key_123',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                responsePre.innerHTML = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                responsePre.innerHTML = 'Error: ' + error.message;
            });
        }
        
        // Test API direct
        function testEmailApiDirect() {
            const resultDiv = document.getElementById('directApiResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="alert alert-info">Mengirim request... <i class="fas fa-spinner fa-spin ms-2"></i></div>';
            
            const data = {
                to: 'agung.senen3@gmail.com',
                message_id: <?php echo isset($test_message) ? $test_message['id'] : 0; ?>,
                response_id: 1,
                message: 'Test message from direct API call at ' + new Date().toISOString()
            };
            
            fetch('email.php?action=send_response', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': 'test_key_123'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Email sent successfully!
                            <pre class="mt-2 mb-0">${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Email sending failed (simulasi mode)
                            <pre class="mt-2 mb-0">${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Error: ${error.message}
                    </div>
                `;
            });
        }
        
        // Auto-hide loading buttons
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                }
            });
        });
    </script>
</body>
</html>
<?php
debug_step("TEST SELESAI", [
    'total_steps' => $step_counter,
    'end_time' => date('Y-m-d H:i:s')
]);
?>