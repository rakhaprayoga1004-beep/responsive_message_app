<?php
// C:\xampp\htdocs\responsive-message-app\api\messages\create.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Koneksi database langsung dengan port 3307
$host = 'localhost';
$port = '3307';
$dbname = 'responsive_message_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
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

if (!$userData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $userData['user_id'];
$userName = $userData['nama_lengkap'] ?? $userData['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $jenisPesanId = $input['jenis_pesan_id'] ?? 0;
    $isiPesan = trim($input['isi_pesan'] ?? '');
    $priority = $input['priority'] ?? 'Medium';
    
    $errors = [];
    
    if ($jenisPesanId <= 0) {
        $errors[] = 'Jenis pesan harus dipilih';
    }
    
    if (empty($isiPesan)) {
        $errors[] = 'Isi pesan harus diisi';
    } elseif (strlen($isiPesan) < 10) {
        $errors[] = 'Isi pesan minimal 10 karakter';
    } elseif (strlen($isiPesan) > 1000) {
        $errors[] = 'Isi pesan maksimal 1000 karakter';
    }
    
    if (!empty($errors)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    try {
        $sql = "
            INSERT INTO messages (
                jenis_pesan_id, pengirim_id, pengirim_nama, isi_pesan,
                status, priority, created_at, updated_at
            ) VALUES (
                :jenis_pesan_id, :pengirim_id, :pengirim_nama, :isi_pesan,
                'Pending', :priority, NOW(), NOW()
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':jenis_pesan_id' => $jenisPesanId,
            ':pengirim_id' => $userId,
            ':pengirim_nama' => $userName,
            ':isi_pesan' => htmlspecialchars($isiPesan),
            ':priority' => $priority
        ]);
        
        if ($result) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesan']);
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>