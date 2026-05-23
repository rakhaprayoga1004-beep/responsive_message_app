<?php
// modules/admin/api/get_message_detail.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../../config/database.php';

// Authentication
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

// Allow Admin, Kepala_Sekolah, Wakil_Kepala
if (!$userData || !in_array($userData['user_type'], ['Admin', 'Kepala_Sekolah', 'Wakil_Kepala'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$message_id = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;

if ($message_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Query lengkap dengan semua informasi untuk Admin
    $query = "SELECT 
                m.id,
                m.reference_number,
                m.isi_pesan,
                m.status,
                m.priority,
                m.created_at,
                m.tanggal_respon,
                m.is_external,
                m.jenis_pesan_id,
                -- Informasi pengirim
                CASE 
                    WHEN m.is_external = 1 THEN es.nama_lengkap
                    ELSE COALESCE(u.nama_lengkap, m.pengirim_nama, 'Unknown')
                END as pengirim_nama_display,
                CASE 
                    WHEN m.is_external = 1 THEN 'External'
                    ELSE COALESCE(u.user_type, 'Internal')
                END as pengirim_tipe,
                CASE 
                    WHEN m.is_external = 1 THEN es.email
                    ELSE u.email
                END as pengirim_email,
                CASE 
                    WHEN m.is_external = 1 THEN es.phone_number
                    ELSE u.phone_number
                END as pengirim_phone,
                CASE 
                    WHEN m.is_external = 1 THEN 
                        CONCAT('EXT-', 
                            CASE 
                                WHEN es.phone_number IS NOT NULL AND es.phone_number != '' THEN es.phone_number
                                WHEN es.email IS NOT NULL AND es.email != '' THEN SUBSTRING_INDEX(es.email, '@', 1)
                                ELSE CONCAT('ID', es.id)
                            END
                        )
                    ELSE 
                        COALESCE(u.username, CONCAT('USR-', u.id))
                END as nomor_identitas,
                -- Informasi jenis pesan
                mt.jenis_pesan as message_jenis_pesan,
                mt.responder_type as message_responder_type,
                mt.response_deadline_hours,
                -- Waktu tersisa
                GREATEST(0, mt.response_deadline_hours - TIMESTAMPDIFF(HOUR, m.created_at, NOW())) as hours_remaining,
                CASE 
                    WHEN TIMESTAMPDIFF(HOUR, m.created_at, NOW()) >= mt.response_deadline_hours THEN 'danger'
                    WHEN (mt.response_deadline_hours - TIMESTAMPDIFF(HOUR, m.created_at, NOW())) <= 24 THEN 'warning'
                    ELSE 'success'
                END as urgency_color,
                -- INFORMASI RESPON GURU (yang terbaru)
                mr.id as response_id,
                mr.catatan_respon as last_response,
                mr.status as response_status,
                mr.created_at as response_date,
                ru.nama_lengkap as responder_name,
                ru.user_type as responder_type,
                -- INFORMASI REVIEW PIMPINAN (Wakepsek/Kepsek)
                wr.id as review_id,
                wr.catatan as review_catatan,
                wr.created_at as review_date,
                reviewer.nama_lengkap as reviewer_name,
                reviewer.user_type as reviewer_type,
                -- Status apakah sudah ada respon
                CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END as has_response,
                -- Hitung jumlah attachment
                (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
            FROM messages m
            LEFT JOIN users u ON m.pengirim_id = u.id
            LEFT JOIN external_senders es ON m.external_sender_id = es.id
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            LEFT JOIN message_responses mr ON m.id = mr.message_id 
                AND mr.created_at = (SELECT MAX(created_at) FROM message_responses WHERE message_id = m.id)
            LEFT JOIN users ru ON mr.responder_id = ru.id
            LEFT JOIN wakepsek_reviews wr ON m.id = wr.message_id
            LEFT JOIN users reviewer ON wr.reviewer_id = reviewer.id
            WHERE m.id = :message_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':message_id', $message_id);
    $stmt->execute();
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        // Ambil attachment jika ada
        $attachments = [];
        if ($message['attachment_count'] > 0) {
            $attStmt = $db->prepare("
                SELECT id, filepath, filename, original_name, filesize, filetype, created_at
                FROM message_attachments 
                WHERE message_id = :message_id
            ");
            $attStmt->bindParam(':message_id', $message_id);
            $attStmt->execute();
            $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Format response
        $response = [
            'success' => true,
            'message' => [
                'id' => $message['id'],
                'reference_number' => $message['reference_number'],
                'isi_pesan' => $message['isi_pesan'],
                'status' => $message['status'],
                'priority' => $message['priority'],
                'created_at' => $message['created_at'],
                'tanggal_respon' => $message['tanggal_respon'],
                'is_external' => $message['is_external'],
                'jenis_pesan' => $message['message_jenis_pesan'],
                'hours_remaining' => $message['hours_remaining'],
                'urgency_color' => $message['urgency_color'],
                // Informasi pengirim
                'pengirim_nama_display' => $message['pengirim_nama_display'],
                'pengirim_tipe' => $message['pengirim_tipe'],
                'pengirim_email' => $message['pengirim_email'],
                'pengirim_phone' => $message['pengirim_phone'],
                'nomor_identitas' => $message['nomor_identitas'],
                // Informasi respon guru
                'has_response' => $message['has_response'] == 1,
                'last_response' => $message['last_response'],
                'response_status' => $message['response_status'],
                'response_date' => $message['response_date'],
                'responder_name' => $message['responder_name'],
                'responder_type' => $message['responder_type'],
                // Informasi review pimpinan
                'has_review' => !empty($message['review_id']),
                'review_id' => $message['review_id'],
                'review_catatan' => $message['review_catatan'],
                'review_date' => $message['review_date'],
                'reviewer_name' => $message['reviewer_name'],
                'reviewer_type' => $message['reviewer_type'],
                // Attachments
                'attachments' => $attachments,
                'attachment_count' => $message['attachment_count']
            ]
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
    }
    
} catch (Exception $e) {
    error_log("Get Message Detail Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>