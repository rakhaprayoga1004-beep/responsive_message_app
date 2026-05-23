<?php
// api/test.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfigurasi database - port 3307
$host = 'localhost';
$port = 3307;
$dbname = 'responsive_message_db';
$username = 'root';
$password = '';

try {
    // Test koneksi database
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek apakah tabel users ada
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Hitung jumlah user
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Koneksi ke database berhasil',
            'database' => $dbname,
            'port' => $port,
            'tables' => [
                'users' => 'ada',
                'jumlah_user' => intval($userCount)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'warning' => 'Tabel users tidak ditemukan',
            'database' => $dbname,
            'port' => $port
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal',
        'error' => $e->getMessage(),
        'database' => $dbname,
        'port' => $port
    ]);
}
?>