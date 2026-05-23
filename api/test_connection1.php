<?php
/**
 * File test untuk koneksi API Flutter
 * Lokasi: /responsive-message-app/api/test_connection1.php
 * 
 * File ini dibuat khusus untuk Flutter, tidak mengganggu file existing
 */

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'success' => true,
    'message' => 'API Test Connection untuk Flutter berhasil',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'script_name' => $_SERVER['SCRIPT_NAME'],
    ],
    'flutter_config' => [
        'api_url' => 'http://localhost:8090/responsive-message-app/api',
        'flutter_project' => 'responsive_message_app_flutter'
    ]
];

// Load konfigurasi database dari proyek utama
$config_file = dirname(__DIR__) . '/config/config.php';
$database_file = dirname(__DIR__) . '/config/database.php';

if (file_exists($config_file) && file_exists($database_file)) {
    try {
        require_once $config_file;
        require_once $database_file;
        
        // Test koneksi database menggunakan class Database
        if (class_exists('Database')) {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            if ($conn) {
                $response['database'] = [
                    'connected' => true,
                    'host' => DB_HOST,
                    'port' => DB_PORT,
                    'database' => DB_NAME,
                ];
                
                // Test query sederhana
                try {
                    $result = $db->select("SELECT 1 as test");
                    $response['database']['test_query'] = 'OK';
                    
                    // Cek tabel users
                    $tables = $db->select("SHOW TABLES LIKE 'users'");
                    $response['database']['tables']['users'] = !empty($tables) ? 'exists' : 'not_found';
                    
                    // Hitung jumlah users
                    if (!empty($tables)) {
                        $count = $db->select("SELECT COUNT(*) as total FROM users");
                        $response['database']['users_count'] = (int)$count[0]['total'];
                    }
                    
                } catch (Exception $e) {
                    $response['database']['query_error'] = $e->getMessage();
                }
            }
        }
        
    } catch (Exception $e) {
        $response['database'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
} else {
    $response['database'] = [
        'connected' => false,
        'error' => 'Configuration files not found',
        'config_path' => $config_file,
        'database_path' => $database_file
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>