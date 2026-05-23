<?php
/**
 * Test API untuk Response Templates
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$db = Database::getInstance()->getConnection();

try {
    $sql = "SELECT COUNT(*) as total FROM response_templates";
    $stmt = $db->query($sql);
    $total = $stmt->fetch()['total'];
    
    $sql = "SELECT * FROM response_templates ORDER BY id ASC";
    $stmt = $db->query($sql);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test successful',
        'total' => $total,
        'data' => $templates,
        'user_id' => $_SESSION['user_id']
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}