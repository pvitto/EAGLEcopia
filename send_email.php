<?php
/**
 * Este archivo contiene la función para enviar correos electrónicos usando PHPMailer.
 */

// Cargar el autoloader de Composer de forma robusta.
require_once __DIR__ . '/vendor/autoload.php';

// Importar las clases de PHPMailer al espacio de nombres global.
// Esto soluciona el error "Undefined type" en el editor y asegura la correcta resolución de clases.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
    // Ahora podemos instanciar PHPMailer usando el nombre de clase corto.
    $mail = new PHPMailer(true);

    try {
        // --- Configuración del Servidor SMTP ---
        $mail->SMTPDebug = 0; // 0 = off (producción), 2 = debug
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // --- CREDENCIALES (Reemplazar con las tuyas en tu entorno local) ---
        // IMPORTANTE: No guardes credenciales reales en este archivo en el repositorio.
        $mail->Username = 'TU_CORREO@gmail.com'; // Tu dirección de correo de Gmail
        $mail->Password = 'TU_CONTRASENA_DE_APLICACION'; // Tu contraseña de aplicación de Gmail

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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

    } catch (PHPMailerException $e) {
        // Capturamos la excepción específica de PHPMailer para un manejo de errores más preciso.
        error_log("PHPMailer Error: No se pudo enviar el correo. " . $mail->ErrorInfo);
        return false;
    }
}
