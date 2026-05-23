<?php
// api/admin/settings.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../../config/config.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

if (!$userData || $userData['user_type'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    if ($method === 'GET') {
        if ($action === 'general') {
            // Get general settings
            $sql = "SELECT setting_key, setting_value, setting_type, description, is_editable 
                    FROM system_settings 
                    WHERE category = 'General' 
                    ORDER BY display_order";
            $settings = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            
        } elseif ($action === 'message_types') {
            // Get message types with stats
            $sql = "SELECT mt.*, 
                    (SELECT COUNT(*) FROM messages WHERE jenis_pesan_id = mt.id) as message_count
                    FROM message_types mt 
                    ORDER BY mt.is_active DESC, mt.jenis_pesan ASC";
            $messageTypes = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'data' => $messageTypes
            ]);
            
        } elseif ($action === 'templates') {
            // Get response templates
            $sql = "SELECT * FROM response_templates ORDER BY use_count DESC, name ASC";
            $templates = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'data' => $templates
            ]);
            
        } elseif ($action === 'users') {
            // Get users with stats
            $sql = "SELECT u.*, 
                    COUNT(DISTINCT m.id) as total_messages,
                    COUNT(DISTINCT mr.id) as total_responses,
                    MAX(m.created_at) as last_activity
                    FROM users u
                    LEFT JOIN messages m ON u.id = m.pengirim_id
                    LEFT JOIN message_responses mr ON u.id = mr.responder_id
                    GROUP BY u.id
                    ORDER BY u.is_active DESC, u.created_at DESC";
            $users = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            
        } elseif ($action === 'notifications') {
            // Get MailerSend config
            $mailerSql = "SELECT * FROM mailersend_config LIMIT 1";
            $mailerConfig = $db->select($mailerSql);
            
            // Get Fonnte config
            $fonnteSql = "SELECT * FROM fonnte_config LIMIT 1";
            $fonnteConfig = $db->select($fonnteSql);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'mailersend' => !empty($mailerConfig) ? $mailerConfig[0] : null,
                    'fonnte' => !empty($fonnteConfig) ? $fonnteConfig[0] : null
                ]
            ]);
            
        } elseif ($action === 'audit') {
            // Get audit logs
            $sql = "SELECT a.*, u.nama_lengkap as user_name 
                    FROM audit_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.created_at DESC 
                    LIMIT 50";
            $logs = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
            
        } elseif ($action === 'backup_list') {
            // Get backup files
            $backupDir = ROOT_PATH . '/backups';
            $files = [];
            if (is_dir($backupDir)) {
                $allFiles = glob($backupDir . '/*.{sql,zip,gz}', GLOB_BRACE);
                foreach ($allFiles as $file) {
                    $files[] = [
                        'name' => basename($file),
                        'size' => filesize($file),
                        'size_formatted' => formatFileSize(filesize($file)),
                        'date' => filemtime($file),
                        'date_formatted' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
                usort($files, function($a, $b) {
                    return $b['date'] - $a['date'];
                });
            }
            
            // Get system stats
            $statsSql = "SELECT 
                            (SELECT COUNT(*) FROM users) as total_users,
                            (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
                            (SELECT COUNT(*) FROM messages) as total_messages,
                            (SELECT COUNT(*) FROM message_responses) as total_responses,
                            (SELECT COUNT(*) FROM external_senders) as total_external,
                            (SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
                            (SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
                             FROM information_schema.tables 
                             WHERE table_schema = DATABASE()) as db_size_mb";
            $stats = $db->select($statsSql);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'backup_files' => $files,
                    'system_stats' => !empty($stats) ? $stats[0] : []
                ]
            ]);
            
        } elseif ($action === 'system_info') {
            // Get system information
            $statsSql = "SELECT 
                            (SELECT COUNT(*) FROM users) as total_users,
                            (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
                            (SELECT COUNT(*) FROM messages) as total_messages,
                            (SELECT COUNT(*) FROM message_responses) as total_responses,
                            (SELECT COUNT(*) FROM external_senders) as total_external,
                            (SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
                            (SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
                             FROM information_schema.tables 
                             WHERE table_schema = DATABASE()) as db_size_mb";
            $stats = $db->select($statsSql);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'php_version' => phpversion(),
                    'mysql_version' => $db->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
                    'db_size_mb' => !empty($stats) ? $stats[0]['db_size_mb'] : 0,
                    'db_host' => DB_HOST,
                    'db_port' => DB_PORT,
                    'db_name' => DB_NAME,
                    'total_users' => !empty($stats) ? $stats[0]['total_users'] : 0,
                    'active_users' => !empty($stats) ? $stats[0]['active_users'] : 0,
                    'total_messages' => !empty($stats) ? $stats[0]['total_messages'] : 0,
                    'total_responses' => !empty($stats) ? $stats[0]['total_responses'] : 0,
                    'total_external' => !empty($stats) ? $stats[0]['total_external'] : 0,
                    'logs_24h' => !empty($stats) ? $stats[0]['logs_24h'] : 0
                ]
            ]);
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_GET['action'] ?? '';
        
        if ($action === 'update_general') {
            $settings = $input['settings'] ?? [];
            $db->beginTransaction();
            try {
                foreach ($settings as $key => $value) {
                    $db->execute("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ? AND is_editable = 1", [$value, $key]);
                }
                $db->commit();
                
                // Log audit
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'UPDATE', 'system_settings', ?, ?, ?, NOW())", 
                             [$userData['user_id'], 'General settings updated', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                
                echo json_encode(['success' => true, 'message' => 'Pengaturan umum berhasil diperbarui']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            
        } elseif ($action === 'add_message_type') {
            $jenis_pesan = $input['jenis_pesan'] ?? '';
            $deskripsi = $input['deskripsi'] ?? '';
            $response_deadline_hours = (int)($input['response_deadline_hours'] ?? 72);
            $allow_external = isset($input['allow_external']) ? 1 : 0;
            $is_active = isset($input['is_active']) ? 1 : 0;
            $responder_type = $input['responder_type'] ?? 'Guru_BK';
            $color_code = $input['color_code'] ?? '#0d6efd';
            $icon_class = $input['icon_class'] ?? 'fas fa-envelope';
            
            if (empty($jenis_pesan)) {
                echo json_encode(['success' => false, 'message' => 'Nama jenis pesan harus diisi']);
                exit;
            }
            
            $sql = "INSERT INTO message_types (jenis_pesan, deskripsi, response_deadline_hours, allow_external, is_active, responder_type, color_code, icon_class, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $result = $db->execute($sql, [$jenis_pesan, $deskripsi, $response_deadline_hours, $allow_external, $is_active, $responder_type, $color_code, $icon_class]);
            
            if ($result) {
                $id = $db->lastInsertId();
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'CREATE', 'message_types', ?, ?, ?, ?, NOW())", 
                             [$userData['user_id'], $id, "Created message type: $jenis_pesan", $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil ditambahkan', 'id' => $id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan jenis pesan']);
            }
            
        } elseif ($action === 'edit_message_type') {
            $id = (int)($input['id'] ?? 0);
            $jenis_pesan = $input['jenis_pesan'] ?? '';
            $deskripsi = $input['deskripsi'] ?? '';
            $response_deadline_hours = (int)($input['response_deadline_hours'] ?? 72);
            $allow_external = isset($input['allow_external']) ? 1 : 0;
            $is_active = isset($input['is_active']) ? 1 : 0;
            $responder_type = $input['responder_type'] ?? 'Guru_BK';
            $color_code = $input['color_code'] ?? '#0d6efd';
            $icon_class = $input['icon_class'] ?? 'fas fa-envelope';
            
            if ($id <= 0 || empty($jenis_pesan)) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }
            
            $sql = "UPDATE message_types SET 
                    jenis_pesan = ?, deskripsi = ?, response_deadline_hours = ?, 
                    allow_external = ?, is_active = ?, responder_type = ?,
                    color_code = ?, icon_class = ?, updated_at = NOW() 
                    WHERE id = ?";
            $result = $db->execute($sql, [$jenis_pesan, $deskripsi, $response_deadline_hours, $allow_external, $is_active, $responder_type, $color_code, $icon_class, $id]);
            
            if ($result) {
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'UPDATE', 'message_types', ?, ?, ?, ?, NOW())", 
                             [$userData['user_id'], $id, "Updated message type: $jenis_pesan", $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil diperbarui']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jenis pesan']);
            }
            
        } elseif ($action === 'delete_message_type') {
            $id = (int)($input['id'] ?? 0);
            
            $check = $db->select("SELECT COUNT(*) as total FROM messages WHERE jenis_pesan_id = ?", [$id]);
            $count = !empty($check) ? (int)$check[0]['total'] : 0;
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => "Tidak dapat menghapus: jenis pesan ini memiliki $count pesan terkait"]);
                exit;
            }
            
            $result = $db->execute("DELETE FROM message_types WHERE id = ?", [$id]);
            
            if ($result) {
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'DELETE', 'message_types', ?, ?, ?, ?, NOW())", 
                             [$userData['user_id'], $id, "Deleted message type ID: $id", $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Jenis pesan berhasil dihapus']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus jenis pesan']);
            }
            
        } elseif ($action === 'update_mailersend') {
            $config = $input;
            $existing = $db->select("SELECT id FROM mailersend_config LIMIT 1");
            
            if (!empty($existing)) {
                $sql = "UPDATE mailersend_config SET 
                        api_token = ?, domain = ?, domain_id = ?, from_email = ?, from_name = ?,
                        smtp_server = ?, smtp_username = ?, smtp_password = ?, smtp_port = ?,
                        smtp_encryption = ?, test_domain = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?";
                $result = $db->execute($sql, [
                    $config['api_token'], $config['domain'], $config['domain_id'],
                    $config['from_email'], $config['from_name'], $config['smtp_server'],
                    $config['smtp_username'], $config['smtp_password'], (int)$config['smtp_port'],
                    $config['smtp_encryption'], $config['test_domain'], (int)$config['is_active'],
                    $existing[0]['id']
                ]);
            } else {
                $sql = "INSERT INTO mailersend_config 
                        (api_token, domain, domain_id, from_email, from_name, smtp_server, smtp_username, smtp_password, smtp_port, smtp_encryption, test_domain, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $result = $db->execute($sql, [
                    $config['api_token'], $config['domain'], $config['domain_id'],
                    $config['from_email'], $config['from_name'], $config['smtp_server'],
                    $config['smtp_username'], $config['smtp_password'], (int)$config['smtp_port'],
                    $config['smtp_encryption'], $config['test_domain'], (int)$config['is_active']
                ]);
            }
            
            if ($result) {
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'UPDATE', 'mailersend_config', ?, ?, ?, NOW())", 
                             [$userData['user_id'], 'MailerSend configuration updated', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Konfigurasi MailerSend berhasil diperbarui']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan konfigurasi MailerSend']);
            }
            
        } elseif ($action === 'update_fonnte') {
            $config = $input;
            $existing = $db->select("SELECT id FROM fonnte_config LIMIT 1");
            
            if (!empty($existing)) {
                $sql = "UPDATE fonnte_config SET 
                        api_token = ?, account_token = ?, device_id = ?, api_url = ?,
                        email = ?, password = ?, country_code = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?";
                $result = $db->execute($sql, [
                    $config['api_token'], $config['account_token'], $config['device_id'],
                    $config['api_url'], $config['email'], $config['password'],
                    $config['country_code'], (int)$config['is_active'], $existing[0]['id']
                ]);
            } else {
                $sql = "INSERT INTO fonnte_config 
                        (api_token, account_token, device_id, api_url, email, password, country_code, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $result = $db->execute($sql, [
                    $config['api_token'], $config['account_token'], $config['device_id'],
                    $config['api_url'], $config['email'], $config['password'],
                    $config['country_code'], (int)$config['is_active']
                ]);
            }
            
            if ($result) {
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'UPDATE', 'fonnte_config', ?, ?, ?, NOW())", 
                             [$userData['user_id'], 'Fonnte configuration updated', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Konfigurasi Fonnte berhasil diperbarui']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan konfigurasi Fonnte']);
            }
            
        } elseif ($action === 'clear_logs') {
            $days = (int)($input['days'] ?? 30);
            $result = $db->execute("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
            $deleted = $db->rowCount();
            
            $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, new_value, ip_address, user_agent, created_at) 
                         VALUES (?, 'CLEANUP', 'audit_logs', ?, ?, ?, NOW())", 
                         [$userData['user_id'], "Cleared $deleted log entries older than $days days", $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            
            echo json_encode(['success' => true, 'message' => "Berhasil membersihkan $deleted entri log", 'deleted' => $deleted]);
            
        } elseif ($action === 'update_user_status') {
            $userId = (int)($input['user_id'] ?? 0);
            $isActive = isset($input['is_active']) ? 1 : 0;
            
            $result = $db->execute("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?", [$isActive, $userId]);
            
            if ($result) {
                $db->execute("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, new_value, ip_address, user_agent, created_at) 
                             VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, NOW())", 
                             [$userData['user_id'], $userId, "User status updated to: " . ($isActive ? 'Active' : 'Inactive'), $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
                echo json_encode(['success' => true, 'message' => 'Status pengguna berhasil diperbarui']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pengguna']);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Settings API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>