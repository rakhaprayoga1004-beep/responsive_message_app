<?php
/**
 * Debug Session - Melihat status session secara detail
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../utils/session.php';

$session = SessionManager::getInstance();

$response = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'cookie' => $_COOKIE,
    'session_data' => $_SESSION,
    'server' => [
        'session_save_path' => session_save_path(),
        'session_name' => session_name(),
    ]
];

// Cek file session
$sessionFile = session_save_path() . '/sess_' . session_id();
$response['session_file'] = [
    'path' => $sessionFile,
    'exists' => file_exists($sessionFile),
];

if (file_exists($sessionFile)) {
    $response['session_file']['size'] = filesize($sessionFile);
    $response['session_file']['permissions'] = substr(sprintf('%o', fileperms($sessionFile)), -4);
    $response['session_file']['readable'] = is_readable($sessionFile);
    $response['session_file']['writable'] = is_writable($sessionFile);
    
    // Baca isi file dengan cara yang aman (menggunakan fopen, bukan file_get_contents)
    if (is_readable($sessionFile)) {
        // Gunakan fopen dengan mode read saja
        $handle = fopen($sessionFile, 'r');
        if ($handle) {
            $content = fread($handle, filesize($sessionFile));
            fclose($handle);
            
            if ($content !== false) {
                $response['session_file']['content_raw'] = bin2hex($content);
                
                // Parse session data
                $response['session_file']['content_parsed'] = [];
                $parts = explode(';', $content);
                foreach ($parts as $part) {
                    if (strpos($part, '|') !== false) {
                        list($key, $value) = explode('|', $part, 2);
                        $response['session_file']['content_parsed'][$key] = $value;
                    }
                }
            } else {
                $response['session_file']['read_error'] = 'Failed to read file content';
            }
        } else {
            $response['session_file']['read_error'] = 'Cannot open file';
        }
    } else {
        $response['session_file']['read_error'] = 'File is not readable';
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>