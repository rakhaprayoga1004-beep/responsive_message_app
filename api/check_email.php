<?php
/**
 * API Cek Validitas Email
 * File: api/check_email.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function checkMXRecord($domain) {
    if (!checkdnsrr($domain, 'MX')) {
        return false;
    }
    
    $mxhosts = [];
    $mxweights = [];
    getmxrr($domain, $mxhosts, $mxweights);
    
    return !empty($mxhosts);
}

function isDisposableEmail($domain) {
    $disposableDomains = [
        'tempmail.com', 'throwaway.com', 'mailinator.com',
        'guerrillamail.com', 'sharklasers.com', 'yopmail.com'
    ];
    
    return in_array(strtolower($domain), $disposableDomains);
}

function checkEmail($email) {
    $result = [
        'valid' => false,
        'mxExists' => false,
        'isDisposable' => false,
        'domain' => '',
        'message' => ''
    ];

    // Validasi format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['message'] = 'Format email tidak valid';
        return $result;
    }

    $parts = explode('@', $email);
    $domain = $parts[1];
    $result['domain'] = $domain;

    // Cek disposable
    $result['isDisposable'] = isDisposableEmail($domain);
    if ($result['isDisposable']) {
        $result['message'] = 'Email sementara tidak diizinkan';
        return $result;
    }

    // Cek MX record
    $result['mxExists'] = checkMXRecord($domain);
    
    if (!$result['mxExists']) {
        $result['message'] = 'Domain tidak memiliki mail server';
    } else {
        $result['valid'] = true;
        $result['message'] = 'Email valid';
    }

    return $result;
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $domain = $input['domain'] ?? '';
    
    if (empty($domain)) {
        http_response_code(400);
        echo json_encode(['error' => 'Domain required']);
        exit;
    }

    $result = checkMXRecord($domain);
    echo json_encode(['mxExists' => $result]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);