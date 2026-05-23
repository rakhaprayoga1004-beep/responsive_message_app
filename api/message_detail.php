<?php
// C:\xampp\htdocs\responsive-message-app\api\message_detail.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config/config.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Decode token
$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get message detail
    $message = $db->select("
        SELECT 
            m.id,
            m.reference_number,
            m.isi_pesan,
            m.status,
            m.priority,
            m.tanggal_pesan as created_at,
            m.tanggal_respon,
            m.pengirim_nama,
            m.pengirim_email,
            m.pengirim_phone,
            m.is_external,
            m.has_attachments,
            m.catatan_respon,
            mt.jenis_pesan,
            COALESCE(r.nama_lengkap, 'Belum ada respon') as responder_name,
            r.user_type as responder_type
        FROM messages m
        LEFT JOIN message_types mt ON mt.id = m.jenis_pesan_id
        LEFT JOIN users r ON r.id = m.responder_id
        WHERE m.id = ?
    ", [$messageId]);
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }
    
    // Get attachments
    $attachments = $db->select("
        SELECT 
            id,
            filename,
            filepath,
            filetype,
            filesize,
            created_at
        FROM message_attachments
        WHERE message_id = ?
        ORDER BY created_at ASC
    ", [$messageId]);
    
    // Process filepath for attachments
    foreach ($attachments as &$attachment) {
        if (!empty($attachment['filepath'])) {
            if (strpos($attachment['filepath'], 'http') !== 0) {
                $attachment['filepath'] = 'http://localhost:8090/responsive-message-app/' . $attachment['filepath'];
            }
        }
    }
    
    // Get response history dari tabel messages (responder_id dan catatan_respon)
    $responses = [];
    
    // Cek apakah ada respon dari responder (catatan_respon tidak NULL)
    if (!empty($message[0]['catatan_respon']) && !empty($message[0]['responder_name']) && $message[0]['responder_name'] != 'Belum ada respon') {
        $responses[] = [
            'id' => $message[0]['id'],
            'catatan_respon' => $message[0]['catatan_respon'],
            'created_at' => $message[0]['tanggal_respon'] ?? $message[0]['created_at'],
            'responder_nama' => $message[0]['responder_name'],
            'user_type' => $message[0]['responder_type'] ?? 'Guru',
            'source' => 'message'
        ];
    }
    
    // Get wakepsek reviews (catatan dari wakil kepala sekolah dan kepala sekolah)
    $reviews = $db->select("
        SELECT 
            wr.id,
            wr.catatan as catatan_respon,
            wr.created_at,
            u.nama_lengkap as responder_nama,
            u.user_type,
            u.avatar
        FROM wakepsek_reviews wr
        LEFT JOIN users u ON u.id = wr.reviewer_id
        WHERE wr.message_id = ?
        ORDER BY wr.created_at ASC
    ", [$messageId]);
    
    // Merge responses and reviews
    $allResponses = array_merge($responses, $reviews);
    usort($allResponses, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'message' => $message[0],
        'attachments' => $attachments,
        'responses' => $allResponses
    ]);
    
} catch (Exception $e) {
    error_log("Message detail error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>