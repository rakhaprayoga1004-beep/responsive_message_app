<?php
/**
 * Monitor Status Koneksi Layanan Eksternal
 * File: responsive-message-app/monitor_connection.php
 * 
 * Skrip independen untuk memonitor koneksi ke:
 * - WhatsApp Gateway (Fonnte)
 * - Email Gateway (MailerSend)
 * 
 * Indikator Warna:
 * 🟢 Hijau   : Koneksi stabil sekali (response time < 1 detik)
 * 🟡 Kuning  : Koneksi rentan disconnect (response time 1-3 detik atau device bermasalah)
 * 🔴 Merah   : Koneksi putus/error (response time > 3 detik atau gagal terkoneksi)
 */

// ============================================================================
// KONFIGURASI WHATSAPP (FONNTE)
// ============================================================================
define('WHATSAPP_TOKEN', 'FS2cq8FckmaTegxtZpFB');
define('WHATSAPP_DEVICE', '6285174207795');
define('WHATSAPP_API_URL', 'https://api.fonnte.com/send');

// ============================================================================
// KONFIGURASI MAILERSEND
// ============================================================================
define('MAILERSEND_API_TOKEN', 'mlsn.3e45cf08196a635e719ea8d30741e4dd2c8ae359015e8c565c257f1a03da5213');
define('MAILERSEND_DOMAIN', 'test-q3enl6k0o7542vwr.mlsender.net');
define('MAILERSEND_DOMAIN_ID', '3m5jgro6voxgdpyo');
define('MAILERSEND_FROM_EMAIL', 'noreply@test-q3enl6k0o7542vwr.mlsender.net');
define('MAILERSEND_FROM_NAME', 'Responsive Message App');

