<?php
session_start();
echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookie_params' => session_get_cookie_params()
]);