<?php
/**
 * Messages REST API
 * File: api/messages.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit;
}

// Authentication
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

if (!$authHeader && !$apiKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user = authenticate($authHeader, $apiKey);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $user);
            break;
        
        case 'POST':
            handlePostRequest($action, $user);
            break;
        
        case 'PUT':
            handlePutRequest($action, $user);
            break;
        
        case 'DELETE':
            handleDeleteRequest($action, $user);
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action, $user) {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'list':
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 10;
            $status = $_GET['status'] ?? 'all';
            $type = $_GET['type'] ?? 'all';
            
            $whereConditions = ["1=1"];
            $params = [];
            
            // Filter by user role
            if ($user['user_type'] !== 'Admin') {
                $whereConditions[] = "pengirim_id = :user_id";
                $params[':user_id'] = $user['id'];
            }
            
            if ($status !== 'all') {
                $whereConditions[] = "status = :status";
                $params[':status'] = $status;
            }
            
            if ($type !== 'all') {
                $whereConditions[] = "jenis_pesan_id = :type";
                $params[':type'] = $type;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total
            $countSql = "SELECT COUNT(*) as total FROM messages WHERE $whereClause";
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get messages
            $sql = "
                SELECT m.*, mt.jenis_pesan, u.nama_lengkap as pengirim_nama 
                FROM messages m
                LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
                LEFT JOIN users u ON m.pengirim_id = u.id
                WHERE $whereClause
                ORDER BY m.created_at DESC
                LIMIT :offset, :limit
            ";
            
            $offset = ($page - 1) * $perPage;
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();
            $messages = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $messages,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;
        
        case 'get':
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Message ID required']);
                return;
            }
            
            $sql = "
                SELECT m.*, mt.jenis_pesan, u.nama_lengkap as pengirim_nama 
                FROM messages m
                LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
                LEFT JOIN users u ON m.pengirim_id = u.id
                WHERE m.id = :id
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $message = $stmt->fetch();
            
            if (!$message) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Message not found']);
                return;
            }
            
            // Check permission
            if ($user['user_type'] !== 'Admin' && $message['pengirim_id'] !== $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                return;
            }
            
            // Get responses
            $responseSql = "
                SELECT mr.*, u.nama_lengkap as responder_nama 
                FROM message_responses mr
                LEFT JOIN users u ON mr.responder_id = u.id
                WHERE mr.message_id = :message_id
                ORDER BY mr.created_at DESC
            ";
            
            $responseStmt = $db->prepare($responseSql);
            $responseStmt->execute([':message_id' => $id]);
            $responses = $responseStmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => $message,
                    'responses' => $responses
                ]
            ]);
            break;
        
        case 'types':
            $stmt = $db->query("SELECT * FROM message_types ORDER BY jenis_pesan");
            $types = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $types]);
            break;
        
        case 'stats':
            $statsSql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) > 72 THEN 1 ELSE 0 END) as expired
                FROM messages
                WHERE pengirim_id = :user_id
            ";
            
            $statsStmt = $db->prepare($statsSql);
            $statsStmt->execute([':user_id' => $user['id']]);
            $stats = $statsStmt->fetch();
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($action, $user) {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $errors = validateMessageData($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }
            
            // Check rate limiting for user
            if (!checkUserMessageLimit($user['id'])) {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Daily message limit reached']);
                return;
            }
            
            $db->beginTransaction();
            
            try {
                $sql = "
                    INSERT INTO messages (
                        jenis_pesan_id, pengirim_id, pengirim_nama, 
                        pengirim_nis_nip, isi_pesan, status, priority, 
                        created_at, updated_at
                    ) VALUES (
                        :jenis_pesan_id, :pengirim_id, :pengirim_nama,
                        :pengirim_nis_nip, :isi_pesan, 'Pending', :priority,
                        NOW(), NOW()
                    )
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':jenis_pesan_id' => $data['jenis_pesan_id'],
                    ':pengirim_id' => $user['id'],
                    ':pengirim_nama' => $user['nama_lengkap'],
                    ':pengirim_nis_nip' => $user['nis_nip'],
                    ':isi_pesan' => Security::sanitize($data['isi_pesan']),
                    ':priority' => $data['priority'] ?? 'Medium'
                ]);
                
                $messageId = $db->lastInsertId();
                
                // Create audit log
                createAuditLog($user['id'], 'CREATE', 'messages', $messageId, null, [
                    'jenis_pesan_id' => $data['jenis_pesan_id'],
                    'priority' => $data['priority'] ?? 'Medium'
                ]);
                
                $db->commit();
                
                // Send notifications
                sendMessageNotifications($messageId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Message created successfully',
                    'message_id' => $messageId
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
        
        case 'respond':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            if (empty($data['message_id']) || empty($data['catatan_respon']) || empty($data['status'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }
            
            // Check if user can respond (must be assigned guru or admin)
            if (!canRespondToMessage($user['id'], $data['message_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Not authorized to respond']);
                return;
            }
            
            $db->beginTransaction();
            
            try {
                // Add response
                $responseSql = "
                    INSERT INTO message_responses (
                        message_id, responder_id, catatan_respon, status, created_at
                    ) VALUES (
                        :message_id, :responder_id, :catatan_respon, :status, NOW()
                    )
                ";
                
                $responseStmt = $db->prepare($responseSql);
                $responseStmt->execute([
                    ':message_id' => $data['message_id'],
                    ':responder_id' => $user['id'],
                    ':catatan_respon' => Security::sanitize($data['catatan_respon']),
                    ':status' => $data['status']
                ]);
                
                // Update message status
                $updateSql = "
                    UPDATE messages 
                    SET status = :status, 
                        responder_id = :responder_id,
                        tanggal_respon = NOW(),
                        updated_at = NOW()
                    WHERE id = :message_id
                ";
                
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute([
                    ':status' => $data['status'],
                    ':responder_id' => $user['id'],
                    ':message_id' => $data['message_id']
                ]);
                
                // Create audit log
                createAuditLog($user['id'], 'RESPOND', 'messages', $data['message_id'], null, [
                    'status' => $data['status'],
                    'responder_id' => $user['id']
                ]);
                
                $db->commit();
                
                // Send notification to sender
                sendResponseNotification($data['message_id'], $user['id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Response submitted successfully'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Validate message data
 */
