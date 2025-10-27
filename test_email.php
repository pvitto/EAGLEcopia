<?php
require __DIR__ . '/send_email.php';

$ok = send_email_notification(
  'jeniffer.yance@gmail.com',               // Cambia por un correo que controles
  'Usuario Prueba',
  'Prueba EAGLE PHPMailer',
  '<b>¡Hola!</b> Este es un correo de prueba desde EAGLE.'
);

echo $ok ? "✅ Enviado\n" : "❌ Falló\n";
