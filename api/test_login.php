<?php
session_name('RMSESSID');
session_start();

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'has_user_id' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_type' => $_SESSION['user_type'] ?? null
]);
?>