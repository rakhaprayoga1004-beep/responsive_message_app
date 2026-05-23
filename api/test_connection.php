<?php
// api/test_connection.php
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mencari file config
$paths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/config/config.php',
    __DIR__ . '/../../config/config.php'
];

$configLoaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    echo json_encode(['success' => false, 'message' => 'Config not found']);
    exit;
}

// Koneksi manual
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$port = defined('DB_PORT') ? DB_PORT : '3307';
$dbname = defined('DB_NAME') ? DB_NAME : 'responsive_message_db';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM message_types");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connected',
        'host' => $host,
        'port' => $port,
        'database' => $dbname,
        'total_message_types' => $result['total']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>