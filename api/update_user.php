<?php
/**
 * API untuk mengupdate user
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID tidak valid');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input');
    }
    
    $db = Database::getInstance();
    
    $nama_lengkap = $input['nama_lengkap'] ?? '';
    $email = $input['email'] ?? '';
    $user_type = $input['user_type'] ?? '';
    $nis_nip = $input['nis_nip'] ?? null;
    $no_telp = $input['no_telp'] ?? null;
    $status = $input['status'] ?? 'aktif';
    
    if (empty($nama_lengkap) || empty($email) || empty($user_type)) {
        throw new Exception('Data tidak lengkap');
    }
    
    // Update user
    $sql = "UPDATE users SET 
                nama_lengkap = ?, 
                email = ?, 
                user_type = ?,
                nis_nip = ?,
                phone_number = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?";
    
    $is_active = ($status == 'aktif') ? 1 : 0;
    
    $result = $db->execute($sql, [
        $nama_lengkap, $email, $user_type,
        $nis_nip, $no_telp, $is_active, $id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User berhasil diupdate'
        ]);
    } else {
        throw new Exception('Gagal mengupdate user');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>