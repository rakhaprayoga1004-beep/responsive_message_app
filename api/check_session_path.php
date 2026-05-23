<?php
/**
 * Check Session Path
 * File: responsive-message-app/api/check_session_path.php
 */

header('Content-Type: text/html');

echo "<h2>Session Path Information</h2>";

$sessionPath = session_save_path();
echo "<h3>Session Save Path:</h3>";
echo "<pre>" . $sessionPath . "</pre>";

// Jika session save path kosong, gunakan default
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
    echo "<p>Using system temp dir: " . $sessionPath . "</p>";
}

// Cek apakah folder ada
echo "<h3>Directory Check:</h3>";
if (is_dir($sessionPath)) {
    echo "<p style='color:green'>✓ Directory exists</p>";
    
    // Cek permission
    if (is_readable($sessionPath)) {
        echo "<p style='color:green'>✓ Directory is readable</p>";
    } else {
        echo "<p style='color:red'>✗ Directory is NOT readable</p>";
    }
    
    if (is_writable($sessionPath)) {
        echo "<p style='color:green'>✓ Directory is writable</p>";
    } else {
        echo "<p style='color:red'>✗ Directory is NOT writable</p>";
    }
} else {
    echo "<p style='color:red'>✗ Directory does NOT exist</p>";
}

// Cek file session untuk ID tertentu
echo "<h3>Session File Check:</h3>";
$sessionId = $_GET['sid'] ?? 'v9n6v2mp2ttqovhuj66eaqp7t0';
$sessionFile = $sessionPath . '/sess_' . $sessionId;

echo "Checking file: " . $sessionFile . "<br>";

if (file_exists($sessionFile)) {
    echo "<p style='color:green'>✓ Session file exists</p>";
    
    // Cek permission file
    if (is_readable($sessionFile)) {
        echo "<p style='color:green'>✓ File is readable</p>";
        
        // Baca isi file
        $content = file_get_contents($sessionFile);
        echo "<p>File size: " . strlen($content) . " bytes</p>";
        echo "<p>File content (hex): " . bin2hex($content) . "</p>";
    } else {
        echo "<p style='color:red'>✗ File is NOT readable (Permission denied)</p>";
    }
} else {
    echo "<p style='color:red'>✗ Session file does NOT exist</p>";
}

// Coba buat file test
echo "<h3>Write Test:</h3>";
$testFile = $sessionPath . '/test_write.txt';
if (file_put_contents($testFile, 'test')) {
    echo "<p style='color:green'>✓ Can write to directory</p>";
    unlink($testFile);
} else {
    echo "<p style='color:red'>✗ Cannot write to directory</p>";
}
?>