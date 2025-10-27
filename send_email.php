<?php
/**
 * Este archivo contiene la función para enviar correos electrónicos usando PHPMailer.
 * Es crucial que el autoload de Composer se cargue correctamente.
 */

// Se hace la ruta al autoload de Composer explícita y robusta, partiendo del directorio de este archivo.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Envía un correo electrónico utilizando PHPMailer con una cuenta de Gmail.
 *
 * @param string $toEmail La dirección de correo del destinatario.
 * @param string $toName El nombre del destinatario.
 * @param string $subject El asunto del correo.
 * @param string $body El cuerpo del correo en formato HTML.
 * @return bool Devuelve true si el correo se envió con éxito, false en caso contrario.
 */
function send_email_notification($toEmail, $toName, $subject, $body) {
    // Se usan los nombres de clase completamente calificados para evitar cualquier ambigüedad de namespace.
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // --- Configuración del Servidor SMTP ---
        $mail->SMTPDebug = 0; // 0 = off (producción)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // --- CREDENCIALES (Reemplazar con las tuyas) ---
        $mail->Username = 'TU_CORREO@gmail.com'; // Tu dirección de correo de Gmail
        $mail->Password = 'TU_CONTRASENA_DE_APLICACION'; // Tu contraseña de aplicación de Gmail

        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // --- Remitente y Destinatario ---
        $mail->setFrom('TU_CORREO@gmail.com', 'Sistema de Alertas EAGLE 3.0');
        $mail->addAddress($toEmail, $toName);

        // --- Contenido del Correo ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();

        return true;

    } catch (Exception $e) {
        // Registrar el error detallado en el log del servidor, nunca mostrarlo al usuario.
        error_log("PHPMailer Error: No se pudo enviar el correo. " . $mail->ErrorInfo);
        return false;
    }
}
