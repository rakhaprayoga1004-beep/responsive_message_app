<?php
/**
 * API untuk konfigurasi Fonnte (WhatsApp)
 * Mendukung: GET (get config), POST (save config)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$configFile = ROOT_PATH . '/config/fonnte.json';
$defaultConfig = [
    'api_token' => 'FS2cq8FckmaTegxtZpFB',
    'account_token' => 'hzCktiDwSP1sfdXt4PrNtmFkaamX',
    'device_id' => '6285174207795',
    'api_url' => 'https://api.fonnte.com/send',
    'country_code' => '62',
    'is_active' => 1
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if (is_array($config)) {
                $config = array_merge($defaultConfig, $config);
            } else {
                $config = $defaultConfig;
            }
        } else {
            $config = $defaultConfig;
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(['success' => true, 'data' => $config]);
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $config = [
            'api_token' => $input['api_token'] ?? $defaultConfig['api_token'],
            'account_token' => $input['account_token'] ?? $defaultConfig['account_token'],
            'device_id' => $input['device_id'] ?? $defaultConfig['device_id'],
            'api_url' => $input['api_url'] ?? $defaultConfig['api_url'],
            'country_code' => $input['country_code'] ?? '62',
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ];
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true, 'message' => 'Fonnte configuration saved']);
    }
    
} catch (Exception $e) {
    error_log("Fonnte API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}