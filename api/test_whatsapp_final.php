<?php
/**
 * TEST WHATSAPP API FINAL
 * File: api/test_whatsapp_final.php
 * 
 * TEST SUPER LENGKAP untuk memastikan WhatsApp berfungsi dengan baik
 * Menggunakan API Fonnte
 * 
 * Konfigurasi:
 * - Token: FS2cq8FckmaTegxtZpFB
 * - Perangkat Terhubung: 6285174207795 (085174207795)
 * - Penerima: 08129469754
 */

// Aktifkan error reporting maksimal
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/whatsapp_test.log');

// Buat direktori logs jika belum ada
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Konfigurasi WhatsApp - PERANGKAT SUDAH TERHUBUNG!
define('WHATSAPP_TOKEN', 'FS2cq8FckmaTegxtZpFB');
define('WHATSAPP_DEVICE', '6285174207795'); // Nomor perangkat terhubung (085174207795)
define('WHATSAPP_TARGET', '08129469754'); // Nomor tujuan/penerima
define('WHATSAPP_API_URL', 'https://api.fonnte.com/send');

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
    
    $debug_steps[] = [
        'step' => $step_counter,
        'time' => date('H:i:s'),
        'title' => $step['title'] ?? $title,
        'data' => $step['data'] ?? $data,
        'type' => $step['type'] ?? $type,
        'caller' => $caller,
        'line' => $line,
        'date' => $current_date
    ];
    
    // Log ke file
    $log_message = "[WHATSAPP_TEST][{$current_date}][STEP {$step_counter}][{$caller}:{$line}] {$title}";
    if ($data !== null) {
        $log_message .= " - " . print_r($data, true);
    }
    error_log($log_message);
    
    $debug_file = __DIR__ . '/../logs/whatsapp_test.log';
    file_put_contents($debug_file, $log_message . "\n", FILE_APPEND);
}

// Fungsi kirim WhatsApp via Fonnte
function sendFonnteMessage($phone, $message) {
    // Format nomor tujuan
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    $postData = [
        'target' => $phone,
        'message' => $message,
        'countryCode' => '62'
    ];
    
    debug_step("Mengirim ke Fonnte", [
        'dari_device' => WHATSAPP_DEVICE,
        'ke_nomor' => $phone,
        'message_length' => strlen($message)
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => WHATSAPP_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . WHATSAPP_TOKEN
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300 && isset($response_data['status']) && $response_data['status']),
        'http_code' => $httpCode,
        'response' => $response,
        'response_data' => $response_data,
        'curl_error' => $curlError
    ];
}

