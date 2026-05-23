<?php
// api/register.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validasi input
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $email = trim($input['email'] ?? '');
    $userType = $input['user_type'] ?? 'Siswa';
    $namaLengkap = trim($input['nama_lengkap'] ?? '');
    $nisNip = trim($input['nis_nip'] ?? '');
    $phoneNumber = trim($input['phone_number'] ?? '');
    $kelas = $input['kelas'] ?? null;
    $jurusan = $input['jurusan'] ?? null;
    
    // Validasi
    $errors = [];
    if (empty($username)) $errors[] = "Username harus diisi";
    if (empty($password)) $errors[] = "Password harus diisi";
    if ($password !== $confirmPassword) $errors[] = "Password tidak cocok";
    if (strlen($password) < 6) $errors[] = "Password minimal 6 karakter";
    if (empty($email)) $errors[] = "Email harus diisi";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email tidak valid";
    if (empty($namaLengkap)) $errors[] = "Nama lengkap harus diisi";
    if (empty($nisNip)) $errors[] = "NIS/NIP harus diisi";
    
    // Cek username sudah ada
    $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->fetch()) {
        $errors[] = "Username sudah terdaftar";
    }
    
    // Cek email sudah ada
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        $errors[] = "Email sudah terdaftar";
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    // Generate reference number untuk tracking
    $referenceNumber = 'REG' . date('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (
                username, password_hash, email, user_type, nama_lengkap, 
                nis_nip, phone_number, kelas, jurusan, privilege_level,
                is_active, created_at, updated_at
            ) VALUES (
                :username, :password, :email, :user_type, :nama_lengkap,
                :nis_nip, :phone, :kelas, :jurusan, 'Limited_Lv3',
                1, NOW(), NOW()
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':password' => $passwordHash,
        ':email' => $email,
        ':user_type' => $userType,
        ':nama_lengkap' => $namaLengkap,
        ':nis_nip' => $nisNip,
        ':phone' => $phoneNumber,
        ':kelas' => $kelas,
        ':jurusan' => $jurusan
    ]);
    
    $userId = $db->lastInsertId();
    
    // Simpan reference number ke session atau log untuk tracking
    $logStmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
        VALUES (?, 'register', ?, ?, NOW())
    ");
    $logStmt->execute([$userId, "User registered with reference: $referenceNumber", $_SERVER['REMOTE_ADDR']]);
    
    // Kirim notifikasi via MailerSend dan Fonnte
    $mailerSendConfig = getMailerSendConfig();
    $fonnteConfig = getFonnteConfig();
    
    // Kirim email jika MailerSend aktif
    $emailSent = false;
    if ($mailerSendConfig['is_active'] && !empty($email)) {
        $emailSent = sendRegistrationEmail($email, $namaLengkap, $referenceNumber, $mailerSendConfig);
    }
    
    // Kirim WhatsApp jika Fonnte aktif
    $whatsappSent = false;
    if ($fonnteConfig['is_active'] && !empty($phoneNumber)) {
        $whatsappSent = sendRegistrationWhatsApp($phoneNumber, $namaLengkap, $referenceNumber, $fonnteConfig);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Registrasi berhasil! Silakan login.',
        'reference_number' => $referenceNumber,
        'user_id' => $userId,
        'notifications' => [
            'email' => $emailSent,
            'whatsapp' => $whatsappSent
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function getMailerSendConfig() {
    $configFile = __DIR__ . '/../config/mailersend.json';
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true);
    }
    return [
        'api_token' => 'mlsn.a4e70a19ff00a659620ddf13fa13ea30662bb0199fa07f13ad391b43507025fa',
        'from_email' => 'noreply@test-r9084zv6rpjgw63d.mlsender.net',
        'from_name' => 'SMKN 12 Jakarta',
        'is_active' => 1
    ];
}

function getFonnteConfig() {
    $configFile = __DIR__ . '/../config/fonnte.json';
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true);
    }
    return [
        'api_token' => 'FS2cq8FckmaTegxtZpFB',
        'api_url' => 'https://api.fonnte.com/send',
        'is_active' => 1
    ];
}

function sendRegistrationEmail($email, $nama, $reference, $config) {
    $subject = "Konfirmasi Registrasi - SMKN 12 Jakarta";
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Konfirmasi Registrasi</title>
    </head>
    <body style="font-family: Arial, sans-serif;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #0b4d8a, #1a73e8); padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">SMKN 12 Jakarta</h1>
                <p style="color: white; margin: 5px 0 0;">Konfirmasi Registrasi</p>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px;">
                <p>Yth. <strong>' . htmlspecialchars($nama) . '</strong>,</p>
                <p>Selamat! Akun Anda telah berhasil terdaftar di Aplikasi Pesan Responsif SMKN 12 Jakarta.</p>
                
                <div style="background: #e8f0fe; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p style="margin: 0; color: #0b4d8a;"><strong>Nomor Referensi Registrasi:</strong></p>
                    <p style="font-size: 18px; font-weight: bold; color: #0b4d8a; margin: 5px 0 0;">' . htmlspecialchars($reference) . '</p>
                </div>
                
                <p><strong>Informasi Akun:</strong></p>
                <ul>
                    <li>Username: ' . htmlspecialchars($nama) . '</li>
                    <li>Email: ' . htmlspecialchars($email) . '</li>
                </ul>
                
                <p>Anda dapat login menggunakan username dan password yang telah Anda daftarkan.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . BASE_URL . 'login.php" style="background: #0b4d8a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">Login Sekarang</a>
                </div>
                
                <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">
                <p style="font-size: 12px; color: #6c757d; text-align: center;">&copy; ' . date('Y') . ' SMKN 12 Jakarta. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $data = [
        'from' => [
            'email' => $config['from_email'],
            'name' => $config['from_name']
        ],
        'to' => [['email' => $email, 'name' => $nama]],
        'subject' => $subject,
        'html' => $html
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailersend.com/v1/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_token'],
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

function sendRegistrationWhatsApp($phone, $nama, $reference, $config) {
    $formattedPhone = $phone;
    if (substr($formattedPhone, 0, 1) == '0') {
        $formattedPhone = '62' . substr($formattedPhone, 1);
    }
    
    $message = "*KONFIRMASI REGISTRASI - SMKN 12 Jakarta*\n\n";
    $message .= "Yth. *$nama*\n\n";
    $message .= "Selamat! Akun Anda telah berhasil terdaftar.\n\n";
    $message .= "*Nomor Referensi Registrasi:*\n";
    $message .= "$reference\n\n";
    $message .= "Anda dapat login menggunakan username dan password yang telah Anda daftarkan.\n\n";
    $message .= "Terima kasih telah mendaftar.\n\n";
    $message .= "_Pesan otomatis._";
    
    $postData = [
        'target' => $formattedPhone,
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Authorization: ' . $config['api_token']]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}
?>