function validateMessageData($data) {
    $errors = [];
    
    if (empty($data['jenis_pesan_id'])) {
        $errors[] = 'Message type is required';
    }
    
    if (empty($data['isi_pesan'])) {
        $errors[] = 'Message content is required';
    } elseif (strlen($data['isi_pesan']) < 10) {
        $errors[] = 'Message must be at least 10 characters';
    } elseif (strlen($data['isi_pesan']) > 1000) {
        $errors[] = 'Message must not exceed 1000 characters';
    }
    
    return $errors;
}

/**
 * Check user message limit
 */
function checkUserMessageLimit($userId) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE pengirim_id = :user_id 
        AND DATE(created_at) = CURDATE()
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch();
    
    return $result['count'] < MESSAGE_RATE_LIMIT;
}

/**
 * Check if user can respond to message
 */
function canRespondToMessage($userId, $messageId) {
    $db = Database::getInstance()->getConnection();
    
    // Admin can respond to any message
    $userSql = "SELECT user_type FROM users WHERE id = :user_id";
    $userStmt = $db->prepare($userSql);
    $userStmt->execute([':user_id' => $userId]);
    $user = $userStmt->fetch();
    
    if ($user['user_type'] === 'Admin') {
        return true;
    }
    
    // Guru can only respond to messages of their type
    $messageSql = "
        SELECT mt.responder_type 
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        WHERE m.id = :message_id
    ";
    
    $messageStmt = $db->prepare($messageSql);
    $messageStmt->execute([':message_id' => $messageId]);
    $message = $messageStmt->fetch();
    
    return $user['user_type'] === $message['responder_type'];
}

/**
 * Send message notifications
 */
