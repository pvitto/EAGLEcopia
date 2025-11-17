<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function send_email($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // DEVELOPER: Configure your Gmail credentials here.
        // For security, it is recommended to use environment variables instead of hardcoding credentials.
        $mail->Username   = 'YOUR_GMAIL_ADDRESS'; // TODO: Replace with your Gmail address
        $mail->Password   = 'YOUR_GMAIL_PASSWORD'; // TODO: Replace with your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom('YOUR_GMAIL_ADDRESS', 'Eaglecopia');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>