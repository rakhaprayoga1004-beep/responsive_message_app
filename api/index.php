<?php
// api/index.php
// ============================================
// API Main Entry Point - Complete API
// Includes: Messages Management & Message Types Management
// ============================================

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');

// Handle preflight requests (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration
require_once 'config/database.php';

// ============================================
// ROUTING - Parse Request URI
// ============================================
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = str_replace('index.php', '', $script_name);
$path = str_replace($base_path, '', $request_uri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();
    
    // ============================================
    // ROUTE HANDLERS
    // ============================================
    
    // ========== MESSAGES ROUTES ==========
    // GET /messages/types - Get message types
    if ($method === 'GET' && preg_match('/^messages\/types$/', $path)) {
        getMessageTypes($db);
    }
    // GET /messages - Get messages with filters
    elseif ($method === 'GET' && preg_match('/^messages$/', $path)) {
        getMessages($db);
    }
    // GET /messages/{id} - Get message detail
    elseif ($method === 'GET' && preg_match('/^messages\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        getMessageDetail($db, $id);
    }
    // POST /messages - Create new message
    elseif ($method === 'POST' && preg_match('/^messages$/', $path)) {
        createMessage($db);
    }
    // POST /messages/{id}/respond - Respond to message
    elseif ($method === 'POST' && preg_match('/^messages\/(\d+)\/respond$/', $path, $matches)) {
        $id = $matches[1];
        respondToMessage($db, $id);
    }
    // DELETE /messages/{id} - Delete message
    elseif ($method === 'DELETE' && preg_match('/^messages\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        deleteMessage($db, $id);
    }
    
    // ========== MESSAGE TYPES ROUTES ==========
    // GET /message_types - Get all message types with statistics
    elseif ($method === 'GET' && preg_match('/^message_types$/', $path)) {
        getMessageTypesAdmin($db);
    }
    // GET /message_types/stats - Get summary statistics
    elseif ($method === 'GET' && preg_match('/^message_types\/stats$/', $path)) {
        getMessageTypeStats($db);
    }
    // GET /message_types/{id} - Get single message type detail
    elseif ($method === 'GET' && preg_match('/^message_types\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        getMessageTypeDetail($db, $id);
    }
    // POST /message_types - Create new message type
    elseif ($method === 'POST' && preg_match('/^message_types$/', $path)) {
        createMessageType($db);
    }
    // PUT /message_types/{id} - Update message type
    elseif ($method === 'PUT' && preg_match('/^message_types\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        updateMessageType($db, $id);
    }
    // POST /message_types/{id}/toggle - Toggle active status
    elseif ($method === 'POST' && preg_match('/^message_types\/(\d+)\/toggle$/', $path, $matches)) {
        $id = $matches[1];
        toggleMessageTypeStatus($db, $id);
    }
    // DELETE /message_types/{id} - Delete message type
    elseif ($method === 'DELETE' && preg_match('/^message_types\/(\d+)$/', $path, $matches)) {
        $id = $matches[1];
        deleteMessageType($db, $id);
    }
    // 404 - Endpoint not found
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'error' => 'Endpoint not found',
            'path' => $path,
            'method' => $method
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}

// ============================================
// MESSAGES HANDLER FUNCTIONS
// ============================================

/**
 * Get all active message types (for messages dropdown)
 */
function getMessageTypes($db) {
    try {
        $stmt = $db->query("
            SELECT id, jenis_pesan, description, response_deadline_hours, responder_type, is_active 
            FROM message_types 
            WHERE is_active = 1 
            ORDER BY jenis_pesan
        ");
        $types = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $types
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get messages with filters and pagination
 */
function getMessages($db) {
    try {
        // Get user info (from session, token, or query params)
        $user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 1;
        $user_type = $_GET['user_type'] ?? $_SESSION['user_type'] ?? 'Admin';
        
        // Parse filters
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = min(100, max(1, intval($_GET['per_page'] ?? 20)));
        $status = $_GET['status'] ?? 'all';
        $type_id = $_GET['type'] ?? null;
        $priority = $_GET['priority'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        
        $where = ["1=1"];
        $params = [];
        
        // Non-admin only see their own messages
        if ($user_type !== 'Admin') {
            $where[] = "m.pengirim_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        // Apply filters
        if ($status !== 'all') {
            $where[] = "m.status = :status";
            $params[':status'] = $status;
        }
        
        if ($type_id) {
            $where[] = "m.jenis_pesan_id = :type_id";
            $params[':type_id'] = $type_id;
        }
        
        if ($priority !== 'all') {
            $where[] = "m.priority = :priority";
            $params[':priority'] = $priority;
        }
        
        if ($search) {
            $where[] = "(m.isi_pesan LIKE :search OR m.pengirim_nama LIKE :search OR m.pengirim_nis_nip LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if ($date_from) {
            $where[] = "DATE(m.created_at) >= :date_from";
            $params[':date_from'] = $date_from;
        }
        
        if ($date_to) {
            $where[] = "DATE(m.created_at) <= :date_to";
            $params[':date_to'] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as total FROM messages m WHERE $where_clause";
        $count_stmt = $db->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetch()['total'];
        
        // Get messages with pagination
        $sql = "
            SELECT m.*, mt.jenis_pesan,
                   (SELECT COUNT(*) FROM message_responses WHERE message_id = m.id) as response_count,
                   (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
            FROM messages m
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            WHERE $where_clause
            ORDER BY m.created_at DESC
            LIMIT :offset, :limit
        ";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();
        
        // Ambil thumbnail untuk setiap pesan
        foreach ($messages as &$message) {
            $message['thumbnail'] = null;
            
            if ($message['has_attachments'] == 1) {
                $thumb_sql = "SELECT filepath, filename FROM message_attachments WHERE message_id = :message_id LIMIT 1";
                $thumb_stmt = $db->prepare($thumb_sql);
                $thumb_stmt->execute([':message_id' => $message['id']]);
                $thumbnail = $thumb_stmt->fetch();
                
                if ($thumbnail) {
                    if (!empty($thumbnail['filepath'])) {
                        $message['thumbnail'] = $thumbnail['filepath'];
                    } else {
                        $message['thumbnail'] = 'uploads/messages/' . $thumbnail['filename'];
                    }
                }
            }
        }
        
        // Calculate statistics
        $stats = [
            'total' => $total,
            'pending' => getStatusCount($db, $where_clause, $params, 'Pending'),
            'approved' => getStatusCount($db, $where_clause, $params, 'Disetujui'),
            'rejected' => getStatusCount($db, $where_clause, $params, 'Ditolak'),
            'completed' => getStatusCount($db, $where_clause, $params, 'Selesai'),
            'expired' => 0,
            'with_attachments' => getAttachmentCount($db, $where_clause, $params),
            'total_responses' => 0,
            'approved_responses' => 0,
            'rejected_responses' => 0,
            'processed_responses' => 0,
            'completed_responses' => 0,
            'responses_today' => 0,
            'responses_last_24h' => 0
        ];
        
        echo json_encode([
            'status' => 'success',
            'messages' => $messages,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            ],
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get detailed message by ID with responses and attachments
 */
function getMessageDetail($db, $id) {
    try {
        $user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 1;
        $user_type = $_GET['user_type'] ?? $_SESSION['user_type'] ?? 'Admin';
        
        $sql = "SELECT m.*, mt.jenis_pesan 
                FROM messages m 
                LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id 
                WHERE m.id = :id";
        $params = [':id' => $id];
        
        if ($user_type !== 'Admin') {
            $sql .= " AND m.pengirim_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $message = $stmt->fetch();
        
        if (!$message) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message not found'
            ]);
            return;
        }
        
        // Get responses
        $resp_sql = "
            SELECT mr.*, u.nama_lengkap as responder_nama 
            FROM message_responses mr 
            LEFT JOIN users u ON mr.responder_id = u.id 
            WHERE mr.message_id = :message_id 
            ORDER BY mr.created_at DESC
        ";
        $resp_stmt = $db->prepare($resp_sql);
        $resp_stmt->execute([':message_id' => $id]);
        $responses = $resp_stmt->fetchAll();
        
        // Get attachments
        $attach_sql = "
            SELECT id, message_id, filename, original_name, filesize, filepath, created_at 
            FROM message_attachments 
            WHERE message_id = :message_id 
            ORDER BY created_at ASC
        ";
        $attach_stmt = $db->prepare($attach_sql);
        $attach_stmt->execute([':message_id' => $id]);
        $attachments = $attach_stmt->fetchAll();
        
        // Format attachments
        foreach ($attachments as &$attachment) {
            if (empty($attachment['original_name'])) {
                $attachment['original_name'] = $attachment['filename'];
            }
            if (empty($attachment['filepath'])) {
                $attachment['filepath'] = 'uploads/messages/' . $attachment['filename'];
            }
            $attachment['filesize'] = intval($attachment['filesize']);
        }
        
        $message['responses'] = $responses;
        $message['attachments'] = $attachments;
        
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Create a new message (with optional attachments)
 */
function createMessage($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
        
        $jenis_pesan_id = $input['jenis_pesan_id'] ?? null;
        $isi_pesan = $input['isi_pesan'] ?? '';
        $priority = $input['priority'] ?? 'Medium';
        $user_id = $input['user_id'] ?? $_SESSION['user_id'] ?? 1;
        $user_name = $input['user_name'] ?? $_SESSION['nama_lengkap'] ?? 'User';
        $user_nis_nip = $input['user_nis_nip'] ?? $_SESSION['nis_nip'] ?? '';
        
        if (!$jenis_pesan_id || strlen($isi_pesan) < 10) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error' => 'Invalid input. Jenis pesan required and message must be at least 10 characters.'
            ]);
            return;
        }
        
        if (strlen($isi_pesan) > 1000) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message must not exceed 1000 characters.'
            ]);
            return;
        }
        
        $sql = "INSERT INTO messages (jenis_pesan_id, pengirim_id, pengirim_nama, pengirim_nis_nip, isi_pesan, status, priority, has_attachments, created_at, updated_at) 
                VALUES (:jenis_pesan_id, :pengirim_id, :pengirim_nama, :pengirim_nis_nip, :isi_pesan, 'Pending', :priority, 0, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':jenis_pesan_id' => $jenis_pesan_id,
            ':pengirim_id' => $user_id,
            ':pengirim_nama' => $user_name,
            ':pengirim_nis_nip' => $user_nis_nip,
            ':isi_pesan' => htmlspecialchars($isi_pesan),
            ':priority' => $priority
        ]);
        
        $message_id = $db->lastInsertId();
        
        // Handle file uploads
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['tmp_name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $has_attachments = false;
            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp_name) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $original_name = $_FILES['attachments']['name'][$i];
                    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $extension;
                    $filepath = 'uploads/messages/' . $filename;
                    $full_path = __DIR__ . '/../' . $filepath;
                    
                    if (move_uploaded_file($tmp_name, $full_path)) {
                        $attach_sql = "INSERT INTO message_attachments (message_id, filename, original_name, filesize, filepath, created_at) 
                                       VALUES (:message_id, :filename, :original_name, :filesize, :filepath, NOW())";
                        $attach_stmt = $db->prepare($attach_sql);
                        $attach_stmt->execute([
                            ':message_id' => $message_id,
                            ':filename' => $filename,
                            ':original_name' => $original_name,
                            ':filesize' => $_FILES['attachments']['size'][$i],
                            ':filepath' => $filepath
                        ]);
                        $has_attachments = true;
                    }
                }
            }
            
            if ($has_attachments) {
                $update_sql = "UPDATE messages SET has_attachments = 1 WHERE id = :id";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([':id' => $message_id]);
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pesan berhasil dikirim',
            'message_id' => $message_id
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Respond to a message
 */
function respondToMessage($db, $message_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $catatan_respon = $input['catatan_respon'] ?? '';
        $status = $input['status'] ?? '';
        $responder_id = $input['responder_id'] ?? $_SESSION['user_id'] ?? 1;
        
        if (!$catatan_respon || !$status) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error' => 'Missing required fields: catatan_respon and status'
            ]);
            return;
        }
        
        $check_sql = "SELECT id, status FROM messages WHERE id = :id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([':id' => $message_id]);
        $message = $check_stmt->fetch();
        
        if (!$message) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message not found'
            ]);
            return;
        }
        
        $db->beginTransaction();
        
        $resp_sql = "INSERT INTO message_responses (message_id, responder_id, catatan_respon, status, created_at) 
                     VALUES (:message_id, :responder_id, :catatan_respon, :status, NOW())";
        $resp_stmt = $db->prepare($resp_sql);
        $resp_stmt->execute([
            ':message_id' => $message_id,
            ':responder_id' => $responder_id,
            ':catatan_respon' => htmlspecialchars($catatan_respon),
            ':status' => $status
        ]);
        
        $update_sql = "UPDATE messages SET status = :status, responder_id = :responder_id, tanggal_respon = NOW(), updated_at = NOW() WHERE id = :message_id";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            ':status' => $status,
            ':responder_id' => $responder_id,
            ':message_id' => $message_id
        ]);
        
        $db->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Respon berhasil dikirim'
        ]);
        
    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Delete a message
 */
