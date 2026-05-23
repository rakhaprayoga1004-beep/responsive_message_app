<?php
// api/message_types/public_list.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// KONEKSI DATABASE MANUAL (LANGSUNG, TANPA CLASS DATABASE)
// ============================================================================

$host = '127.0.0.1';
$port = '3307';
$dbname = 'responsive_message_db';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ============================================================================
// AMBIL DATA MESSAGE TYPES
// ============================================================================

try {
    // Query untuk mengambil message types yang aktif
    $sql = "
        SELECT id, jenis_pesan, description, responder_type
        FROM message_types 
        WHERE is_active = 1 
        AND allow_external = 1
        ORDER BY id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $types = $stmt->fetchAll();
    
    // Format response
    $messageTypes = [];
    foreach ($types as $type) {
        $messageTypes[] = [
            'id' => (int)$type['id'],
            'jenis_pesan' => $type['jenis_pesan'],
            'description' => $type['description'] ?? '',
            'responder_type' => $type['responder_type'] ?? 'Guru'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message_types' => $messageTypes,
            'total' => count($messageTypes)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>