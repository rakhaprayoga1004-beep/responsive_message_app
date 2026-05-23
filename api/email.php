<?php
/**
 * Email API Integration
 * File: api/email.php
 * 
 * FITUR: Mengirim notifikasi email untuk respons pesan dari guru
 * Mendukung pengirim internal (user terdaftar) dan eksternal
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API Authentication
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if (!$apiKey || !validateApiKey($apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send_email':
        sendEmail();
        break;
    
    case 'send_response':
        sendEmailResponse(); // Khusus untuk respons guru
        break;
    
    case 'status':
        getEmailStatus();
        break;
    
    case 'test':
        testEmailConnection();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * FUNGSI UTAMA: Kirim email response dari guru ke pengirim
 */
function sendEmailResponse() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $errors = [];
    if (empty($data['to'])) {
        $errors[] = 'Recipient email is required';
    }
    if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($data['message_id'])) {
        $errors[] = 'Message ID is required';
    }
    if (empty($data['response_id'])) {
        $errors[] = 'Response ID is required';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get complete message and response data
        $stmt = $db->prepare("
            SELECT 
                m.*,
                mr.catatan_respon,
                mr.status as response_status,
                mr.created_at as response_date,
                u.id as guru_id,
                u.nama_lengkap as guru_nama,
                u.email as guru_email,
                u.user_type as guru_type,
                CASE 
                    WHEN m.is_external = 1 THEN es.nama_lengkap
                    ELSE peng.nama_lengkap
                END as pengirim_nama,
                CASE 
                    WHEN m.is_external = 1 THEN es.email
                    ELSE peng.email
                END as pengirim_email,
                mt.jenis_pesan as message_type
            FROM messages m
            INNER JOIN message_responses mr ON m.id = mr.message_id
            LEFT JOIN users u ON mr.responder_id = u.id
            LEFT JOIN message_types mt ON m.jenis_pesan_id = mt.id
            LEFT JOIN external_senders es ON m.external_sender_id = es.id AND m.is_external = 1
            LEFT JOIN users peng ON m.pengirim_id = peng.id AND m.is_external = 0
            WHERE m.id = :message_id AND mr.id = :response_id
        ");
        $stmt->execute([
            ':message_id' => $data['message_id'],
            ':response_id' => $data['response_id']
        ]);
        $messageData = $stmt->fetch();
        
        if (!$messageData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Message or response not found']);
            return;
        }
        
        // Build email subject
        $subject = "Respons Pesan #{$messageData['reference_number']} - SMKN 12 Jakarta";
        
        // Build HTML email content
        $htmlContent = buildEmailTemplate($messageData, $data['message'] ?? $messageData['catatan_respon']);
        
        // Build plain text alternative
        $textContent = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlContent));
        
        // Send email
        $result = sendEmailViaSMTP($data['to'], $subject, $htmlContent, $textContent, [
            'replyTo' => $messageData['guru_email'],
            'replyToName' => $messageData['guru_nama']
        ]);
        
        if ($result['success']) {
            // Log email notification
            $logStmt = $db->prepare("
                INSERT INTO email_logs 
                (message_id, response_id, recipient, subject, status, sent_at) 
                VALUES (:message_id, :response_id, :recipient, :subject, 'sent', NOW())
            ");
            $logStmt->execute([
                ':message_id' => $data['message_id'],
                ':response_id' => $data['response_id'],
                ':recipient' => $data['to'],
                ':subject' => $subject
            ]);
            
            // Update message notification status
            $updateStmt = $db->prepare("
                UPDATE messages 
                SET email_notified = 1, 
                    email_notified_at = NOW() 
                WHERE id = :message_id
            ");
            $updateStmt->execute([':message_id' => $data['message_id']]);
            
            // Update response notification status
            $updateResponseStmt = $db->prepare("
                UPDATE message_responses 
                SET email_sent = 1, 
                    email_sent_at = NOW() 
                WHERE id = :response_id
            ");
            $updateResponseStmt->execute([':response_id' => $data['response_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Email response sent successfully',
                'log_id' => $db->lastInsertId(),
                'to' => $data['to'],
                'subject' => $subject
            ]);
        } else {
            throw new Exception($result['error'] ?? 'Failed to send email');
        }
        
    } catch (Exception $e) {
        error_log("Email Send Response Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()]);
    }
}

/**
 * Build HTML email template
 */
function buildEmailTemplate($data, $responseMessage) {
    $statusColor = match($data['response_status']) {
        'Disetujui' => '#28a745',
        'Ditolak' => '#dc3545',
        'Diproses' => '#ffc107',
        'Selesai' => '#17a2b8',
        default => '#6c757d'
    };
    
    $statusIcon = match($data['response_status']) {
        'Disetujui' => '✓',
        'Ditolak' => '✗',
        'Diproses' => '⟳',
        'Selesai' => '✓✓',
        default => '•'
    };
    
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f6f9;
                line-height: 1.6;
                color: #2c3e50;
            }
            .container {
                max-width: 650px;
                margin: 30px auto;
                background: white;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(145deg, #0b4d8a, #1a73e8);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                font-size: 32px;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .header p {
                font-size: 16px;
                opacity: 0.95;
            }
            .content {
                padding: 40px 30px;
            }
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
            }
            .greeting strong {
                color: #0b4d8a;
            }
            .status-badge {
                display: inline-block;
                padding: 10px 25px;
                background: {$statusColor};
                color: white;
                border-radius: 50px;
                font-weight: 600;
                font-size: 16px;
                margin: 20px 0;
            }
            .info-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 25px;
                margin: 25px 0;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .info-item {
                margin-bottom: 10px;
            }
            .info-label {
                font-size: 12px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .info-value {
                font-size: 16px;
                font-weight: 600;
                color: #0b4d8a;
            }
            .response-card {
                background: white;
                border-left: 4px solid #0b4d8a;
                padding: 25px;
                margin: 25px 0;
                border-radius: 0 12px 12px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .response-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
            }
            .responder-avatar {
                width: 50px;
                height: 50px;
                background: #e8f0fe;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #1a73e8;
                font-size: 20px;
            }
            .responder-info h4 {
                margin: 0;
                color: #0b4d8a;
            }
            .responder-info p {
                margin: 5px 0 0;
                color: #64748b;
                font-size: 13px;
            }
            .response-message {
                background: #f8fafc;
                padding: 20px;
                border-radius: 8px;
                margin-top: 15px;
                white-space: pre-line;
            }
            .footer {
                background: #f1f5f9;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
            }
            .footer p {
                color: #64748b;
                font-size: 13px;
                margin-bottom: 10px;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(145deg, #1a73e8, #0d47a1);
                color: white;
                text-decoration: none;
                border-radius: 50px;
                font-weight: 600;
                margin-top: 20px;
                transition: transform 0.3s, box-shadow 0.3s;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(26,115,232,0.4);
            }
            .reference {
                font-family: 'Courier New', monospace;
                font-size: 18px;
                font-weight: 700;
                color: #0b4d8a;
                background: #e8f0fe;
                padding: 8px 16px;
                border-radius: 8px;
                display: inline-block;
            }
            @media (max-width: 600px) {
                .container { margin: 15px; }
                .content { padding: 25px 20px; }
                .info-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🏫 SMKN 12 Jakarta</h1>
                <p>Responsive Message App - Respons Pesan</p>
            </div>
            
            <div class="content">
                <div class="greeting">
                    <h3>Yth. <strong>{$data['pengirim_nama']}</strong>,</h3>
                </div>
                
                <p style="font-size: 16px; margin-bottom: 20px;">
                    Pesan Anda dengan nomor referensi telah mendapatkan respons dari tim kami.
                </p>
                
                <div style="text-align: center;">
                    <span class="reference">📋 {$data['reference_number']}</span>
                </div>
                
                <div style="text-align: center;">
                    <span class="status-badge">
                        {$statusIcon} Status: {$data['response_status']}
                    </span>
                </div>
                
                <div class="info-box">
                    <h4 style="color: #0b4d8a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📊</span> Detail Pesan
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">ID Pesan</div>
                            <div class="info-value">#{$data['id']}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Jenis Pesan</div>
                            <div class="info-value">{$data['message_type']}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tanggal Kirim</div>
                            <div class="info-value">" . date('d/m/Y H:i', strtotime($data['created_at'])) . "</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tanggal Respons</div>
                            <div class="info-value">" . date('d/m/Y H:i', strtotime($data['response_date'])) . "</div>
                        </div>
                    </div>
                </div>
                
                <div class="response-card">
                    <div class="response-header">
                        <div class="responder-avatar">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="responder-info">
                            <h4>{$data['guru_nama']}</h4>
                            <p>{$data['guru_type']} • SMKN 12 Jakarta</p>
                        </div>
                    </div>
                    <div style="margin-left: 65px;">
                        <p style="color: #64748b; margin-bottom: 10px; font-size: 14px;">
                            <i class="fas fa-clock"></i> " . date('d/m/Y H:i', strtotime($data['response_date'])) . "
                        </p>
                        <div class="response-message">
                            " . nl2br(htmlspecialchars($responseMessage)) . "
                        </div>
                    </div>
                </div>
                
                <div style="background: #e8f0fe; padding: 20px; border-radius: 12px; margin-top: 30px;">
                    <p style="margin: 0; color: #0b4d8a; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">💡</span>
                        <strong>Informasi Penting:</strong> Anda dapat membalas email ini untuk komunikasi lebih lanjut dengan {$data['guru_nama']}.
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <a href="{$_SERVER['HTTP_ORIGIN'] ?? BASE_URL}" class="btn">
                        <span style="margin-right: 8px;">🚀</span> Kirim Pesan Baru
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p style="font-size: 14px; font-weight: 600; color: #0b4d8a;">
                    SMKN 12 Jakarta - Responsive Message App
                </p>
                <p>
                    Jl. Pertanian Raya No. 12, Jakarta Timur<br>
                    Email: info@smkn12jakarta.sch.id | Telp: (021) 1234567
                </p>
                <p style="margin-top: 20px; font-style: italic;">
                    Email ini dikirim secara otomatis, mohon tidak membalas langsung ke email ini.<br>
                    Untuk membalas, gunakan fitur "Balas" di aplikasi email Anda.
                </p>
                <p style="margin-top: 15px; font-size: 11px; color: #94a3b8;">
                    © " . date('Y') . " SMKN 12 Jakarta. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>
    HTML;
    
    return $html;
}

/**
 * Send email via SMTP
 */
function sendEmailViaSMTP($to, $subject, $htmlContent, $textContent = null, $options = []) {
    if (!EMAIL_ENABLED) {
        return ['success' => false, 'error' => 'Email service is disabled'];
    }
    
    try {
        $headers = [];
        
        // From header
        $fromName = EMAIL_FROM_NAME ?? 'Responsive Message App';
        $fromEmail = EMAIL_FROM_EMAIL ?? 'noreply@smkn12jakarta.sch.id';
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        
        // Reply-To header
        if (!empty($options['replyTo'])) {
            $replyToName = $options['replyToName'] ?? $options['replyTo'];
            $headers[] = "Reply-To: {$replyToName} <{$options['replyTo']}>";
        }
        
        // Content-Type headers
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"boundary_" . md5(time()) . "\"";
        
        // Additional headers
        $headers[] = "X-Mailer: ResponsiveMessageApp/1.0";
        $headers[] = "X-Priority: 3 (Normal)";
        
        // Build message body
        $boundary = "boundary_" . md5(time() . rand());
        $body = "--{$boundary}\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\n";
        $body .= "Content-Transfer-Encoding: 8bit\n\n";
        $body .= ($textContent ?? strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlContent))) . "\n\n";
        $body .= "--{$boundary}\n";
        $body .= "Content-Type: text/html; charset=UTF-8\n";
        $body .= "Content-Transfer-Encoding: 8bit\n\n";
        $body .= $htmlContent . "\n\n";
        $body .= "--{$boundary}--";
        
        // Send email
        if (EMAIL_USE_SMTP) {
            // Use SMTP if configured
            $result = sendViaSMTP($to, $subject, $body, implode("\r\n", $headers));
        } else {
            // Use PHP mail() function
            $result = mail($to, $subject, $body, implode("\r\n", $headers));
        }
        
        if ($result) {
            error_log("Email sent successfully: To: {$to}, Subject: {$subject}");
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to send email via mail()'];
        }
        
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send via SMTP (placeholder - implement sesuai kebutuhan)
 */
function sendViaSMTP($to, $subject, $body, $headers) {
    // Implementasi SMTP jika diperlukan
    // Bisa menggunakan PHPMailer, SwiftMailer, dll
    return mail($to, $subject, $body, $headers);
}

/**
 * Validate API key
 */
function validateApiKey($apiKey) {
    $validKeys = explode(',', EMAIL_API_KEYS ?? WHATSAPP_API_KEYS);
    return in_array($apiKey, $validKeys);
}

/**
 * Get email service status
 */
function getEmailStatus() {
    echo json_encode([
        'success' => true,
        'enabled' => EMAIL_ENABLED ?? false,
        'use_smtp' => EMAIL_USE_SMTP ?? false,
        'from_email' => EMAIL_FROM_EMAIL ?? 'noreply@smkn12jakarta.sch.id',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Test email connection
 */
function testEmailConnection() {
    $testEmail = $_GET['test_email'] ?? EMAIL_FROM_EMAIL ?? 'noreply@smkn12jakarta.sch.id';
    
    $result = sendEmailViaSMTP(
        $testEmail,
        'Test Email dari Responsive Message App',
        '<h1>Test Connection</h1><p>Email berhasil terkirim pada ' . date('Y-m-d H:i:s') . '</p>',
        'Test email from Responsive Message App - ' . date('Y-m-d H:i:s')
    );
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
}