function deleteMessage($db, $id) {
    try {
        $user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 1;
        $user_type = $_GET['user_type'] ?? $_SESSION['user_type'] ?? 'Admin';
        
        $check_sql = "SELECT pengirim_id, is_external FROM messages WHERE id = :id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([':id' => $id]);
        $message = $check_stmt->fetch();
        
        if (!$message) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message not found'
            ]);
            return;
        }
        
        if ($user_type !== 'Admin' && $message['pengirim_id'] != $user_id) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'error' => 'Unauthorized to delete this message'
            ]);
            return;
        }
        
        $db->beginTransaction();
        
        $attach_sql = "SELECT filepath, filename FROM message_attachments WHERE message_id = :id";
        $attach_stmt = $db->prepare($attach_sql);
        $attach_stmt->execute([':id' => $id]);
        $attachments = $attach_stmt->fetchAll();
        
        $db->prepare("DELETE FROM message_responses WHERE message_id = :id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM message_attachments WHERE message_id = :id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM messages WHERE id = :id")->execute([':id' => $id]);
        
        $db->commit();
        
        foreach ($attachments as $attachment) {
            if (!empty($attachment['filepath'])) {
                $file_path = __DIR__ . '/../' . $attachment['filepath'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pesan berhasil dihapus'
        ]);
        
    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================
// MESSAGE TYPES HANDLER FUNCTIONS (ADMIN)
// ============================================

/**
 * Get all message types with statistics (for admin)
 */
function getMessageTypesAdmin($db) {
    try {
        $sql = "
            SELECT 
                mt.*,
                COALESCE(COUNT(m.id), 0) as total_messages,
                COALESCE(SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END), 0) as approved_count,
                COALESCE(SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END), 0) as rejected_count,
                COALESCE(SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END), 0) as processed_count,
                COALESCE(SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END), 0) as completed_count,
                COALESCE(AVG(CASE WHEN m.tanggal_respon IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL END), 0) as avg_response_time
            FROM message_types mt
            LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
            GROUP BY mt.id
            ORDER BY mt.is_active DESC, total_messages DESC, mt.jenis_pesan ASC
        ";
        
        $stmt = $db->query($sql);
        $types = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $types
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get summary statistics for message types
 */
