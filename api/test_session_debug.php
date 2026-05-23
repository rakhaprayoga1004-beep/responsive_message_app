<?php
/**
 * Test Session Debug API
 * File: responsive-message-app/api/test_session_debug.php
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

try {
    $session = SessionManager::getInstance();
    
    echo json_encode([
        'success' => true,
        'session_id' => session_id(),
        'session_data' => $_SESSION,
        'session_status' => session_status(),
        'cookie' => $_COOKIE
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>