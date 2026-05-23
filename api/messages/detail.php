<?php
// C:\xampp\htdocs\responsive-message-app\api\messages\detail.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/config.php';
require_once '../../config/database.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$userData = null;
if (!empty($token)) {
    $payload = json_decode(base64_decode($token), true);
    if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
        $userData = $payload;
    }
}

if (!$userData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $userData['user_id'];
$userType = $userData['user_type'];
$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($messageId <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'ID pesan tidak valid']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get message detail
    $sql = "
        SELECT m.*, mt.jenis_pesan 
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        WHERE m.id = :id
    ";
    
    if ($userType !== 'Admin') {
        $sql .= " AND m.pengirim_id = :user_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
    if ($userType !== 'Admin') {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $message = $stmt->fetch();
    
    if (!$message) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit;
    }
    
    // Get responses (Level 1: Guru/Admin)
    $responseSql = "
        SELECT mr.*, u.nama_lengkap as responder_nama, u.user_type as responder_type 
        FROM message_responses mr
        LEFT JOIN users u ON mr.responder_id = u.id
        WHERE mr.message_id = :message_id
        ORDER BY mr.created_at ASC
    ";
    $responseStmt = $db->prepare($responseSql);
    $responseStmt->execute([':message_id' => $messageId]);
    $responses = $responseStmt->fetchAll();
    
    // Get reviews (Level 2 & 3: Wakil Kepala & Kepala Sekolah)
    $reviewSql = "
        SELECT wr.*, u.nama_lengkap as reviewer_name, u.user_type as reviewer_type
        FROM wakepsek_reviews wr
        LEFT JOIN users u ON wr.reviewer_id = u.id
        WHERE wr.message_id = :message_id
        ORDER BY wr.created_at ASC
    ";
    $reviewStmt = $db->prepare($reviewSql);
    $reviewStmt->execute([':message_id' => $messageId]);
    $reviews = $reviewStmt->fetchAll();
    
    // Konversi catatan untuk kompatibilitas
    foreach ($reviews as &$review) {
        $review['catatan_respon'] = $review['catatan'] ?? '';
    }
    
    // Get attachments
    $attachmentSql = "SELECT * FROM message_attachments WHERE message_id = :message_id ORDER BY created_at ASC";
    $attachmentStmt = $db->prepare($attachmentSql);
    $attachmentStmt->execute([':message_id' => $messageId]);
    $attachments = $attachmentStmt->fetchAll();
    
    // Process filepath for attachments
    foreach ($attachments as &$attachment) {
        if (!empty($attachment['filepath'])) {
            if (strpos($attachment['filepath'], 'http') !== 0) {
                $attachment['filepath'] = BASE_URL . '/' . $attachment['filepath'];
            }
        }
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $message,
        'responses' => $responses,
        'reviews' => $reviews,
        'attachments' => $attachments
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>