function getMessageTypeStats($db) {
    try {
        $sql = "
            SELECT 
                COUNT(*) as total_types,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types,
                COALESCE(SUM(m.total_messages), 0) as total_messages,
                COALESCE(AVG(CASE WHEN m.total_messages > 0 THEN m.avg_response_time ELSE NULL END), 0) as avg_response_time,
                COALESCE((SUM(m.completed_count) / NULLIF(SUM(m.total_messages), 0)) * 100, 0) as completion_rate
            FROM message_types mt
            LEFT JOIN (
                SELECT 
                    jenis_pesan_id,
                    COUNT(*) as total_messages,
                    AVG(CASE WHEN tanggal_respon IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, created_at, tanggal_respon) 
                        ELSE NULL END) as avg_response_time,
                    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as completed_count
                FROM messages
                GROUP BY jenis_pesan_id
            ) m ON mt.id = m.jenis_pesan_id
        ";
        
        $stmt = $db->query($sql);
        $stats = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get single message type detail
 */
function getMessageTypeDetail($db, $id) {
    try {
        $sql = "
            SELECT 
                mt.*,
                COALESCE(COUNT(m.id), 0) as total_messages,
                COALESCE(SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN m.status = 'Disetujui' THEN 1 ELSE 0 END), 0) as approved_count,
                COALESCE(SUM(CASE WHEN m.status = 'Ditolak' THEN 1 ELSE 0 END), 0) as rejected_count,
                COALESCE(SUM(CASE WHEN m.status = 'Diproses' THEN 1 ELSE 0 END), 0) as processed_count,
                COALESCE(SUM(CASE WHEN m.status = 'Selesai' THEN 1 ELSE 0 END), 0) as completed_count,
                COALESCE(AVG(CASE WHEN m.tanggal_respon IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, m.created_at, m.tanggal_respon) 
                    ELSE NULL END), 0) as avg_response_time
            FROM message_types mt
            LEFT JOIN messages m ON mt.id = m.jenis_pesan_id
            WHERE mt.id = :id
            GROUP BY mt.id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $type = $stmt->fetch();
        
        if (!$type) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message type not found'
            ]);
            return;
        }
        
        $recentSql = "
            SELECT id, reference_number, isi_pesan, status, created_at, 
                   pengirim_nama, pengirim_email
            FROM messages 
            WHERE jenis_pesan_id = :id 
            ORDER BY created_at DESC 
            LIMIT 10
        ";
        
        $recentStmt = $db->prepare($recentSql);
        $recentStmt->execute([':id' => $id]);
        $recentMessages = $recentStmt->fetchAll();
        
        $type['recent_messages'] = $recentMessages;
        
        echo json_encode([
            'status' => 'success',
            'data' => $type
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Create new message type
 */
function createMessageType($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $jenis_pesan = trim($input['jenis_pesan'] ?? '');
        $description = trim($input['description'] ?? '');
        $response_deadline_hours = intval($input['response_deadline_hours'] ?? 72);
        $is_active = intval($input['is_active'] ?? 1);
        
        if (empty($jenis_pesan)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error' => 'Jenis pesan tidak boleh kosong'
            ]);
            return;
        }
        
        $checkSql = "SELECT id FROM message_types WHERE jenis_pesan = :jenis_pesan";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':jenis_pesan' => $jenis_pesan]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'error' => 'Jenis pesan sudah ada'
            ]);
            return;
        }
        
        $sql = "
            INSERT INTO message_types (jenis_pesan, description, response_deadline_hours, is_active, created_at, updated_at)
            VALUES (:jenis_pesan, :description, :response_deadline_hours, :is_active, NOW(), NOW())
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':jenis_pesan' => $jenis_pesan,
            ':description' => $description ?: null,
            ':response_deadline_hours' => $response_deadline_hours,
            ':is_active' => $is_active
        ]);
        
        $id = $db->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Jenis pesan berhasil ditambahkan',
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update message type
 */
