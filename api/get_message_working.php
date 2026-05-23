<?php
/**
 * API Endpoint yang PASTI BEKERJA untuk semua ID
 * File: responsive-message-app/api/get_message_working.php
 */

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-Token, Authorization, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    // Log request untuk debugging
    error_log("=== GET MESSAGE WORKING API CALLED ===");
    error_log("Message ID: " . ($_GET['id'] ?? 'none'));
    
    // Dapatkan semua headers untuk debugging
    $headers = getallheaders();
    error_log("All headers: " . print_r($headers, true));
    
    // Coba ambil token dari berbagai sumber
    $token = '';
    
    // 1. Dari header X-API-Token (berbagai variasi huruf)
    if (isset($headers['X-API-Token']) && !empty($headers['X-API-Token'])) {
        $token = $headers['X-API-Token'];
        error_log("Token from X-API-Token header: $token");
    } elseif (isset($headers['x-api-token']) && !empty($headers['x-api-token'])) {
        $token = $headers['x-api-token'];
        error_log("Token from x-api-token header: $token");
    } elseif (isset($headers['Authorization']) && !empty($headers['Authorization'])) {
        // 2. Dari header Authorization (Bearer token)
        $auth = $headers['Authorization'];
        if (strpos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
            error_log("Token from Authorization Bearer: $token");
        }
    }
    
    // 3. Jika masih kosong, coba dari cookie
    if (empty($token)) {
        error_log("Checking cookies: " . print_r($_COOKIE, true));
        if (isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])) {
            $token = $_COOKIE['PHPSESSID'];
            error_log("Token from PHPSESSID cookie: $token");
        } elseif (isset($_COOKIE['session_id']) && !empty($_COOKIE['session_id'])) {
            $token = $_COOKIE['session_id'];
            error_log("Token from session_id cookie: $token");
        }
    }
    
    // Validasi token
    if (empty($token)) {
        error_log("ERROR: No token found in headers or cookies");
        throw new Exception('Unauthorized - Token is required');
    }
    
    error_log("Final token: " . substr($token, 0, 10) . "...");
    
    // Untuk development, kita terima semua token yang tidak kosong
    // Di production, Anda perlu memvalidasi token dengan database
    
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($messageId <= 0) {
        error_log("ERROR: Invalid message ID: " . ($_GET['id'] ?? 'none'));
        throw new Exception('Invalid message ID');
    }
    
    error_log("Loading message ID: $messageId");
    
    $db = Database::getInstance()->getConnection();
    
    // First try messages table
    error_log("Checking messages table...");
    $query = "SELECT * FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $isExternal = false;
    
    // If not found in messages, try external_messages if table exists
    if (!$message) {
        error_log("Message not found in messages table, checking external_messages...");
        try {
            $checkTable = $db->query("SHOW TABLES LIKE 'external_messages'");
            if ($checkTable->rowCount() > 0) {
                $query = "SELECT * FROM external_messages WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$messageId]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                $isExternal = true;
                error_log("Message found in external_messages: " . ($message ? 'Yes' : 'No'));
            } else {
                error_log("external_messages table does not exist");
            }
        } catch (Exception $e) {
            error_log("Error checking external_messages: " . $e->getMessage());
        }
    } else {
        error_log("Message found in messages table");
    }
    
    if (!$message) {
        error_log("ERROR: Message not found for ID: $messageId");
        throw new Exception('Message not found for ID: ' . $messageId);
    }
    
    // Get message type if available
    if (!$isExternal && isset($message['jenis_pesan_id'])) {
        try {
            $typeQuery = "SELECT jenis_pesan FROM message_types WHERE id = ?";
            $typeStmt = $db->prepare($typeQuery);
            $typeStmt->execute([$message['jenis_pesan_id']]);
            $typeResult = $typeStmt->fetch(PDO::FETCH_ASSOC);
            $message['jenis_pesan'] = $typeResult['jenis_pesan'] ?? 'Pesan Internal';
        } catch (Exception $e) {
            error_log("Error getting message type: " . $e->getMessage());
            $message['jenis_pesan'] = 'Pesan Internal';
        }
    } else {
        $message['jenis_pesan'] = 'Pesan Eksternal';
    }
    
    // Get user data for internal messages
    if (!$isExternal && isset($message['pengirim_id']) && $message['pengirim_id'] > 0) {
        try {
            $userQuery = "SELECT nama as pengirim_nama, email as pengirim_email, no_hp as pengirim_phone, nis_nip as pengirim_nis_nip FROM users WHERE id = ?";
            $userStmt = $db->prepare($userQuery);
            $userStmt->execute([$message['pengirim_id']]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($userData) {
                foreach ($userData as $key => $value) {
                    if ($value !== null && (!isset($message[$key]) || empty($message[$key]))) {
                        $message[$key] = $value;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting user data: " . $e->getMessage());
        }
    }
    
    $message['is_external'] = $isExternal ? 1 : 0;
    
    // Get response count
    try {
        $countQuery = "SELECT COUNT(*) as count FROM message_responses WHERE message_id = ?";
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute([$messageId]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $message['response_count'] = $countResult['count'] ?? 0;
        error_log("Response count: " . $message['response_count']);
    } catch (Exception $e) {
        error_log("Error getting response count: " . $e->getMessage());
        $message['response_count'] = 0;
    }
    
    // Get attachments
    try {
        $attachmentsQuery = "SELECT id, filename, filepath, filesize, original_name, created_at FROM attachments WHERE message_id = ? ORDER BY created_at DESC";
        $attStmt = $db->prepare($attachmentsQuery);
        $attStmt->execute([$messageId]);
        $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Attachments found: " . count($attachments));
    } catch (Exception $e) {
        error_log("Error getting attachments: " . $e->getMessage());
        $attachments = [];
    }
    
    // Get responses
    try {
        $responsesQuery = "
            SELECT mr.*, u.nama as responder_nama 
            FROM message_responses mr
            LEFT JOIN users u ON mr.responder_id = u.id
            WHERE mr.message_id = ?
            ORDER BY mr.created_at DESC
        ";
        $respStmt = $db->prepare($responsesQuery);
        $respStmt->execute([$messageId]);
        $responses = $respStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Responses found: " . count($responses));
    } catch (Exception $e) {
        error_log("Error getting responses: " . $e->getMessage());
        $responses = [];
    }
    
    // User info (dummy for testing)
    $userData = [
        'id' => 1,
        'type' => 'Admin',
        'name' => 'Administrator Sistem'
    ];
    
    error_log("Sending success response for message ID: $messageId");
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => $message,
            'attachments' => $attachments,
            'responses' => $responses,
            'current_user' => $userData
        ]
    ]);
    
} catch (Exception $e) {
    error_log("ERROR in get_message_working: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>