// ============================================================================
// FUNGSI LOGGING (OPSIONAL)
// ============================================================================
function writeLog($service, $message, $data = null) {
    $log_dir = __DIR__ . '/logs';
    
    // Buat direktori logs jika belum ada
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/' . $service . '_monitor_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log_message .= " - " . print_r($data, true);
        } else {
            $log_message .= " - $data";
        }
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// ============================================================================
// FUNGSI TEST KONEKSI FONNTE
// ============================================================================
function testFonnteConnection() {
    $result = [
        'status' => 'unknown',
        'color' => 'secondary',
        'message' => 'Belum diuji',
        'response_time' => 0,
        'http_code' => 0,
        'device_status' => 'unknown',
        'device_name' => '-',
        'details' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    writeLog('whatsapp', 'Menguji koneksi Fonnte...');
    
    try {
        $start = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.fonnte.com/device',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . WHATSAPP_TOKEN
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        curl_close($ch);
        
        $result['response_time'] = $responseTime;
        $result['http_code'] = $httpCode;
        
        if ($response) {
            $data = json_decode($response, true);
            $result['details'] = $data;
            
            // Cek status device
            if (isset($data['data']['device_status'])) {
                $result['device_status'] = $data['data']['device_status'];
                $result['device_name'] = $data['data']['device_name'] ?? 'Unknown';
                
                // Tentukan status berdasarkan response time dan device status
                if ($httpCode >= 200 && $httpCode < 300) {
                    if ($result['device_status'] === 'connected') {
                        if ($responseTime < 1000) {
                            $result['status'] = 'stable';
                            $result['color'] = 'success'; // Hijau
                            $result['message'] = '✅ Koneksi STABIL SEKALI (response time < 1 detik)';
                        } elseif ($responseTime < 3000) {
                            $result['status'] = 'unstable';
                            $result['color'] = 'warning'; // Kuning
                            $result['message'] = '⚠️ Koneksi RENTAN DISCONNECT (response time 1-3 detik)';
                        } else {
                            $result['status'] = 'critical';
                            $result['color'] = 'danger'; // Merah
                            $result['message'] = '🔴 Koneksi KRITIS (response time > 3 detik)';
                        }
                    } else {
                        $result['status'] = 'disconnected';
                        $result['color'] = 'danger'; // Merah
                        $result['message'] = '🔴 Device WhatsApp TIDAK TERHUBUNG';
                    }
                } else {
                    $result['status'] = 'error';
                    $result['color'] = 'danger'; // Merah
                    $result['message'] = "🔴 HTTP Error: $httpCode";
                }
            } else {
                $result['status'] = 'error';
                $result['color'] = 'danger'; // Merah
                $result['message'] = '🔴 Format response tidak dikenal';
            }
        } else {
            $result['status'] = 'error';
            $result['color'] = 'danger'; // Merah
            $result['message'] = "🔴 Koneksi gagal: $error";
        }
        
        writeLog('whatsapp', "Hasil test: {$result['status']} - {$result['response_time']}ms", $result);
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['color'] = 'danger'; // Merah
        $result['message'] = '🔴 Exception: ' . $e->getMessage();
        writeLog('whatsapp', "EXCEPTION: " . $e->getMessage());
    }
    
    return $result;
}

// ============================================================================
// FUNGSI TEST KONEKSI MAILERSEND
// ============================================================================
function testMailersendConnection() {
    $result = [
        'status' => 'unknown',
        'color' => 'secondary',
        'message' => 'Belum diuji',
        'response_time' => 0,
        'http_code' => 0,
        'domain_status' => 'unknown',
        'domain_verified' => false,
        'details' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    writeLog('mailersend', 'Menguji koneksi Mailersend...');
    
    try {
        $start = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.mailersend.com/v1/domain',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . MAILERSEND_API_TOKEN,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        curl_close($ch);
        
        $result['response_time'] = $responseTime;
        $result['http_code'] = $httpCode;
        
        if ($response) {
            $data = json_decode($response, true);
            $result['details'] = $data;
            
            // Cari domain yang sesuai
            $domainFound = false;
            if (isset($data['data'])) {
                foreach ($data['data'] as $domain) {
                    if ($domain['id'] === MAILERSEND_DOMAIN_ID || $domain['name'] === MAILERSEND_DOMAIN) {
                        $domainFound = true;
                        $result['domain_status'] = $domain['status'] ?? 'unknown';
                        $result['domain_verified'] = $domain['is_verified'] ?? false;
                        $result['domain_dns_valid'] = $domain['is_dns_valid'] ?? false;
                        break;
                    }
                }
            }
            
            // Tentukan status berdasarkan response time dan domain status
            if ($httpCode >= 200 && $httpCode < 300) {
                if ($domainFound) {
                    if ($result['domain_verified'] && $result['domain_status'] === 'active') {
                        if ($responseTime < 1000) {
                            $result['status'] = 'stable';
                            $result['color'] = 'success'; // Hijau
                            $result['message'] = '✅ Koneksi STABIL SEKALI (response time < 1 detik)';
                        } elseif ($responseTime < 3000) {
                            $result['status'] = 'unstable';
                            $result['color'] = 'warning'; // Kuning
                            $result['message'] = '⚠️ Koneksi RENTAN DISCONNECT (response time 1-3 detik)';
                        } else {
                            $result['status'] = 'critical';
                            $result['color'] = 'danger'; // Merah
                            $result['message'] = '🔴 Koneksi KRITIS (response time > 3 detik)';
                        }
                    } else {
                        $result['status'] = 'unverified';
                        $result['color'] = 'warning'; // Kuning
                        $result['message'] = '⚠️ Domain BELUM TERVERIFIKASI';
                    }
                } else {
                    $result['status'] = 'not_found';
                    $result['color'] = 'warning'; // Kuning
                    $result['message'] = '⚠️ Domain tidak ditemukan dalam daftar';
                }
            } else {
                $result['status'] = 'error';
                $result['color'] = 'danger'; // Merah
                $result['message'] = "🔴 HTTP Error: $httpCode";
            }
        } else {
            $result['status'] = 'error';
            $result['color'] = 'danger'; // Merah
            $result['message'] = "🔴 Koneksi gagal: $error";
        }
        
        writeLog('mailersend', "Hasil test: {$result['status']} - {$result['response_time']}ms", $result);
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['color'] = 'danger'; // Merah
        $result['message'] = '🔴 Exception: ' . $e->getMessage();
        writeLog('mailersend', "EXCEPTION: " . $e->getMessage());
    }
    
    return $result;
}

// ============================================================================
// FUNGSI TEST SMTP MAILERSEND
// ============================================================================
function testMailersendSMTP() {
    $result = [
        'success' => false,
        'message' => '',
        'response_time' => 0
    ];
    
    $host = 'smtp.mailersend.net';
    $port = 587;
    $timeout = 5;
    
    $start = microtime(true);
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $responseTime = round((microtime(true) - $start) * 1000, 2);
    
    if ($connection) {
        fclose($connection);
        $result['success'] = true;
        $result['message'] = "✅ Koneksi SMTP berhasil ke $host:$port";
        $result['response_time'] = $responseTime;
    } else {
        $result['message'] = "🔴 Koneksi SMTP gagal: $errstr ($errno)";
        $result['response_time'] = $responseTime;
    }
    
    writeLog('mailersend', "SMTP Test: " . ($result['success'] ? 'BERHASIL' : 'GAGAL') . " - {$responseTime}ms");
    
    return $result;
}

// ============================================================================
// FUNGSI TEST KIRIM WHATSAPP (OPSIONAL)
// ============================================================================
function testSendWhatsApp() {
    $result = [
        'success' => false,
        'message' => '',
        'response_time' => 0
    ];
    
    $testMessage = "🔍 TEST MONITORING SYSTEM\n\n";
    $testMessage .= "Ini adalah pesan test dari monitoring koneksi.\n";
    $testMessage .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
    $testMessage .= "Tujuan: Memeriksa fungsi pengiriman WhatsApp\n";
    $testMessage .= "Status: TEST - TIDAK PERLU DIBALAS";
    
    try {
        $start = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => WHATSAPP_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query([
                'target' => WHATSAPP_DEVICE,
                'message' => $testMessage,
                'countryCode' => '62'
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . WHATSAPP_TOKEN
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        curl_close($ch);
        
        $result['response_time'] = $responseTime;
        
        if ($response) {
            $data = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && isset($data['status']) && $data['status']) {
                $result['success'] = true;
                $result['message'] = '✅ Test WhatsApp berhasil dikirim';
            } else {
                $result['message'] = "🔴 Gagal: " . ($data['reason'] ?? 'Unknown error');
            }
        } else {
            $result['message'] = "🔴 Curl error: $error";
        }
        
    } catch (Exception $e) {
        $result['message'] = '🔴 Exception: ' . $e->getMessage();
    }
    
    writeLog('whatsapp', "Test Kirim: " . ($result['success'] ? 'BERHASIL' : 'GAGAL') . " - {$result['response_time']}ms");
    
    return $result;
}

// ============================================================================
// FUNGSI TEST KIRIM EMAIL (OPSIONAL)
// ============================================================================
function testSendEmail() {
    $result = [
        'success' => false,
        'message' => '',
        'response_time' => 0
    ];
    
    // Gunakan email sendiri untuk test (ganti dengan email Anda)
    $test_email = MAILERSEND_FROM_EMAIL; // Kirim ke diri sendiri
    
    $subject = "🔍 TEST MONITORING SYSTEM - MailerSend";
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #667eea; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔍 TEST MONITORING SYSTEM</h2>
            </div>
            <div class='content'>
                <p>Ini adalah email test dari monitoring koneksi MailerSend.</p>
                <p><strong>Waktu:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Tujuan:</strong> Memeriksa fungsi pengiriman email</p>
                <p><em>Status: TEST - TIDAK PERLU DIBALAS</em></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Monitoring System</p>
                <p><i>Powered by MailerSend</i></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        $start = microtime(true);
        
        $data = [
            'from' => [
                'email' => MAILERSEND_FROM_EMAIL,
                'name' => MAILERSEND_FROM_NAME
            ],
            'to' => [
                ['email' => $test_email, 'name' => 'Monitoring System']
            ],
            'subject' => $subject,
            'html' => $html_content,
            'text' => strip_tags($html_content)
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        curl_close($ch);
        
        $result['response_time'] = $responseTime;
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result['success'] = true;
            $result['message'] = '✅ Test email berhasil dikirim';
        } else {
            $result['message'] = "🔴 HTTP Error: $httpCode - $error";
        }
        
    } catch (Exception $e) {
        $result['message'] = '🔴 Exception: ' . $e->getMessage();
    }
    
    writeLog('mailersend', "Test Kirim: " . ($result['success'] ? 'BERHASIL' : 'GAGAL') . " - {$result['response_time']}ms");
    
    return $result;
}

// ============================================================================
// PROSES REQUEST
// ============================================================================

// Handle actions
$action = $_GET['action'] ?? '';
$auto_refresh = isset($_GET['auto']) ? (int)$_GET['auto'] : 30;

// Jalankan test
$fonnteResult = testFonnteConnection();
$mailersendResult = testMailersendConnection();
$smtpResult = testMailersendSMTP();

// Jalankan test kirim jika diminta
$waSendResult = null;
$emailSendResult = null;

if ($action === 'test_wa') {
    $waSendResult = testSendWhatsApp();
} elseif ($action === 'test_email') {
    $emailSendResult = testSendEmail();
} elseif ($action === 'test_all') {
    $waSendResult = testSendWhatsApp();
    $emailSendResult = testSendEmail();
}

// ============================================================================
// TAMPILAN HTML
// ============================================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Koneksi Layanan Eksternal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --secondary-color: #6c757d;
        }
        
        body {
            background: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 80px;
        }
        
        .container-fluid {
            max-width: 1400px;
        }
        
        /* Status Indicators */
        .status-card {
            border-radius: 20px;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .status-card.success::before {
            background: linear-gradient(90deg, #28a745, #20c997);
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
        }
        
        .status-card.warning::before {
            background: linear-gradient(90deg, #ffc107, #fd7e14);
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.5);
        }
        
        .status-card.danger::before {
            background: linear-gradient(90deg, #dc3545, #c82333);
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.5);
        }
        
        .status-card.secondary::before {
            background: linear-gradient(90deg, #6c757d, #5a6268);
        }
        
        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        /* Animated pulse for critical status */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .status-card.danger {
            animation: pulse 2s infinite;
        }
        
        .status-card.warning {
            animation: pulse 3s infinite;
        }
        
        /* Icon circles */
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .icon-circle.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .icon-circle.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .icon-circle.danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .icon-circle.secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        /* Progress bars */
        .progress {
            height: 12px;
            border-radius: 6px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-bar {
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Status badges */
        .status-badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-badge.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .status-badge.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        .status-badge.danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .status-badge.secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        /* Metrics cards */
        .metric-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .metric-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Action buttons */
        .action-btn {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .action-btn.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .action-btn.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        /* Auto refresh indicator */
        .auto-refresh {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 50px;
            padding: 10px 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .refresh-progress {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 3px solid #e9ecef;
            border-top-color: #667eea;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Log viewer */
        .log-viewer {
            background: #1e1e2f;
            color: #0f0;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding: 5px;
            border-left: 3px solid;
        }
        
        .log-entry.whatsapp { border-left-color: #25D366; }
        .log-entry.mailersend { border-left-color: #667eea; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .icon-circle {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .metric-value {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-0">
                    <i class="fas fa-heartbeat me-2 text-primary"></i>
                    Monitor Koneksi Layanan Eksternal
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Monitoring real-time koneksi ke layanan eksternal
                </p>
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-secondary me-2" id="lastUpdate">
                    <i class="fas fa-clock me-1"></i>
                    Update: <?php echo date('H:i:s'); ?>
                </span>
                <a href="?auto=<?php echo $auto_refresh; ?>" class="btn btn-primary me-2">
                    <i class="fas fa-sync-alt me-1"></i>Refresh Manual
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-clock me-1"></i>Auto Refresh <?php echo $auto_refresh; ?>s
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?auto=5">5 detik</a></li>
                        <li><a class="dropdown-item" href="?auto=10">10 detik</a></li>
                        <li><a class="dropdown-item" href="?auto=30">30 detik</a></li>
                        <li><a class="dropdown-item" href="?auto=60">1 menit</a></li>
                        <li><a class="dropdown-item" href="?auto=0">Mati</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Status Legend -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm">
                    <div class="status-badge success me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">🟢 Stabil Sekali</h6>
                        <small class="text-muted">Response time < 1 detik</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm">
                    <div class="status-badge warning me-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">🟡 Rentan Disconnect</h6>
                        <small class="text-muted">Response time 1-3 detik atau domain belum verifikasi</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm">
                    <div class="status-badge danger me-3">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">🔴 Koneksi Putus</h6>
                        <small class="text-muted">Response time > 3 detik atau gagal terkoneksi</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Status Cards -->
        <div class="row g-4 mb-4">
            <!-- WhatsApp Card -->
            <div class="col-lg-6">
                <div class="card status-card <?php echo $fonnteResult['color']; ?> h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="icon-circle <?php echo $fonnteResult['color']; ?> text-white">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="mb-1">WhatsApp Gateway</h4>
                                <p class="text-muted mb-0">Fonnte</p>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $fonnteResult['color']; ?>">
                                    <?php
                                    $statusIcons = [
                                        'stable' => '🟢',
                                        'unstable' => '🟡',
                                        'critical' => '🔴',
                                        'disconnected' => '🔴',
                                        'error' => '🔴'
                                    ];
                                    $icon = $statusIcons[$fonnteResult['status']] ?? '⚪';
                                    echo $icon . ' ' . strtoupper($fonnteResult['status']);
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Metrics Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-tachometer-alt fa-2x mb-2" style="color: <?php
                                        if ($fonnteResult['response_time'] < 1000) echo '#28a745';
                                        elseif ($fonnteResult['response_time'] < 3000) echo '#ffc107';
                                        else echo '#dc3545';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php echo $fonnteResult['response_time']; ?> ms
                                    </div>
                                    <div class="metric-label">Response Time</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-plug fa-2x mb-2" style="color: <?php
                                        echo ($fonnteResult['device_status'] === 'connected') ? '#28a745' : '#dc3545';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php 
                                        echo ucfirst($fonnteResult['device_status']);
                                        ?>
                                    </div>
                                    <div class="metric-label">Device Status</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-exchange-alt fa-2x mb-2" style="color: <?php
                                        if ($fonnteResult['http_code'] >= 200 && $fonnteResult['http_code'] < 300) echo '#28a745';
                                        else echo '#dc3545';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php echo $fonnteResult['http_code']; ?>
                                    </div>
                                    <div class="metric-label">HTTP Code</div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Response Time Threshold</span>
                                <span>
                                    <span class="badge bg-success me-1">&lt;1s</span>
                                    <span class="badge bg-warning me-1">1-3s</span>
                                    <span class="badge bg-danger">&gt;3s</span>
                                </span>
                            </div>
                            <div class="progress">
                                <?php
                                $width = min(100, ($fonnteResult['response_time'] / 100) * 3.33);
                                if ($fonnteResult['response_time'] < 1000) {
                                    $barClass = 'bg-success';
                                } elseif ($fonnteResult['response_time'] < 3000) {
                                    $barClass = 'bg-warning';
                                } else {
                                    $barClass = 'bg-danger';
                                }
                                ?>
                                <div class="progress-bar <?php echo $barClass; ?>" 
                                     style="width: <?php echo $width; ?>%;"></div>
                            </div>
                        </div>

                        <!-- Details Table -->
                        <table class="table table-sm">
                            <tr>
                                <th>Device Name</th>
                                <td><?php echo htmlspecialchars($fonnteResult['device_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Nomor Device</th>
                                <td><code><?php echo WHATSAPP_DEVICE; ?></code></td>
                            </tr>
                            <tr>
                                <th>Token</th>
                                <td>
                                    <code><?php echo substr(WHATSAPP_TOKEN, 0, 8); ?>...<?php echo substr(WHATSAPP_TOKEN, -4); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th>API URL</th>
                                <td><small class="text-muted"><?php echo WHATSAPP_API_URL; ?></small></td>
                            </tr>
                        </table>

                        <!-- Status Message -->
                        <div class="alert alert-<?php 
                            echo $fonnteResult['color'] === 'success' ? 'success' : 
                                ($fonnteResult['color'] === 'warning' ? 'warning' : 'danger');
                        ?> mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $fonnteResult['message']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MailerSend Card -->
            <div class="col-lg-6">
                <div class="card status-card <?php echo $mailersendResult['color']; ?> h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="icon-circle <?php echo $mailersendResult['color']; ?> text-white">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="mb-1">Email Gateway</h4>
                                <p class="text-muted mb-0">MailerSend</p>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $mailersendResult['color']; ?>">
                                    <?php
                                    $statusIcons = [
                                        'stable' => '🟢',
                                        'unstable' => '🟡',
                                        'critical' => '🔴',
                                        'unverified' => '🟡',
                                        'not_found' => '🟡',
                                        'error' => '🔴'
                                    ];
                                    $icon = $statusIcons[$mailersendResult['status']] ?? '⚪';
                                    echo $icon . ' ' . strtoupper($mailersendResult['status']);
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Metrics Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-tachometer-alt fa-2x mb-2" style="color: <?php
                                        if ($mailersendResult['response_time'] < 1000) echo '#28a745';
                                        elseif ($mailersendResult['response_time'] < 3000) echo '#ffc107';
                                        else echo '#dc3545';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php echo $mailersendResult['response_time']; ?> ms
                                    </div>
                                    <div class="metric-label">Response Time</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-check-circle fa-2x mb-2" style="color: <?php
                                        echo $mailersendResult['domain_verified'] ? '#28a745' : '#ffc107';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php echo $mailersendResult['domain_verified'] ? 'Verified' : 'Unverified'; ?>
                                    </div>
                                    <div class="metric-label">Domain Status</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-card">
                                    <i class="fas fa-exchange-alt fa-2x mb-2" style="color: <?php
                                        if ($mailersendResult['http_code'] >= 200 && $mailersendResult['http_code'] < 300) echo '#28a745';
                                        else echo '#dc3545';
                                    ?>"></i>
                                    <div class="metric-value">
                                        <?php echo $mailersendResult['http_code']; ?>
                                    </div>
                                    <div class="metric-label">HTTP Code</div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Response Time Threshold</span>
                                <span>
                                    <span class="badge bg-success me-1">&lt;1s</span>
                                    <span class="badge bg-warning me-1">1-3s</span>
                                    <span class="badge bg-danger">&gt;3s</span>
                                </span>
                            </div>
                            <div class="progress">
                                <?php
                                $width = min(100, ($mailersendResult['response_time'] / 100) * 3.33);
                                if ($mailersendResult['response_time'] < 1000) {
                                    $barClass = 'bg-success';
                                } elseif ($mailersendResult['response_time'] < 3000) {
                                    $barClass = 'bg-warning';
                                } else {
                                    $barClass = 'bg-danger';
                                }
                                ?>
                                <div class="progress-bar <?php echo $barClass; ?>" 
                                     style="width: <?php echo $width; ?>%;"></div>
                            </div>
                        </div>

                        <!-- Details Table -->
                        <table class="table table-sm">
                            <tr>
                                <th>Domain</th>
                                <td><code><?php echo MAILERSEND_DOMAIN; ?></code></td>
                            </tr>
                            <tr>
                                <th>Domain Status</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $mailersendResult['domain_status'] === 'active' ? 'success' : 'warning';
                                    ?>">
                                        <?php echo ucfirst($mailersendResult['domain_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>From Email</th>
                                <td><code><?php echo MAILERSEND_FROM_EMAIL; ?></code></td>
                            </tr>
                            <tr>
                                <th>API Token</th>
                                <td>
                                    <code><?php echo substr(MAILERSEND_API_TOKEN, 0, 10); ?>...<?php echo substr(MAILERSEND_API_TOKEN, -5); ?></code>
                                </td>
                            </tr>
                        </table>

                        <!-- Status Message -->
                        <div class="alert alert-<?php 
                            echo $mailersendResult['color'] === 'success' ? 'success' : 
                                ($mailersendResult['color'] === 'warning' ? 'warning' : 'danger');
                        ?> mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $mailersendResult['message']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP Status & Test Actions -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="fas fa-server me-2 text-primary"></i>
                            SMTP Server Status
                        </h5>
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="icon-circle <?php echo $smtpResult['success'] ? 'success' : 'danger'; ?> text-white me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-network-wired"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">smtp.mailersend.net:587</h6>
                                <div class="d-flex align-items-center">
                                    <span class="status-badge <?php echo $smtpResult['success'] ? 'success' : 'danger'; ?> me-2" style="padding: 4px 12px;">
                                        <?php echo $smtpResult['success'] ? '🟢 ONLINE' : '🔴 OFFLINE'; ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo $smtpResult['response_time']; ?> ms
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar <?php echo $smtpResult['success'] ? 'bg-success' : 'bg-danger'; ?>" 
                                 style="width: <?php echo $smtpResult['success'] ? '100' : '0'; ?>%;"></div>
                        </div>
                        
                        <p class="mb-0 small <?php echo $smtpResult['success'] ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-<?php echo $smtpResult['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-1"></i>
                            <?php echo $smtpResult['message']; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="fas fa-paper-plane me-2 text-primary"></i>
                            Test Pengiriman
                        </h5>
                        
                        <div class="d-grid gap-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <a href="?action=test_wa&auto=<?php echo $auto_refresh; ?>" class="btn btn-outline-success w-100 py-3" style="border-width: 2px;">
                                        <i class="fab fa-whatsapp fa-2x mb-2"></i>
                                        <div class="fw-bold">Test WhatsApp</div>
                                        <small>Kirim pesan test ke device sendiri</small>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="?action=test_email&auto=<?php echo $auto_refresh; ?>" class="btn btn-outline-primary w-100 py-3" style="border-width: 2px;">
                                        <i class="fas fa-envelope fa-2x mb-2"></i>
                                        <div class="fw-bold">Test Email</div>
                                        <small>Kirim email test ke noreply</small>
                                    </a>
                                </div>
                            </div>
                            
                            <a href="?action=test_all&auto=<?php echo $auto_refresh; ?>" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-rocket me-2"></i>Test Semua Layanan
                            </a>
                        </div>

                        <?php if ($waSendResult || $emailSendResult): ?>
                        <hr>
                        <h6 class="mb-2">Hasil Test:</h6>
                        <?php if ($waSendResult): ?>
                        <div class="alert alert-<?php echo $waSendResult['success'] ? 'success' : 'danger'; ?> py-2 mb-2">
                            <i class="fab fa-whatsapp me-2"></i>
                            <?php echo $waSendResult['message']; ?>
                            <small class="d-block text-muted mt-1">
                                Response time: <?php echo $waSendResult['response_time']; ?> ms
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($emailSendResult): ?>
                        <div class="alert alert-<?php echo $emailSendResult['success'] ? 'success' : 'danger'; ?> py-2">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo $emailSendResult['message']; ?>
                            <small class="d-block text-muted mt-1">
                                Response time: <?php echo $emailSendResult['response_time']; ?> ms
                            </small>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Logs -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <ul class="nav nav-tabs card-header-tabs" id="logTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="whatsapp-logs-tab" data-bs-toggle="tab" data-bs-target="#whatsapp-logs" type="button" role="tab">
                                    <i class="fab fa-whatsapp text-success me-1"></i> WhatsApp Logs
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="mailersend-logs-tab" data-bs-toggle="tab" data-bs-target="#mailersend-logs" type="button" role="tab">
                                    <i class="fas fa-envelope text-primary me-1"></i> MailerSend Logs
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="logTabsContent">
                            <div class="tab-pane fade show active" id="whatsapp-logs" role="tabpanel">
                                <div class="log-viewer" id="whatsappLogViewer">
                                    <?php
                                    $whatsapp_log_file = __DIR__ . '/logs/whatsapp_monitor_' . date('Y-m-d') . '.log';
                                    if (file_exists($whatsapp_log_file)) {
                                        $logs = array_reverse(file($whatsapp_log_file));
                                        $display_logs = array_slice($logs, 0, 20);
                                        foreach ($display_logs as $log) {
                                            $log_class = strpos($log, 'BERHASIL') !== false ? 'text-success' : 
                                                        (strpos($log, 'GAGAL') !== false ? 'text-danger' : 'text-light');
                                            echo "<div class='log-entry whatsapp'><span class='{$log_class}'>" . htmlspecialchars($log) . "</span></div>";
                                        }
                                    } else {
                                        echo "<div class='text-muted'>Belum ada log WhatsApp untuk hari ini.</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mailersend-logs" role="tabpanel">
                                <div class="log-viewer" id="mailersendLogViewer">
                                    <?php
                                    $mailersend_log_file = __DIR__ . '/logs/mailersend_monitor_' . date('Y-m-d') . '.log';
                                    if (file_exists($mailersend_log_file)) {
                                        $logs = array_reverse(file($mailersend_log_file));
                                        $display_logs = array_slice($logs, 0, 20);
                                        foreach ($display_logs as $log) {
                                            $log_class = strpos($log, 'BERHASIL') !== false ? 'text-success' : 
                                                        (strpos($log, 'GAGAL') !== false ? 'text-danger' : 'text-light');
                                            echo "<div class='log-entry mailersend'><span class='{$log_class}'>" . htmlspecialchars($log) . "</span></div>";
                                        }
                                    } else {
                                        echo "<div class='text-muted'>Belum ada log MailerSend untuk hari ini.</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Refresh Indicator -->
    <?php if ($auto_refresh > 0): ?>
    <div class="auto-refresh" id="autoRefreshIndicator">
        <div class="refresh-progress" id="refreshProgress"></div>
        <div>
            <strong>Auto Refresh</strong>
            <div>Setiap <?php echo $auto_refresh; ?> detik</div>
        </div>
        <a href="?auto=0" class="btn btn-sm btn-outline-secondary ms-2">
            <i class="fas fa-times"></i>
        </a>
    </div>

    <script>
        let timeLeft = <?php echo $auto_refresh; ?>;
        const refreshInterval = <?php echo $auto_refresh; ?> * 1000;
        const progressElement = document.getElementById('refreshProgress');
        
        function updateProgress() {
            timeLeft--;
            if (timeLeft <= 0) {
                timeLeft = <?php echo $auto_refresh; ?>;
                window.location.href = window.location.pathname + '?auto=<?php echo $auto_refresh; ?>';
            }
            
            // Update progress indicator (visual feedback)
            const percentage = (timeLeft / <?php echo $auto_refresh; ?>) * 100;
            progressElement.style.borderTopColor = `hsl(${percentage * 1.2}, 70%, 50%)`;
        }
        
        setInterval(updateProgress, 1000);
    </script>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto refresh tanpa reload jika parameter auto diatur -->
    <?php if ($auto_refresh > 0 && !isset($_GET['action'])): ?>
    <script>
        setTimeout(function() {
            window.location.href = window.location.pathname + '?auto=<?php echo $auto_refresh; ?>';
        }, <?php echo $auto_refresh * 1000; ?>);
    </script>
    <?php endif; ?>
</body>
</html>