<?php
// C:\xampp\htdocs\responsive-message-app\api\get_csrf_token.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token']
]);