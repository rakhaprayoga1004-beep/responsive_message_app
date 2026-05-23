<?php
/**
 * File: reset_all.php
 * Reset total semua session dan cookie
 */

session_start();

// Destroy semua
session_unset();
session_destroy();

// Hapus semua cookie
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-3600, '/');
        setcookie($name, '', time()-3600, '/', $_SERVER['HTTP_HOST']);
    }
}

// Redirect ke login
header('Location: login.php?reset=all');
exit;
?>