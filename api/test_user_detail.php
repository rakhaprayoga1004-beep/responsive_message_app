<?php
/**
 * Script test untuk verifikasi API user_detail.php
 * Akses: http://localhost:8090/responsive-message-app/api/test_user_detail.php?id=95
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Start session dengan nama yang benar
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME); // RMSESSID
    } else {
        session_name('PHPSESSID');
    }
    session_start();
}

// Set session untuk testing (gunakan user yang sudah ada di database)
if (!isset($_SESSION['user_id'])) {
    // Coba ambil user admin dari database
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, user_type FROM users WHERE user_type = 'Admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_type'] = $admin['user_type'];
            $_SESSION['privilege_level'] = 'Full_Access';
            
            echo "<p style='color:green'>✅ Session set for user: {$admin['username']} (ID: {$admin['id']})</p>";
        } else {
            echo "<p style='color:red'>❌ No admin user found in database!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "<h1>Test User Detail API</h1>";
echo "<h3>Session Info:</h3>";
echo "<pre>";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true);
echo "</pre>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $sql = "SELECT 
                id, username, user_type, nama_lengkap, 
                privilege_level, created_at
            FROM users 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>User Data from Database:</h2>";
    echo "<pre>";
    echo "User ID: {$user['id']}\n";
    echo "Username: {$user['username']}\n";
    echo "User Type: {$user['user_type']}\n";
    echo "Privilege Level: " . ($user['privilege_level'] ?? 'NULL') . "\n";
    echo "</pre>";
    
    echo "<h2>Testing actual API call with session cookie:</h2>";
    
    // Buat request dengan session cookie
    $apiUrl = "http://localhost:8090/responsive-message-app/api/user_detail.php?id=$id";
    
    $options = [
        'http' => [
            'header' => "Cookie: " . session_name() . "=" . session_id() . "\r\n",
            'method' => 'GET',
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($apiUrl, false, $context);
    $http_response_header = $http_response_header;
    
    echo "<pre>";
    echo "HTTP Headers:\n";
    print_r($http_response_header);
    echo "\nAPI Response:\n";
    echo $response;
    echo "</pre>";
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['data']['privilege_level'])) {
        echo "<h3 style='color: green'>✅ privilege_level found: {$data['data']['privilege_level']}</h3>";
        echo "<h3 style='color: green'>✅ privilege_label: {$data['data']['privilege_label']}</h3>";
    } elseif ($data && isset($data['error'])) {
        echo "<h3 style='color: red'>❌ API Error: " . $data['message'] . "</h3>";
    } else {
        echo "<h3 style='color: red'>❌ privilege_level MISSING in API response!</h3>";
        echo "<pre>Full response: " . print_r($data, true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red'>Error: " . $e->getMessage() . "</h3>";
}