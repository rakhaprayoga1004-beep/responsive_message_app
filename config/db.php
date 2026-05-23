<?php
// File: config/db.php
// Database connection class - terpisah dari config.php

// Gunakan konfigurasi database dari config.php
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3307');
    define('DB_NAME', 'responsive_message_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Database connection class
if (!class_exists('Database')) {
    class Database {
        private static $instance = null;
        private $connection;
        
        private function __construct() {
            try {
                $this->connection = new PDO(
                    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch(PDOException $e) {
                die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        
        public static function getInstance() {
            if (self::$instance == null) {
                self::$instance = new Database();
            }
            return self::$instance;
        }
        
        public function getConnection() {
            return $this->connection;
        }
    }
}
?>