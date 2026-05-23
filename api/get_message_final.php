<?php
/**
 * Get Message Detail Final Version - CLEAN
 * File: /api/get_message_final.php
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
require_once '../config/database.php';

// ============================================================================
// CONSTANTS - CEK APAKAH SUDAH DEFINED SEBELUMNYA
// ============================================================================
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost:8090');
}
if (!defined('APP_PATH')) {
    define('APP_PATH', '/responsive-message-app');
}
if (!defined('UPLOAD_PATH_MESSAGES')) {
    define('UPLOAD_PATH_MESSAGES', '/responsive-message-app/uploads/messages/');
}
if (!defined('UPLOAD_PATH_EXTERNAL')) {
    define('UPLOAD_PATH_EXTERNAL', '/responsive-message-app/uploads/external_messages/');
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get database connection
 */
function getDbConnection() {
    try {
        $database = Database::getInstance();
        return $database->getConnection();
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Validate session and get current user
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'type' => $_SESSION['user_type'],
        'name' => $_SESSION['nama_lengkap'] ?? $_SESSION['user_name'] ?? 'Unknown',
        'username' => $_SESSION['username'] ?? ''
    ];
}

/**
 * Format datetime for JSON response
 */
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return null;
    }
    return $datetime;
}

/**
 * Build full image URL from filepath
 */
function buildImageUrl($filepath, $isExternal = false) {
    if (empty($filepath)) {
        return '';
    }
    
    // If already full URL
    if (strpos($filepath, 'http://') === 0 || strpos($filepath, 'https://') === 0) {
        return $filepath;
    }
    
    // Remove leading slash if exists
    $cleanPath = ltrim($filepath, '/');
    
    // Build URL
    if ($isExternal) {
        return BASE_URL . UPLOAD_PATH_EXTERNAL . basename($cleanPath);
    } else {
        return BASE_URL . UPLOAD_PATH_MESSAGES . basename($cleanPath);
    }
}

/**
 * Get jabatan from user type
 */
function getJabatanFromUserType($userType) {
    if (empty($userType)) return '';
    
    $jabatanMap = [
        'Admin' => 'Admin',
        'Guru_BK' => 'Guru BK',
        'Wakil_Kepala' => 'Wakil Kepala Sekolah',
        'Kepala_Sekolah' => 'Kepala Sekolah',
        'Guru_Kurikulum' => 'Guru Kurikulum',
        'Guru_Kesiswaan' => 'Guru Kesiswaan',
        'Guru_Sarana' => 'Guru Sarana',
        'Guru_Humas' => 'Guru Humas',
        'Guru' => 'Guru',
        'Siswa' => 'Siswa',
        'Orang_Tua' => 'Orang Tua'
    ];
    
    return $jabatanMap[$userType] ?? $userType;
}

// ============================================================================
// MAIN SCRIPT
// ============================================================================

