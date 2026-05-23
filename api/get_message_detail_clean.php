<?php
/**
 * API Endpoint untuk mengambil detail pesan - CLEAN VERSION
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load konfigurasi
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

try {
    // Get session menggunakan singleton pattern
    $session = SessionManager::getInstance();
    $sessionData = $session->checkSession();
    
    if (!$sessionData) {
        // Log sederhana tanpa file_get_contents
        error_log("Session check failed for ID: " . session_id());
        throw new Exception('Unauthorized - No valid session');
    }
    
    $currentUserId = $sessionData['user_id'];
    $userType = $sessionData['user_type'];
    
    // Get message ID
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($messageId <= 0) {
        throw new Exception('Invalid message ID');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Query untuk internal messages
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
        // Check external messages
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
            throw new Exception('Message not found');
        }
        
        $message['is_external'] = 1;
    } else {
        $message['is_external'] = 0;
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
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    
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