function sendMessageNotifications($messageId) {
    // Get message details
    $db = Database::getInstance()->getConnection();
    
    $sql = "
        SELECT m.*, mt.jenis_pesan, mt.responder_type, u.email, u.phone_number
        FROM messages m
        LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
        LEFT JOIN users u ON m.pengirim_id = u.id
        WHERE m.id = :message_id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':message_id' => $messageId]);
    $message = $stmt->fetch();
    
    if (!$message) return;
    
    // Get assigned guru
    $guruSql = "
        SELECT u.* 
        FROM users u
        WHERE u.user_type = :responder_type
        AND u.is_active = 1
        LIMIT 1
    ";
    
    $guruStmt = $db->prepare($guruSql);
    $guruStmt->execute([':responder_type' => $message['responder_type']]);
    $guru = $guruStmt->fetch();
    
    if ($guru) {
        // Send email notification
        if (!empty($guru['email'])) {
            sendEmailNotification($guru['email'], 'new_message', $message);
        }
        
        // Send WhatsApp notification
        if (WHATSAPP_ENABLED && !empty($guru['phone_number'])) {
            sendWhatsAppNotification($guru['phone_number'], 'new_message', $message);
        }
    }
}

/**
 * Send response notification
 */
function sendResponseNotification($messageId, $responderId) {
    $db = Database::getInstance()->getConnection();
    
    // Get message and sender details
    $sql = "
        SELECT m.*, u.email, u.phone_number, u.nama_lengkap
        FROM messages m
        LEFT JOIN users u ON m.pengirim_id = u.id
        WHERE m.id = :message_id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':message_id' => $messageId]);
    $message = $stmt->fetch();
    
    if (!$message) return;
    
    // Get response details
    $responseSql = "
        SELECT mr.*, u.nama_lengkap as responder_nama
        FROM message_responses mr
        LEFT JOIN users u ON mr.responder_id = u.id
        WHERE mr.message_id = :message_id
        ORDER BY mr.created_at DESC
        LIMIT 1
    ";
    
    $responseStmt = $db->prepare($responseSql);
    $responseStmt->execute([':message_id' => $messageId]);
    $response = $responseStmt->fetch();
    
    if (!$response) return;
    
    // Send email notification
    if (!empty($message['email'])) {
        sendEmailNotification($message['email'], 'response_received', [
            'message' => $message,
            'response' => $response
        ]);
    }
    
    // Send WhatsApp notification
    if (WHATSAPP_ENABLED && !empty($message['phone_number'])) {
        sendWhatsAppNotification($message['phone_number'], 'response_received', [
            'message' => $message,
            'response' => $response
        ]);
    }
}

/**
 * Authenticate user
 */
function authenticate($authHeader, $apiKey) {
    $db = Database::getInstance()->getConnection();
    
    // Check API key first
    if ($apiKey) {
        $stmt = $db->prepare("SELECT * FROM users WHERE api_key = :api_key AND is_active = 1");
        $stmt->execute([':api_key' => $apiKey]);
        return $stmt->fetch();
    }
    
    // Check Bearer token
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        
        $stmt = $db->prepare("
            SELECT u.* 
            FROM users u
            LEFT JOIN api_tokens t ON u.id = t.user_id
            WHERE t.token = :token 
            AND t.expires_at > NOW()
            AND u.is_active = 1
        ");
        
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }
    
    return false;
}

/**
 * Check rate limit
 */
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'api_rate_' . $ip;
    
    // Using file-based rate limiting (in production, use Redis)
    $cacheDir = '../cache/rate_limit/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($key) . '.json';
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (time() - $data['timestamp'] < 3600) { // 1 hour window
            if ($data['count'] >= API_RATE_LIMIT) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'timestamp' => time()];
        }
    } else {
        $data = ['count' => 1, 'timestamp' => time()];
    }
    
    file_put_contents($cacheFile, json_encode($data));
    return true;
}

// Note: Remaining functions (handlePutRequest, handleDeleteRequest, createAuditLog, 
// sendEmailNotification, sendWhatsAppNotification) should be implemented based on
// your specific requirements and infrastructure.