<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'send_email.php';

echo "Intentando enviar correo de prueba...<br>";

// Reemplaza esto con un correo real al que tengas acceso para verificar si llega.
$test_recipient_email = 'paolovittoriomancini@gmail.com'; 
$test_recipient_name = 'Usuario de Prueba';

$subject = 'Prueba de Correo desde EAGLE 3.0';
$body = '<h1>¡Prueba Exitosa!</h1><p>Si recibes este correo, la configuración de PHPMailer y la conexión con Gmail están funcionando correctamente.</p>';

$sent = send_task_email($test_recipient_email, $test_recipient_name, $subject, $body);

if ($sent) {
    echo "<b><span style='color:green;'>¡Correo enviado con éxito!</span></b><br>Revisa la bandeja de entrada de " . htmlspecialchars($test_recipient_email);
} else {
    echo "<b><span style='color:red;'>Error al enviar el correo.</span></b><br>Revisa los logs de error de PHP en XAMPP para más detalles.";
}
?>