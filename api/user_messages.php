<?php
/**
 * API untuk mengambil pesan user
 * Lokasi: /responsive-message-app/api/user_messages.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = Database::getInstance();
    
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($userId <= 0) {
        throw new Exception('User ID tidak valid');
    }
    
    // Ambil pesan yang dikirim oleh user
    $sql = "
        SELECT 
            m.id,
            m.reference_number,
            m.isi_pesan,
            m.status,
            m.created_at,
            m.updated_at,
            mt.id as jenis_pesan_id,
            mt.jenis_pesan,
            u_responder.nama_lengkap as responder_name,
            CASE 
                WHEN mr.id IS NOT NULL THEN mr.response_text 
                ELSE NULL 
            END as response_text
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        LEFT JOIN users u_responder ON m.responder_id = u_responder.id
        LEFT JOIN message_responses mr ON m.id = mr.message_id AND mr.responder_id = m.responder_id
        WHERE m.pengirim_id = :user_id
        ORDER BY m.created_at DESC
    ";
    
    $messages = $db->select($sql, [':user_id' => $userId]);
    
    // Format data untuk response
    $formattedMessages = [];
    foreach ($messages as $message) {
        $formattedMessages[] = [
            'id' => (int)$message['id'],
            'reference_number' => $message['reference_number'],
            'isi_pesan' => $message['isi_pesan'],
            'status' => $message['status'],
            'jenis_pesan_id' => (int)$message['jenis_pesan_id'],
            'jenis_pesan' => $message['jenis_pesan'],
            'responder_name' => $message['responder_name'] ?? null,
            'response_text' => $message['response_text'] ?? null,
            'created_at' => $message['created_at'],
            'updated_at' => $message['updated_at'],
        ];
    }
    
    // Hitung statistik
    $total = count($formattedMessages);
    $pending = count(array_filter($messages, function($m) { return $m['status'] == 'Pending'; }));
    $approved = count(array_filter($messages, function($m) { return $m['status'] == 'Disetujui'; }));
    $rejected = count(array_filter($messages, function($m) { return $m['status'] == 'Ditolak'; }));
    $processed = count(array_filter($messages, function($m) { return $m['status'] == 'Diproses'; }));
    
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'stats' => [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'processed' => $processed,
        ]
    ]);
    
} catch (Exception $e) {
    error_log("User messages error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'messages' => []
    ]);
}
?>