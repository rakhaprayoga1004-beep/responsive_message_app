<?php
/**
 * WhatsApp API Endpoint
 * File: api/whatsapp.php
 * 
 * API untuk mengirim notifikasi WhatsApp via Fonnte
 * Berdasarkan dokumentasi resmi: https://docs.fonnte.com/mengirim-pesan-api/
 */

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfigurasi WhatsApp
define('WHATSAPP_TOKEN', 'v2_9mGB_7E5nW6HdW5gVjVeWv4m8zXyYk7KjN2rL3pQ5tR8sM9nB');
define('WHATSAPP_API_URL', 'https://api.fonnte.com/send');

// Logging
$logFile = __DIR__ . '/../logs/whatsapp_api.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function api_log($message, $data = null) {
    global $logFile;
    $log = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $log .= " - " . print_r($data, true);
    }
    $log .= "\n";
    file_put_contents($logFile, $log, FILE_APPEND);
}

// Fungsi kirim WhatsApp via Fonnte
function sendWhatsApp($phone, $message) {
    api_log("sendWhatsApp called", [
        'original_phone' => $phone,
        'message_length' => strlen($message)
    ]);
    
    // Format nomor telepon sesuai dokumentasi Fonnte [citation:5]
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    api_log("Formatted phone", ['phone' => $phone]);
    
    // Siapkan data sesuai parameter API [citation:5]
    $postData = [
        'target' => $phone,
        'message' => $message,
        'countryCode' => '62' // Default untuk Indonesia
    ];
    
    api_log("Sending to Fonnte", $postData);
    
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
    
    api_log("Fonnte response", [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError
    ]);
    
    $responseData = json_decode($response, true);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $responseData,
        'error' => $curlError
    ];
}

// Handle request
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'status':
            $response = [
                'success' => true,
                'status' => 'active',
                'api' => 'Fonnte',
                'token_valid' => !empty(WHATSAPP_TOKEN),
                'api_url' => WHATSAPP_API_URL,
                'timestamp' => date('Y-m-d H:i:s'),
                'docs' => 'https://docs.fonnte.com/mengirim-pesan-api/'
            ];
            break;
            
        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            // Bisa dari JSON atau POST form
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            api_log("Send message request", $input);
            
            $phone = $input['to'] ?? $input['phone'] ?? $input['target'] ?? '';
            $message = $input['message'] ?? '';
            $message_id = $input['message_id'] ?? null;
            
            if (empty($phone)) {
                throw new Exception('Phone number (target) is required');
            }
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            $result = sendWhatsApp($phone, $message);
            
            $response = [
                'success' => $result['success'],
                'http_code' => $result['http_code'],
                'data' => $result['response'],
                'error' => $result['error'],
                'message_id' => $message_id
            ];
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => 'Unknown action',
                'available_actions' => ['status', 'send_message'],
                'note' => 'Untuk mengirim pesan, gunakan action=send_message dengan method POST'
            ];
    }
} catch (Exception $e) {
    api_log("ERROR", $e->getMessage());
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

api_log("RESPONSE", $response);

// Send response
header('Content-Type: application/json');
echo json_encode($response);