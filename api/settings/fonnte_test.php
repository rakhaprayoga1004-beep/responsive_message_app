<?php
/**
 * API untuk test Fonnte (WhatsApp)
 * Mendukung: test connection, send test WhatsApp
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Verify token
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userId = verifyToken($token);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        $phone = '62' . $phone;
    }
    return $phone;
}

function sendTestWhatsApp($config, $to_phone, $to_name = 'Admin Test') {
    $formatted_phone = formatPhoneNumber($to_phone);
    
    if (strlen($formatted_phone) < 10 || strlen($formatted_phone) > 15) {
        return ['success' => false, 'message' => "Nomor tidak valid: $formatted_phone", 'sent' => false];
    }
    
    $message = "🔔 *TEST NOTIFIKASI WHATSAPP - SMKN 12 Jakarta*\n\n";
    $message .= "Yth. *$to_name*\n\n";
    $message .= "Ini adalah pesan test dari sistem Aplikasi Pesan Responsif.\n\n";
    $message .= "*Detail Test:*\n";
    $message .= "Waktu: " . date('d/m/Y H:i:s') . " WIB\n";
    $message .= "Tujuan: $to_phone\n\n";
    $message .= "Jika Anda menerima pesan ini, berarti konfigurasi Fonnte berhasil! ✅\n\n";
    $message .= "_Pesan otomatis._\n";
    $message .= "_Dikirim dari perangkat: " . ($config['device_id'] ?? '6285174207795') . "_";
    
    $postData = [
        'target' => $formatted_phone,
        'message' => $message,
        'countryCode' => $config['country_code'] ?? '62',
        'delay' => '0'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_url'] ?? 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Authorization: ' . ($config['api_token'] ?? '')],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    $success = false;
    if ($httpCode == 200) {
        if (isset($response_data['status']) && $response_data['status'] == 1) {
            $success = true;
        } elseif (isset($response_data['status']) && $response_data['status'] === true) {
            $success = true;
        } elseif (isset($response_data['id'])) {
            $success = true;
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => "WhatsApp test berhasil dikirim ke $to_phone", 'sent' => true];
    } else {
        $errorMsg = $curlError ?: ($response_data['reason'] ?? "HTTP $httpCode");
        return ['success' => false, 'message' => "Gagal mengirim WhatsApp: $errorMsg", 'sent' => false];
    }
}

function testFonnteConnection($config) {
    if (empty($config['api_token'])) {
        return ['success' => false, 'message' => 'API Token tidak boleh kosong'];
    }
    
    $test_phone = $config['device_id'] ?? '6285174207795';
    $result = sendTestWhatsApp($config, $test_phone, 'Admin Test');
    
    if ($result['success']) {
        return ['success' => true, 'message' => "✓ Koneksi Fonnte berhasil! WhatsApp test telah dikirim ke $test_phone", 'sent' => true];
    } else {
        return ['success' => false, 'message' => "❌ Gagal mengirim WhatsApp test: " . ($result['message'] ?? 'Unknown error'), 'sent' => false];
    }
}

try {
    if ($action === 'whatsapp') {
        $to_phone = $input['phone'] ?? '';
        $config = $input['config'] ?? [];
        
        if (empty($to_phone)) {
            echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tujuan harus diisi', 'sent' => false]);
            exit;
        }
        
        $result = sendTestWhatsApp($config, $to_phone);
        echo json_encode($result);
        
    } else {
        $config = $input;
        $result = testFonnteConnection($config);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Fonnte Test API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'sent' => false]);
}