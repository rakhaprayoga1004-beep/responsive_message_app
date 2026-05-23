<?php
/**
 * API Cek Nomor WhatsApp via Fonnte
 * File: api/check_whatsapp.php
 * 
 * ENDPOINT: POST /api/check_whatsapp.php
 * BODY: {"phone": "085117128578"}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

// Konfigurasi Fonnte
define('WHATSAPP_TOKEN', 'FS2cq8FckmaTegxtZpFB');

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        return '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        return '62' . $phone;
    }
    return $phone;
}

function checkWhatsAppNumber($phone) {
    $result = [
        'exists' => false,
        'canReceive' => false,
        'formatted' => '',
        'message' => ''
    ];

    try {
        $formatted = formatPhoneNumber($phone);
        $result['formatted'] = $formatted;

        // Cek via API Fonnte (cek status nomor)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.fonnte.com/check-number',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['target' => $formatted]),
            CURLOPT_HTTPHEADER => ['Authorization: ' . WHATSAPP_TOKEN],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            
            // Interpretasi response Fonnte
            if (isset($data['status']) && $data['status'] == true) {
                if (isset($data['data']['exists'])) {
                    $result['exists'] = $data['data']['exists'];
                    $result['canReceive'] = $data['data']['can_receive'] ?? false;
                } else {
                    // Fallback: jika tidak ada error, anggap exists
                    $result['exists'] = true;
                    $result['canReceive'] = true;
                }
            } else {
                // Jika API error, jangan blokir pengiriman
                $result['exists'] = true;
                $result['canReceive'] = true;
                $result['message'] = $data['reason'] ?? 'Unknown error';
            }
        } else {
            // Fallback jika API timeout
            $result['exists'] = true;
            $result['canReceive'] = true;
        }

    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
        // Fallback: anggap nomor valid
        $result['exists'] = true;
        $result['canReceive'] = true;
    }

    return $result;
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $phone = $input['phone'] ?? '';
    
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone number required']);
        exit;
    }

    $result = checkWhatsAppNumber($phone);
    echo json_encode($result);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);