try {
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }
    
    // Get message ID from query string
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
        exit();
    }
    
    // Get current user
    $currentUser = getCurrentUser();
    
    // Jika tidak ada session, gunakan default untuk testing (HAPUS DI PRODUCTION)
    if (!$currentUser) {
        $currentUser = [
            'id' => 1,
            'type' => 'Admin',
            'name' => 'Administrator Sistem',
            'username' => 'admin'
        ];
    }
    
    // Get database connection
    $db = getDbConnection();
    
    // ============================================================================
    // 1. FETCH MESSAGE DATA
    // ============================================================================
    
    $messageSql = "
        SELECT 
            m.*,
            mt.jenis_pesan,
            mt.responder_type,
            mt.response_deadline_hours,
            mt.color_code,
            mt.icon_class,
            u.nama_lengkap as pengirim_nama_lengkap,
            u.user_type as pengirim_user_type,
            u.email as pengirim_email_user,
            u.phone_number as pengirim_phone_user
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        LEFT JOIN users u ON m.pengirim_id = u.id
        WHERE m.id = :id
    ";
    
    $messageStmt = $db->prepare($messageSql);
    $messageStmt->execute([':id' => $id]);
    $message = $messageStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit();
    }
    
    // ============================================================================
    // 2. FETCH ATTACHMENTS
    // ============================================================================
    
    $attachmentsSql = "
        SELECT 
            id,
            message_id,
            user_id,
            filename,
            filepath,
            filetype,
            filesize,
            is_approved,
            virus_scan_status,
            download_count,
            created_at
        FROM message_attachments 
        WHERE message_id = :message_id
        ORDER BY created_at ASC
    ";
    
    $attachmentsStmt = $db->prepare($attachmentsSql);
    $attachmentsStmt->execute([':message_id' => $id]);
    $attachments = $attachmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process attachments
    foreach ($attachments as &$attachment) {
        $attachment['image_url'] = buildImageUrl(
            $attachment['filepath'] ?? '', 
            $message['is_external'] ?? 0
        );
        
        // Clean filename for display
        $filename = $attachment['filename'] ?? '';
        if (strpos($filename, '_') !== false) {
            $parts = explode('_', $filename);
            if (count($parts) > 3) {
                $attachment['display_name'] = implode('_', array_slice($parts, 3));
            } else if (count($parts) > 2) {
                $attachment['display_name'] = implode('_', array_slice($parts, 2));
            } else {
                $attachment['display_name'] = $filename;
            }
        } else {
            $attachment['display_name'] = $filename;
        }
        
        // Format file size
        $size = intval($attachment['filesize'] ?? 0);
        if ($size < 1024) {
            $attachment['formatted_size'] = $size . ' B';
        } else if ($size < 1024 * 1024) {
            $attachment['formatted_size'] = round($size / 1024, 1) . ' KB';
        } else {
            $attachment['formatted_size'] = round($size / (1024 * 1024), 1) . ' MB';
        }
        
        // Determine if image
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'bmp'];
        $attachment['is_image'] = in_array($ext, $imageExtensions);
    }
    
    // ============================================================================
    // 3. FETCH MESSAGE RESPONSES
    // ============================================================================
    
    $responsesSql = "
        SELECT 
            mr.*,
            u.nama_lengkap as responder_nama,
            u.user_type as responder_type,
            u.avatar as responder_avatar
        FROM message_responses mr
        LEFT JOIN users u ON mr.responder_id = u.id
        WHERE mr.message_id = :message_id
        ORDER BY mr.created_at ASC  /* ASC untuk urutan paling awal di atas */
    ";
    
    $responsesStmt = $db->prepare($responsesSql);
    $responsesStmt->execute([':message_id' => $id]);
    $responses = $responsesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================================================
    // 4. FETCH WAKEPSEK REVIEWS
    // ============================================================================
    
    $wakepsekSql = "
        SELECT 
            wr.*,
            u.nama_lengkap as reviewer_nama,
            u.user_type as reviewer_type,
            u.avatar as reviewer_avatar
        FROM wakepsek_reviews wr
        LEFT JOIN users u ON wr.reviewer_id = u.id
        WHERE wr.message_id = :message_id
        ORDER BY wr.created_at ASC  /* ASC untuk urutan paling awal di atas */
    ";
    
    $wakepsekStmt = $db->prepare($wakepsekSql);
    $wakepsekStmt->execute([':message_id' => $id]);
    $wakepsekReviews = $wakepsekStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================================================
    // 5. COMBINE ALL RESPONSES (URUTAN PALING AWAL DI ATAS)
    // ============================================================================
    
    $allResponses = [];
    
    // Add message responses
    foreach ($responses as $response) {
        $allResponses[] = [
            'id' => 'resp_' . $response['id'],
            'responder_id' => $response['responder_id'],
            'responder_nama' => $response['responder_nama'] ?? 'Unknown',
            'responder_jabatan' => getJabatanFromUserType($response['responder_type'] ?? ''),
            'responder_type' => $response['responder_type'] ?? '',
            'catatan_respon' => $response['catatan_respon'] ?? '',
            'status' => $response['status'] ?? 'Pending',
            'created_at' => $response['created_at'],
            'source' => 'message_response'
        ];
    }
    
    // Add wakepsek reviews
    foreach ($wakepsekReviews as $review) {
        $allResponses[] = [
            'id' => 'review_' . $review['id'],
            'responder_id' => $review['reviewer_id'],
            'responder_nama' => $review['reviewer_nama'] ?? 'Unknown',
            'responder_jabatan' => getJabatanFromUserType($review['reviewer_type'] ?? ''),
            'responder_type' => $review['reviewer_type'] ?? '',
            'catatan_respon' => $review['review_notes'] ?? $review['catatan'] ?? '',
            'status' => $review['review_status'] ?? $review['status'] ?? 'Pending',
            'created_at' => $review['created_at'],
            'source' => 'wakepsek_review'
        ];
    }
    
    // Sort by created_at (OLDEST FIRST - untuk urutan paling awal di atas)
    usort($allResponses, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    // ============================================================================
    // 6. PREPARE RESPONSE
    // ============================================================================
    
    $response = [
        'success' => true,
        'data' => [
            'message' => [
                'id' => $message['id'],
                'reference_number' => $message['reference_number'] ?? '',
                'tanggal_pesan' => formatDateTime($message['tanggal_pesan'] ?? $message['created_at']),
                'jenis_pesan_id' => $message['jenis_pesan_id'],
                'jenis_pesan' => $message['jenis_pesan'] ?? '',
                'pengirim_id' => $message['pengirim_id'],
                'pengirim_nama' => $message['pengirim_nama'] ?? $message['pengirim_nama_lengkap'] ?? 'Unknown',
                'pengirim_email' => $message['pengirim_email'] ?? $message['pengirim_email_user'] ?? '',
                'pengirim_phone' => $message['pengirim_phone'] ?? $message['pengirim_phone_user'] ?? '',
                'pengirim_nis_nip' => $message['pengirim_nis_nip'] ?? '',
                'isi_pesan' => $message['isi_pesan'] ?? '',
                'status' => $message['status'] ?? 'Pending',
                'priority' => $message['priority'] ?? 'Medium',
                'responder_id' => $message['responder_id'],
                'is_external' => intval($message['is_external'] ?? 0),
                'has_attachments' => intval($message['has_attachments'] ?? 0),
                'catatan_respon' => $message['catatan_respon'] ?? '',
                'tanggal_respon' => formatDateTime($message['tanggal_respon']),
                'created_at' => $message['created_at'],
                'updated_at' => $message['updated_at'],
                'response_count' => count($allResponses),
                'responder_type' => $message['responder_type'] ?? '',
                'response_deadline_hours' => $message['response_deadline_hours'] ?? 72,
                'color_code' => $message['color_code'] ?? '#0d6efd',
                'icon_class' => $message['icon_class'] ?? 'fas fa-envelope'
            ],
            'attachments' => $attachments,
            'responses' => $allResponses,
            'current_user' => $currentUser
        ]
    ];
    
    // Send response
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>