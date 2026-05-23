<?php
/**
 * verify_token.php
 * Endpoint untuk memverifikasi validitas token dari Flutter app
 * 
 * Method: POST
 * Request Body: { "token": "string_token" }
 * Response: { "valid": boolean, "user_id": int (optional) }
 */

// Set headers untuk CORS dan JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Inisialisasi koneksi database
$database = new Database();
$db = $database->getConnection();

// Default response
$response = ['valid' => false];

// Hanya menerima method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Baca input JSON dari request body
    $input = json_decode(file_get_contents('php://input'), true);
    $token = isset($input['token']) ? trim($input['token']) : '';
    
    // Validasi token tidak kosong
    if (!empty($token)) {
        // Query untuk mengecek token
        $query = "SELECT id, user_id, expires_at, is_active, name 
                  FROM api_tokens 
                  WHERE token = :token 
                  AND is_active = 1 
                  AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['valid'] = true;
            $response['user_id'] = (int)$tokenData['user_id'];
            $response['expires_at'] = $tokenData['expires_at'];
            
            // Optional: Update last_used_at
            $updateQuery = "UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $tokenData['id']);
            $updateStmt->execute();
        }
    }
}

// Kirim response JSON
echo json_encode($response);
?>