function updateMessageType($db, $id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $jenis_pesan = trim($input['jenis_pesan'] ?? '');
        $description = trim($input['description'] ?? '');
        $response_deadline_hours = intval($input['response_deadline_hours'] ?? 72);
        $is_active = intval($input['is_active'] ?? 1);
        
        if (empty($jenis_pesan)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error' => 'Jenis pesan tidak boleh kosong'
            ]);
            return;
        }
        
        $checkSql = "SELECT id FROM message_types WHERE id = :id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message type not found'
            ]);
            return;
        }
        
        $dupSql = "SELECT id FROM message_types WHERE jenis_pesan = :jenis_pesan AND id != :id";
        $dupStmt = $db->prepare($dupSql);
        $dupStmt->execute([':jenis_pesan' => $jenis_pesan, ':id' => $id]);
        if ($dupStmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'error' => 'Jenis pesan sudah ada'
            ]);
            return;
        }
        
        $sql = "
            UPDATE message_types 
            SET jenis_pesan = :jenis_pesan,
                description = :description,
                response_deadline_hours = :response_deadline_hours,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':jenis_pesan' => $jenis_pesan,
            ':description' => $description ?: null,
            ':response_deadline_hours' => $response_deadline_hours,
            ':is_active' => $is_active,
            ':id' => $id
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Jenis pesan berhasil diperbarui'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Toggle message type status
 */
