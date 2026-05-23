require_once '../vendor/PHPMailer/src/Exception.php';
require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'agung.senen3@gmail.com';
    $mail->Password = 'YOUR_APP_PASSWORD'; // BUKAN password biasa!
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    
    $mail->setFrom('noreply@smkn12jakarta.sch.id', 'Responsive Message App');
    $mail->addAddress('agung.senen3@gmail.com');
    $mail->Subject = 'Test Email via PHPMailer';
    $mail->Body = '<h1>Test</h1><p>Email berhasil dikirim via PHPMailer!</p>';
    
    $mail->send();
    echo 'Email berhasil dikirim!';
} catch (Exception $e) {
    echo "Gagal: {$mail->ErrorInfo}";
}