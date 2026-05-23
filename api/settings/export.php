<?php
/**
 * API untuk export konfigurasi
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$db = Database::getInstance()->getConnection();

try {
    // Get message types
    $sql = "SELECT * FROM message_types ORDER BY id";
    $stmt = $db->query($sql);
    $messageTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get templates
    $sql = "SELECT * FROM response_templates ORDER BY id";
    $stmt = $db->query($sql);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get general settings
    $settingsFile = ROOT_PATH . '/config/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    }
    
    // Get mailersend config
    $mailersendFile = ROOT_PATH . '/config/mailersend.json';
    $mailersend = [];
    if (file_exists($mailersendFile)) {
        $mailersend = json_decode(file_get_contents($mailersendFile), true);
    }
    
    // Get fonnte config
    $fonnteFile = ROOT_PATH . '/config/fonnte.json';
    $fonnte = [];
    if (file_exists($fonnteFile)) {
        $fonnte = json_decode(file_get_contents($fonnteFile), true);
    }
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'exported_by' => $userId,
        'message_types' => $messageTypes,
        'templates' => $templates,
        'settings' => $settings,
        'mailersend' => $mailersend,
        'fonnte' => $fonnte
    ];
    
    echo json_encode(['success' => true, 'data' => $exportData], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Export API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}