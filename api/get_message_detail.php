<?php
/**
 * API Endpoint untuk mengambil detail pesan
 * File: responsive-message-app/api/get_message_detail.php
 */

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log untuk debugging
error_log("=== GET MESSAGE DETAIL API CALLED ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("Cookie: " . print_r($_COOKIE, true));

// Load konfigurasi
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

try {
    // Debug: cek session sebelum check
    error_log("Session ID before check: " . session_id());
    error_log("Session data before check: " . print_r($_SESSION, true));
    
    // Get session menggunakan singleton pattern
    $session = SessionManager::getInstance();
    $sessionData = $session->checkSession();
    
    if (!$sessionData) {
        error_log("Session check failed: No valid session");
        error_log("Session ID from cookie: " . ($_COOKIE['PHPSESSID'] ?? 'none'));
        error_log("Session ID from session_id(): " . session_id());
        
        // Cek apakah session file exists - TANPA file_get_contents
        $sessionFile = session_save_path() . '/sess_' . session_id();
        error_log("Session file: " . $sessionFile);
        error_log("Session file exists: " . (file_exists($sessionFile) ? 'Yes' : 'No'));
        if (file_exists($sessionFile)) {
            error_log("Session file size: " . filesize($sessionFile) . " bytes");
            error_log("Session file readable: " . (is_readable($sessionFile) ? 'Yes' : 'No'));
        }
        
        throw new Exception('Unauthorized - No valid session');
    }
    
    error_log("Session valid for user: " . $sessionData['user_id']);
    error_log("User type: " . $sessionData['user_type']);
    error_log("User name: " . $sessionData['user_name']);
    
    $currentUserId = $sessionData['user_id'];
    $userType = $sessionData['user_type'];
    
    // Get message ID from query parameter
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($messageId <= 0) {
        error_log("Invalid message ID: " . $messageId);
        throw new Exception('Invalid message ID');
    }
    
    error_log("Loading message detail for ID: " . $messageId);
    
    // Get database connection menggunakan singleton
    $db = Database::getInstance()->getConnection();
    
    // Query to get message detail with related data (internal messages)
    $query = "
        SELECT 
            m.*,
            mp.nama as jenis_pesan,
            u.nama as pengirim_nama,
            u.email as pengirim_email,
            u.no_hp as pengirim_phone,
            u.nis_nip as pengirim_nis_nip,
            (
                SELECT COUNT(*) 
                FROM message_responses mr 
                WHERE mr.message_id = m.id
            ) as response_count
        FROM messages m
        LEFT JOIN message_types mp ON m.jenis_pesan_id = mp.id
        LEFT JOIN users u ON m.pengirim_id = u.id
        WHERE m.id = :id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $messageId, PDO::PARAM_INT);
    $stmt->execute();
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        error_log("Message not found in internal messages, checking external...");
        
        // Check if it's an external message
        $extQuery = "
            SELECT 
                em.*,
                'External' as jenis_pesan,
                em.pengirim_nama,
                em.pengirim_email,
                em.pengirim_phone,
                em.pengirim_nis_nip,
                0 as response_count
            FROM external_messages em
            WHERE em.id = :id
        ";
        
        $extStmt = $db->prepare($extQuery);
        $extStmt->bindParam(':id', $messageId, PDO::PARAM_INT);
        $extStmt->execute();
        
        $message = $extStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) {
            error_log("Message not found in external messages either");
            throw new Exception('Message not found');
        }
        
        $message['is_external'] = 1;
        $message['pengirim_id'] = $message['pengirim_id'] ?? 0;
        error_log("External message found for ID: " . $messageId);
    } else {
        $message['is_external'] = 0;
        error_log("Internal message found for ID: " . $messageId);
    }
    
    // Get attachments
    $attachmentsQuery = "
        SELECT 
            id,
            filename,
            filepath,
            filesize,
            CASE 
                WHEN filepath LIKE '%external%' THEN 1 
                ELSE 0 
            END as is_external,
            original_name,
            created_at
        FROM attachments 
        WHERE message_id = :message_id
        ORDER BY created_at DESC
    ";
    
    $attStmt = $db->prepare($attachmentsQuery);
    $attStmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
    $attStmt->execute();
    
    $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($attachments) . " attachments for message ID: " . $messageId);
    
    // Get responses
    $responsesQuery = "
        SELECT 
            mr.*,
            u.nama as responder_nama
        FROM message_responses mr
        LEFT JOIN users u ON mr.responder_id = u.id
        WHERE mr.message_id = :message_id
        ORDER BY mr.created_at DESC
    ";
    
    $respStmt = $db->prepare($responsesQuery);
    $respStmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
    $respStmt->execute();
    
    $responses = $respStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($responses) . " responses for message ID: " . $messageId);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => $message,
            'attachments' => $attachments,
            'responses' => $responses,
            'current_user' => [
                'id' => $currentUserId,
                'type' => $userType
            ]
        ]
    ]);
    
    error_log("Response sent successfully for message ID: " . $messageId);
    
} catch (Exception $e) {
    error_log("Error in get_message_detail: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Gunakan 401 untuk unauthorized, 400 untuk error lainnya
    if (strpos($e->getMessage(), 'Unauthorized') !== false) {
        http_response_code(401);
    } elseif (strpos($e->getMessage(), 'Message not found') !== false) {
        http_response_code(404);
    } else {
        http_response_code(400);
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>