<?php
/**
 * Cek status PHPMailer
 * File: api/phpmailer_check.php
 */

echo "<h2>🔍 Cek Status PHPMailer</h2>";

$phpmailer_paths = [
    '../vendor/PHPMailer/src/PHPMailer.php',
    '../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    '../PHPMailer/src/PHPMailer.php',
    '../src/PHPMailer.php'
];

$found = false;
foreach ($phpmailer_paths as $path) {
    if (file_exists($path)) {
        echo "✅ PHPMailer ditemukan di: <strong>$path</strong><br>";
        $found = true;
        break;
    }
}

if (!$found) {
    echo "❌ PHPMailer TIDAK ditemukan!<br>";
    echo "<h3>📥 Cara Install PHPMailer:</h3>";
    echo "<ol>";
    echo "<li>Download dari: <a href='https://github.com/PHPMailer/PHPMailer/archive/master.zip' target='_blank'>https://github.com/PHPMailer/PHPMailer/archive/master.zip</a></li>";
    echo "<li>Extract file zip</li>";
    echo "<li>Buat folder <strong>C:\\xampp\\htdocs\\responsive-message-app\\vendor\\</strong></li>";
    echo "<li>Copy folder <strong>src</strong> dari hasil extract ke folder vendor</li>";
    echo "<li>Rename menjadi <strong>PHPMailer</strong></li>";
    echo "</ol>";
    echo "Setelah selesai, refresh halaman ini.";
}
?>