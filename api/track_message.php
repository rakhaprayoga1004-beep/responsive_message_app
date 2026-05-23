<?php
// api/track_message.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load database configuration
$rootPath = realpath(__DIR__ . '/..');
if ($rootPath === false) {
    echo json_encode(['success' => false, 'message' => 'Root path not found']);
    exit;
}

$configPath = $rootPath . '/config/config.php';
if (!file_exists($configPath)) {
    // Coba path alternatif
    $configPath = __DIR__ . '/../config/config.php';
    if (!file_exists($configPath)) {
        echo json_encode(['success' => false, 'message' => 'Config file not found']);
        exit;
    }
}

require_once $configPath;

// Koneksi database manual
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$port = defined('DB_PORT') ? DB_PORT : '3307';
$dbname = defined('DB_NAME') ? DB_NAME : 'responsive_message_db';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Ambil reference dari POST atau GET
$reference = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['tracking_reference'] ?? '');
} else {
    $reference = trim($_GET['ref'] ?? $_GET['tracking_reference'] ?? '');
}

if (empty($reference)) {
    echo json_encode(['success' => false, 'message' => 'Nomor referensi tidak boleh kosong']);
    exit;
}

try {
    // Cari pesan berdasarkan reference_number
    $sql = "SELECT m.*, mt.jenis_pesan, mt.responder_type 
            FROM messages m 
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id 
            WHERE m.reference_number = :ref 
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':ref' => $reference]);
    $message = $stmt->fetch();
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit;
    }
    
    // Ambil responses
    $responses = [];
    $resStmt = $db->prepare("
        SELECT r.*, u.nama_lengkap as responder_name, u.user_type
        FROM message_responses r
        LEFT JOIN users u ON r.responder_id = u.id
        WHERE r.message_id = :message_id
        ORDER BY r.created_at ASC
    ");
    $resStmt->execute([':message_id' => $message['id']]);
    $responses = $resStmt->fetchAll();
    
    // Ambil reviews
    $reviews = [];
    $revStmt = $db->prepare("
        SELECT wr.*, u.nama_lengkap as reviewer_name, u.user_type
        FROM wakepsek_reviews wr
        LEFT JOIN users u ON wr.reviewer_id = u.id
        WHERE wr.message_id = :message_id
        ORDER BY wr.created_at ASC
    ");
    $revStmt->execute([':message_id' => $message['id']]);
    $reviews = $revStmt->fetchAll();
    
    // Kirim response
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => $message,
            'responses' => $responses,
            'reviews' => $reviews,
            'html' => renderTrackingHtml($message, $responses, $reviews)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Track message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function renderTrackingHtml($message, $responses, $reviews) {
    $status = $message['status'] ?? 'Pending';
    $statusColor = $status == 'Disetujui' ? '#28a745' : ($status == 'Ditolak' ? '#dc3545' : '#ffc107');
    $statusText = $status == 'Disetujui' ? 'Disetujui' : ($status == 'Ditolak' ? 'Ditolak' : ($status == 'Diproses' ? 'Diproses' : 'Menunggu'));
    
    $html = '
    <div style="padding: 15px; background: linear-gradient(135deg, #0b4d8a 0%, #1a73e8 100%); border-radius: 12px; color: white;">
        <div style="background: rgba(255,255,255,0.15); border-radius: 10px; padding: 12px; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap;">
                <h3 style="font-size: 16px; margin: 0;">
                    <i class="fas fa-search-location" style="color: #ffd700; font-size: 14px;"></i>
                    #' . htmlspecialchars($message['reference_number']) . '
                </h3>
                <span style="padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: ' . $statusColor . '">
                    ' . $statusText . '
                </span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 11px; margin-top: 8px;">
                <div><i class="fas fa-user" style="width: 16px;"></i> ' . htmlspecialchars($message['pengirim_nama'] ?? 'Unknown') . '</div>
                <div><i class="fas fa-tag" style="width: 16px;"></i> ' . htmlspecialchars($message['jenis_pesan'] ?? 'Pesan') . '</div>
                <div><i class="fas fa-calendar" style="width: 16px;"></i> ' . date('d/m/Y H:i', strtotime($message['created_at'])) . '</div>
                <div><i class="fas fa-hourglass-half" style="width: 16px;"></i> ' . (!empty($message['expired_at']) ? date('d/m/Y H:i', strtotime($message['expired_at'])) : '-') . '</div>
            </div>
        </div>
        <div style="margin-top: 10px; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
            <p style="margin: 0; font-size: 13px; line-height: 1.5;">' . nl2br(htmlspecialchars($message['isi_pesan'] ?? '')) . '</p>
        </div>';
    
    if (!empty($responses)) {
        $html .= '
        <div style="margin-top: 15px;">
            <h4 style="font-size: 13px; margin-bottom: 10px;"><i class="fas fa-reply-all"></i> Respon Guru:</h4>';
        foreach ($responses as $resp) {
            $responderType = str_replace('Guru_', '', $resp['user_type'] ?? 'Guru');
            $html .= '
            <div style="background: rgba(40,167,69,0.2); border-radius: 8px; padding: 10px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; flex-wrap: wrap;">
                    <strong>' . htmlspecialchars($resp['responder_name'] ?? 'Guru') . ' (' . $responderType . ')</strong>
                    <small>' . date('d/m/Y H:i', strtotime($resp['created_at'])) . '</small>
                </div>
                <p style="margin: 0; font-size: 12px;">' . nl2br(htmlspecialchars($resp['catatan_respon'] ?? '')) . '</p>
                <div><small>Status: ' . htmlspecialchars($resp['status'] ?? 'Diproses') . '</small></div>
            </div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($reviews)) {
        $html .= '
        <div style="margin-top: 15px;">
            <h4 style="font-size: 13px; margin-bottom: 10px;"><i class="fas fa-clipboard-list"></i> Review Pimpinan:</h4>';
        foreach ($reviews as $review) {
            $reviewerType = $review['user_type'] == 'Kepala_Sekolah' ? 'Kepala Sekolah' : 'Wakil Kepala Sekolah';
            $html .= '
            <div style="background: rgba(23,162,184,0.2); border-radius: 8px; padding: 10px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong>' . htmlspecialchars($reviewerType) . '</strong>
                    <small>' . date('d/m/Y H:i', strtotime($review['created_at'])) . '</small>
                </div>
                <p style="margin: 0; font-size: 12px;">' . nl2br(htmlspecialchars($review['catatan'] ?? '')) . '</p>
            </div>';
        }
        $html .= '</div>';
    }
    
    $html .= '
        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <span style="display: flex; align-items: center; gap: 4px; font-size: 10px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: #28a745;"></span> Selesai</span>
            <span style="display: flex; align-items: center; gap: 4px; font-size: 10px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span> Proses</span>
            <span style="display: flex; align-items: center; gap: 4px; font-size: 10px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: #6c757d;"></span> Tunggu</span>
        </div>
    </div>';
    
    return $html;
}
?>