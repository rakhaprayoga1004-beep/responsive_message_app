<?php
/**
 * API untuk manajemen Response Templates
 * Mendukung: GET (list), POST (create), PUT (update), DELETE (delete)
 */

// Matikan error reporting untuk output HTML
error_reporting(0);
ini_set('display_errors', 0);

// Hapus output buffer
while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Cek autentikasi
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Please login first']);
    exit();
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if ($method === 'GET') {
        // Query untuk mendapatkan semua template - ORDER BY id ASC
        $sql = "SELECT 
                    id,
                    guru_type,
                    name,
                    content,
                    category,
                    default_status,
                    is_active,
                    use_count,
                    created_by,
                    created_at,
                    updated_at
                FROM response_templates
                ORDER BY id ASC";
        
        $stmt = $db->query($sql);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'data' => $templates,
            'total' => count($templates)
        ], JSON_PRETTY_PRINT);
        exit();
        
    } elseif ($method === 'POST') {
        // Create new response template
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validasi input
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nama template harus diisi']);
            exit();
        }
        
        $content = trim($input['content'] ?? '');
        if (empty($content)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Konten template harus diisi']);
            exit();
        }
        
        $guruType = trim($input['guru_type'] ?? 'ALL');
        $category = trim($input['category'] ?? 'Umum');
        $defaultStatus = trim($input['default_status'] ?? 'Diproses');
        $isActive = isset($input['is_active']) ? 1 : 0;
        $createdBy = $_SESSION['user_id'];
        
        // Insert ke database
        $sql = "INSERT INTO response_templates 
                (name, content, category, default_status, guru_type, is_active, use_count, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $name, 
            $content, 
            $category, 
            $defaultStatus, 
            $guruType, 
            $isActive,
            $createdBy
        ]);
        $newId = $db->lastInsertId();
        
        // Ambil data yang baru dibuat
        $sql = "SELECT 
                    id, guru_type, name, content, category, default_status, 
                    is_active, use_count, created_by, created_at, updated_at
                FROM response_templates WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$newId]);
        $newTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Template respons berhasil ditambahkan',
            'data' => $newTemplate
        ], JSON_PRETTY_PRINT);
        exit();
        
    } elseif ($method === 'PUT') {
        // Update response template
        if ($id == 0) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($input['name'] ?? '');
        $content = trim($input['content'] ?? '');
        $category = trim($input['category'] ?? 'Umum');
        $defaultStatus = trim($input['default_status'] ?? 'Diproses');
        $guruType = trim($input['guru_type'] ?? 'ALL');
        $isActive = isset($input['is_active']) ? 1 : 0;
        
        $sql = "UPDATE response_templates SET 
                    name = ?, 
                    content = ?, 
                    category = ?, 
                    default_status = ?, 
                    guru_type = ?, 
                    is_active = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$name, $content, $category, $defaultStatus, $guruType, $isActive, $id]);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Template berhasil diperbarui']);
        exit();
        
    } elseif ($method === 'DELETE') {
        // Delete response template
        if ($id == 0) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit();
        }
        
        $sql = "DELETE FROM response_templates WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Template berhasil dihapus']);
        exit();
        
    } else {
        ob_clean();
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Response Templates API error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}