function toggleMessageTypeStatus($db, $id) {
    try {
        $checkSql = "SELECT is_active FROM message_types WHERE id = :id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $type = $checkStmt->fetch();
        
        if (!$type) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Message type not found'
            ]);
            return;
        }
        
        $newStatus = $type['is_active'] ? 0 : 1;
        
        $sql = "UPDATE message_types SET is_active = :is_active, updated_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':is_active' => $newStatus,
            ':id' => $id
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Status berhasil diubah',
            'is_active' => $newStatus == 1
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Delete message type
 */
function deleteMessageType($db, $id) {
    try {
        $checkSql = "SELECT COUNT(*) as count FROM messages WHERE jenis_pesan_id = :id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $usage = $checkStmt->fetch();
        
        if ($usage['count'] > 0) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'error' => 'Jenis pesan tidak dapat dihapus karena masih digunakan oleh ' . $usage['count'] . ' pesan'
            ]);
            return;
        }
        
        $sql = "DELETE FROM message_types WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Jenis pesan berhasil dihapus'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get count of messages by status
 */
function getStatusCount($db, $where, $params, $status) {
    $sql = "SELECT COUNT(*) as count FROM messages m WHERE $where AND m.status = :status";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':status', $status);
    $stmt->execute();
    return $stmt->fetch()['count'];
}

/**
 * Get count of messages with attachments
 */
function getAttachmentCount($db, $where, $params) {
    $sql = "SELECT COUNT(*) as count FROM messages m WHERE $where AND m.has_attachments = 1";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetch()['count'];
}
?>