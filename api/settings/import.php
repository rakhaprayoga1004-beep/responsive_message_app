<?php
/**
 * API untuk import konfigurasi
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

$db = Database::getInstance()->getConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$success = true;
$errors = [];

try {
    // Import message types
    if (isset($input['message_types']) && is_array($input['message_types'])) {
        foreach ($input['message_types'] as $type) {
            try {
                $sql = "INSERT IGNORE INTO message_types (id, jenis_pesan, deskripsi, response_deadline_hours, allow_external, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $type['id'],
                    $type['jenis_pesan'],
                    $type['deskripsi'] ?? '',
                    $type['response_deadline_hours'] ?? 72,
                    $type['allow_external'] ?? 1,
                    $type['is_active'] ?? 1
                ]);
            } catch (Exception $e) {
                $success = false;
                $errors[] = "Message type {$type['jenis_pesan']}: " . $e->getMessage();
            }
        }
    }
    
    // Import templates
    if (isset($input['templates']) && is_array($input['templates'])) {
        foreach ($input['templates'] as $template) {
            try {
                $sql = "INSERT IGNORE INTO response_templates (id, name, content, category, default_status, guru_type, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $template['id'],
                    $template['name'],
                    $template['content'],
                    $template['category'] ?? 'Umum',
                    $template['default_status'] ?? 'Disetujui',
                    $template['guru_type'] ?? 'ALL',
                    $template['is_active'] ?? 1
                ]);
            } catch (Exception $e) {
                $success = false;
                $errors[] = "Template {$template['name']}: " . $e->getMessage();
            }
        }
    }
    
    // Import settings
    if (isset($input['settings']) && is_array($input['settings'])) {
        $settingsFile = ROOT_PATH . '/config/settings.json';
        file_put_contents($settingsFile, json_encode($input['settings'], JSON_PRETTY_PRINT));
    }
    
    // Import mailersend config
    if (isset($input['mailersend']) && is_array($input['mailersend'])) {
        $mailersendFile = ROOT_PATH . '/config/mailersend.json';
        file_put_contents($mailersendFile, json_encode($input['mailersend'], JSON_PRETTY_PRINT));
    }
    
    // Import fonnte config
    if (isset($input['fonnte']) && is_array($input['fonnte'])) {
        $fonnteFile = ROOT_PATH . '/config/fonnte.json';
        file_put_contents($fonnteFile, json_encode($input['fonnte'], JSON_PRETTY_PRINT));
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Configuration imported successfully' : 'Partial import completed with errors',
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Import API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}