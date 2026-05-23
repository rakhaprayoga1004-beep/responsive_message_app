<?php
/**
 * Cek Status Perangkat Fonnte
 * File: api/cek_device.php
 */

define('WHATSAPP_TOKEN', '3AbX6MnRTzJSrFqkWtPq26JM31a9Wo');

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

echo "<h3>HTTP Code: $httpCode</h3>";
echo "<pre>" . print_r(json_decode($response, true), true) . "</pre>";