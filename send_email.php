<?php
// Incluir el autoload de Composer para cargar PHPMailer
require __DIR__ . '/vendor/autoload.php';

// Importar las clases de PHPMailer al espacio de nombres global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    // Crear una nueva instancia de PHPMailer
    $mail = new PHPMailer(true);

    try {
        // --- Configuración del Servidor SMTP de Gmail ---

        // Habilitar el modo de depuración SMTP (opcional, útil para solucionar problemas)
        // 0 = off (producción)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;

        // Usar SMTP
        $mail->isSMTP();

        // Servidor SMTP de Gmail
        $mail->Host = 'smtp.gmail.com';

        // Habilitar autenticación SMTP
        $mail->SMTPAuth = true;

        // --- TUS CREDENCIALES DE GMAIL (¡NO LAS PONGAS DIRECTAMENTE AQUÍ!) ---
        // Se recomienda usar variables de entorno o un archivo de configuración seguro.
        // Reemplaza los valores de abajo con tus credenciales.
        $mail->Username = 'TU_CORREO@gmail.com'; // Tu dirección de correo de Gmail
        $mail->Password = 'TU_CONTRASENA_DE_APLICACION'; // Tu contraseña de aplicación de Gmail

        // Habilitar cifrado TLS implícito
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Puerto TCP para conectar
        $mail->Port = 587;

        // --- Remitente y Destinatario ---

        // Quién envía el correo
        $mail->setFrom('TU_CORREO@gmail.com', 'Sistema de Alertas EAGLE 3.0');

        // A quién se envía el correo
        $mail->addAddress($toEmail, $toName);

        // --- Contenido del Correo ---

        // Establecer el formato del correo a HTML
        $mail->isHTML(true);

        // Asunto del correo
        $mail->Subject = $subject;

        // Cuerpo del correo
        $mail->Body = $body;

        // Cuerpo alternativo en texto plano para clientes de correo que no soportan HTML
        $mail->AltBody = strip_tags($body);

        // Establecer el juego de caracteres
        $mail->CharSet = 'UTF-8';

        // Enviar el correo
        $mail->send();

        // Si llega hasta aquí, el correo se envió con éxito
        return true;

    } catch (Exception $e) {
        // Si ocurre un error, registrarlo para depuración.
        // En un entorno de producción, nunca muestres el error directamente al usuario.
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>