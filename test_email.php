<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Prueba de Envío de Correo</h1>";

// Cargar el autoloader de Composer
$vendor_autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    die("<p style='color: red;'><strong>ERROR:</strong> No se encontró el archivo 'vendor/autoload.php'. Por favor, ejecuta 'composer install' en la raíz de tu proyecto.</p>");
}
require_once $vendor_autoload;

// Importar clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CONFIGURACIÓN ---
// 1. Reemplaza con tus credenciales de Gmail
$gmail_user = 'TU_CORREO@gmail.com'; // Tu dirección de correo de Gmail
$gmail_password = 'TU_CONTRASEÑA_DE_APLICACION'; // Tu contraseña de aplicación de 16 letras

// 2. Reemplaza con la dirección donde quieres recibir el correo de prueba
$recipient_email = 'correo_destino@example.com';
$recipient_name = 'Nombre Destinatario';

// --- NO MODIFICAR DEBAJO DE ESTA LÍNEA ---

if ($gmail_user === 'TU_CORREO@gmail.com' || $gmail_password === 'TU_CONTRASEÑA_DE_APLICACION') {
    die("<p style='color: orange;'><strong>ADVERTENCIA:</strong> Por favor, abre el archivo 'test_email.php' y configura tus credenciales de Gmail en las variables \$gmail_user y \$gmail_password.</p>");
}

if ($recipient_email === 'correo_destino@example.com') {
    die("<p style='color: orange;'><strong>ADVERTENCIA:</strong> Por favor, abre el archivo 'test_email.php' y configura la dirección de correo del destinatario en la variable \$recipient_email.</p>");
}

$mail = new PHPMailer(true);

try {
    echo "<p>Intentando conectar con el servidor SMTP de Gmail...</p>";

    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmail_user;
    $mail->Password = $gmail_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    // Opcional: Habilitar debug para ver más detalles en caso de error
    // $mail->SMTPDebug = 2;

    // Remitente
    $mail->setFrom($gmail_user, 'Prueba Sistema EAGLE 3.0');
    echo "<p>Remitente configurado: " . htmlspecialchars($gmail_user) . "</p>";

    // Destinatario
    $mail->addAddress($recipient_email, $recipient_name);
    echo "<p>Destinatario configurado: " . htmlspecialchars($recipient_email) . "</p>";

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Correo de Prueba - EAGLE 3.0';
    $mail->Body    = 'Este es un correo de prueba para verificar que la configuración de PHPMailer y Gmail es correcta. <b>¡Si lo recibes, todo funciona!</b>';
    $mail->AltBody = 'Este es un correo de prueba para verificar que la configuración de PHPMailer y Gmail es correcta. ¡Si lo recibes, todo funciona!';

    echo "<p>Enviando correo...</p>";
    $mail->send();

    echo "<h2 style='color: green;'>¡ÉXITO!</h2>";
    echo "<p>El correo de prueba fue enviado correctamente a <strong>" . htmlspecialchars($recipient_email) . "</strong>. Por favor, revisa tu bandeja de entrada.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>ERROR AL ENVIAR EL CORREO</h2>";
    echo "<p><strong>Mensaje de error de PHPMailer:</strong> " . $mail->ErrorInfo . "</p>";
    echo "<hr>";
    echo "<h3>Posibles Soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verifica que la <strong>contraseña de aplicación</strong> de 16 letras sea correcta.</li>";
    echo "<li>Asegúrate de que la <strong>Verificación en dos pasos</strong> esté activada en tu cuenta de Google.</li>";
    echo "<li>Revisa que tu antivirus o firewall no estén bloqueando la conexión al puerto 587.</li>";
    echo "<li>Si el error menciona 'SMTP connect() failed', puede ser un problema temporal de red o un bloqueo por parte de tu proveedor de internet.</li>";
    echo "</ul>";
}