// Fungsi cek status perangkat
function checkDeviceStatus() {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/get-devices',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . WHATSAPP_TOKEN
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Header HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp API - SMKN 12 Jakarta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(145deg, #075E54, #128C7E);
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
            color: #075E54;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .btn-test {
            background: linear-gradient(145deg, #25D366, #128C7E);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37,211,102,0.4);
            color: white;
        }
        .btn-test-warning {
            background: linear-gradient(145deg, #FFA500, #FF8C00);
        }
        .btn-test-danger {
            background: linear-gradient(145deg, #dc3545, #c82333);
        }
        .btn-test:disabled {
            opacity: 0.6;
            transform: none;
        }
        .result-box {
            background: white;
            border-left: 4px solid #25D366;
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
            width: 100%;
        }
        .config-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 15px;
        }
        .config-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .config-table tr:last-child td {
            border-bottom: none;
        }
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
            display: none;
        }
        .debug-content.show {
            display: block;
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
        .phone-input {
            font-size: 1.2rem;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
        }
        .phone-input:focus {
            border-color: #25D366;
            box-shadow: 0 0 0 0.2rem rgba(37,211,102,0.25);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 12px 25px;
        }
        .nav-tabs .nav-link.active {
            color: #25D366;
            border-bottom: 3px solid #25D366;
            background: transparent;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .token-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            border: 1px solid #dee2e6;
            word-break: break-all;
        }
        .device-status {
            background: #e8f5e9;
            border: 1px solid #25D366;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .device-status h5 {
            color: #075E54;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
        }
        .status-badge.connected {
            background: #25D366;
            color: white;
        }
        .status-badge.disconnected {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="d-flex align-items-center">
            <i class="fab fa-whatsapp fa-2x me-3"></i>
            <h1>TEST WHATSAPP API - SMKN 12 Jakarta</h1>
        </div>
        <div>
            <span class="badge bg-light text-dark p-2 me-2">
                <i class="fas fa-phone-alt me-1"></i> Device: 085174207795
            </span>
            <span class="badge bg-success p-2">
                <i class="fas fa-check-circle me-1"></i> Target: 08129469754
            </span>
        </div>
    </div>

    <div class="container">
        <!-- STATUS PERANGKAT -->
        <div class="device-status">
            <div class="row">
                <div class="col-md-8">
                    <h5><i class="fas fa-mobile-alt me-2"></i>Status Perangkat WhatsApp</h5>
                    <p>
                        <span class="status-badge connected">
                            <i class="fas fa-check-circle me-1"></i> TERHUBUNG
                        </span>
                        <span class="ms-3">
                            <strong>Nomor:</strong> 6285174207795 (085174207795)
                        </span>
                    </p>
                    <p class="mb-0">
                        <strong>Token:</strong> <code><?php echo substr(WHATSAPP_TOKEN, 0, 5) . '...' . substr(WHATSAPP_TOKEN, -5); ?></code><br>
                        <strong>Paket:</strong> Free (1.000 pesan/bulan) - Aktif sampai 17 Maret 2026
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="https://api.fonnte.com/connect" target="_blank" class="btn btn-success">
                        <i class="fas fa-qrcode me-2"></i>QR Code
                    </a>
                </div>
            </div>
        </div>

        <!-- TABS NAVIGATION -->
        <ul class="nav nav-tabs" id="testTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="send-tab" data-bs-toggle="tab" data-bs-target="#send" type="button" role="tab">
                    <i class="fas fa-paper-plane me-2"></i>Kirim Pesan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                    <i class="fas fa-file-alt me-2"></i>Template
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="debug-tab" data-bs-toggle="tab" data-bs-target="#debug" type="button" role="tab">
                    <i class="fas fa-bug me-2"></i>Debug Log
                </button>
            </li>
        </ul>

        <div class="tab-content" id="testTabsContent">
            <!-- TAB 1: KIRIM PESAN -->
            <div class="tab-pane fade show active" id="send" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-paper-plane me-2 text-success"></i>Kirim Pesan WhatsApp</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_wa'])) {
                            $phone = $_POST['phone'] ?? WHATSAPP_TARGET;
                            $message = $_POST['message'] ?? '';
                            
                            $result = sendFonnteMessage($phone, $message);
                            
                            echo '<div class="result-box ' . ($result['success'] ? 'success' : 'error') . '">';
                            echo '<h5><i class="fab fa-whatsapp me-2"></i>Hasil Pengiriman:</h5>';
                            
                            if ($result['success']) {
                                echo '<div class="alert alert-success">';
                                echo '<i class="fas fa-check-circle me-2"></i>';
                                echo '✅ PESAN BERHASIL DIKIRIM ke ' . htmlspecialchars($phone) . '!';
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-danger">';
                                echo '<i class="fas fa-times-circle me-2"></i>';
                                echo '❌ GAGAL MENGIRIM!<br>';
                                echo 'Response: ' . htmlspecialchars($result['response']);
                                if ($result['response_data'] && isset($result['response_data']['reason'])) {
                                    echo '<br>Reason: ' . $result['response_data']['reason'];
                                }
                                echo '</div>';
                            }
                            
                            echo '<details>';
                            echo '<summary>Debug Info</summary>';
                            echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';
                            echo '</details>';
                            echo '</div>';
                        }
                        ?>
                        
                        <div class="test-section">
                            <h4>Form Kirim Pesan</h4>
                            <form method="POST" action="">
                                <input type="hidden" name="send_wa" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Dari Nomor (Device):</label>
                                    <input type="text" class="form-control" value="085174207795" readonly disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Ke Nomor Tujuan:</label>
                                    <input type="text" class="form-control phone-input" name="phone" value="08129469754" required>
                                    <small class="text-muted">Format: 08129469754 (akan dikonversi ke 628129469754)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Pesan:</label>
                                    <textarea class="form-control" name="message" rows="6" required>🔔 *NOTIFIKASI WHATSAPP - SMKN 12 Jakarta*

Yth. Bapak/Ibu,

Ini adalah pesan test dari sistem SMKN 12 Jakarta.
Dikirim dari nomor: 085174207795
Waktu: <?php echo date('d/m/Y H:i:s'); ?>

Pesan ini dikirim menggunakan API Fonnte.
Jika Anda menerima pesan ini, berarti integrasi WhatsApp BERHASIL!

Terima kasih.</textarea>
                                </div>
                                
                                <button type="submit" class="btn-test w-100 p-3">
                                    <i class="fab fa-whatsapp me-2 fa-lg"></i>
                                    KIRIM PESAN SEKARANG
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB 2: TEMPLATE -->
            <div class="tab-pane fade" id="templates" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt me-2 text-success"></i>Template Pesan Cepat</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">✅ Disetujui</h5>
                                        <p class="card-text small">Template untuk status DISETUJUI</p>
                                        <button class="btn btn-success w-100" onclick="copyTemplate('approved')">
                                            <i class="fas fa-copy me-2"></i>Salin Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-danger">❌ Ditolak</h5>
                                        <p class="card-text small">Template untuk status DITOLAK</p>
                                        <button class="btn btn-danger w-100" onclick="copyTemplate('rejected')">
                                            <i class="fas fa-copy me-2"></i>Salin Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-warning">⚙️ Diproses</h5>
                                        <p class="card-text small">Template untuk status DIPROSES</p>
                                        <button class="btn btn-warning w-100" onclick="copyTemplate('processing')">
                                            <i class="fas fa-copy me-2"></i>Salin Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-info">🏁 Selesai</h5>
                                        <p class="card-text small">Template untuk status SELESAI</p>
                                        <button class="btn btn-info w-100" onclick="copyTemplate('completed')">
                                            <i class="fas fa-copy me-2"></i>Salin Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Template akan otomatis tersalin ke clipboard. Paste di form pesan.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TAB 3: DEBUG LOG -->
            <div class="tab-pane fade" id="debug" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bug me-2 text-success"></i>Debug Log</h3>
                    </div>
                    <div class="card-body">
                        <div class="debug-panel">
                            <div class="debug-header" onclick="toggleDebug()">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-white">
                                        <i class="fas fa-list me-2"></i>
                                        DEBUG STEPS - <?php echo count($debug_steps); ?> entries
                                    </h5>
                                    <i class="fas fa-chevron-down text-white" id="debugIcon"></i>
                                </div>
                            </div>
                            <div class="debug-content" id="debugContent">
                                <?php foreach ($debug_steps as $step): ?>
                                    <div class="debug-entry <?php echo $step['type']; ?>">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <span class="debug-step">STEP <?php echo str_pad($step['step'], 2, '0', STR_PAD_LEFT); ?></span>
                                                <span class="debug-message"><?php echo htmlspecialchars($step['title']); ?></span>
                                            </span>
                                            <span class="debug-time"><?php echo $step['time']; ?></span>
                                        </div>
                                        <div class="debug-caller"><?php echo $step['caller']; ?>:<?php echo $step['line']; ?></div>
                                        <?php if ($step['data'] !== null): ?>
                                            <div class="debug-data">
                                                <pre style="margin:0;"><?php echo htmlspecialchars(print_r($step['data'], true)); ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDebug() {
            const content = document.getElementById('debugContent');
            const icon = document.getElementById('debugIcon');
            content.classList.toggle('show');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
        
        function copyTemplate(type) {
            const now = new Date().toLocaleString('id-ID');
            const templates = {
                'approved': `✅ *PESAN DISETUJUI* ✅\n\nYth. Bapak/Ibu,\n\nPesan Anda dengan nomor referensi #REF-001 telah DISETUJUI.\n\nTerima kasih telah menghubungi SMKN 12 Jakarta.\nWaktu: ${now}\n\nDikirim dari: 085174207795`,
                
                'rejected': `❌ *PESAN DITOLAK* ❌\n\nYth. Bapak/Ibu,\n\nPesan Anda dengan nomor referensi #REF-001 telah DITOLAK.\n\nSilakan hubungi kami untuk informasi lebih lanjut.\nWaktu: ${now}\n\nDikirim dari: 085174207795`,
                
                'processing': `⚙️ *PESAN DIPROSES* ⚙️\n\nYth. Bapak/Ibu,\n\nPesan Anda dengan nomor referensi #REF-001 sedang DIPROSES.\n\nTim kami akan segera menghubungi Anda.\nWaktu: ${now}\n\nDikirim dari: 085174207795`,
                
                'completed': `🏁 *PESAN SELESAI* 🏁\n\nYth. Bapak/Ibu,\n\nPesan Anda dengan nomor referensi #REF-001 telah SELESAI.\n\nTerima kasih telah menggunakan layanan SMKN 12 Jakarta.\nWaktu: ${now}\n\nDikirim dari: 085174207795`
            };
            
            navigator.clipboard.writeText(templates[type]).then(() => {
                alert('Template berhasil disalin!');
            }).catch(() => {
                alert('Gagal menyalin. Silakan copy manual.');
            });
        }
    </script>
</body>
</html>
<?php
debug_step("TEST SELESAI", ['total_steps' => $step_counter]);
?>