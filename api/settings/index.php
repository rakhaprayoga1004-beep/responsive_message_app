// api/settings/index.php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'general':
        handleGeneralSettings($method, $userId);
        break;
    case 'message-types':
        handleMessageTypes($method, $userId);
        break;
    case 'templates':
        handleTemplates($method, $userId);
        break;
    // ... tambahkan handler lainnya
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
}