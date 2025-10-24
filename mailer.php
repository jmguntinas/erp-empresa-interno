<?php
// mailer.php (usando PHPMailer)

// Cargar Autoloader de Composer (si usas Composer)
require_once __DIR__ . '/vendor/autoload.php'; 
// O incluye manualmente si no usas Composer:
// require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/config.php'; // Para cargar credenciales SMTP

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un email usando PHPMailer con configuración SMTP.
 * * @param string $to      La dirección del destinatario.
 * @param string $subject El asunto del email.
 * @param string $body    El cuerpo del email (puede ser HTML si ajustas IsHTML(true)).
 * @return bool           True si el email se envió correctamente, False si hubo un error.
 */
function app_send_mail(string $to, string $subject, string $body): bool
{
    // --- Configuración SMTP (¡Guarda esto de forma segura!) ---
    // Define estas constantes en config.php o usa variables de entorno
    $smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.example.com'; // Ej: smtp.gmail.com, smtp.office365.com
    $smtp_port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;          // Puerto: 587 (TLS) o 465 (SSL)
    $smtp_user = defined('SMTP_USER') ? SMTP_USER : 'tu_email@example.com';
    $smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : 'tu_contraseña_o_app_password';
    $smtp_secure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS; // o PHPMailer::ENCRYPTION_SMTPS

    // Remitente (puede ser el mismo que el usuario SMTP)
    $from_email = defined('APP_EMAIL_FROM') ? APP_EMAIL_FROM : $smtp_user;
    $from_name = defined('APP_EMAIL_FROM_NAME') ? APP_EMAIL_FROM_NAME : 'Gestor Inventario';

    $mail = new PHPMailer(true); // Habilitar excepciones

    try {
        // Configuración del servidor SMTP
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomenta para depuración detallada
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port       = $smtp_port;
        $mail->CharSet = PHPMailer::CHARSET_UTF8; // Asegurar UTF-8

        // Remitente y Destinatario(s)
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to); // El destinatario
        // $mail->addReplyTo($from_email, $from_name); // Opcional: Responder a

        // Contenido
        $mail->isHTML(false); // Enviar como texto plano (mejor para recuperación de contraseña)
        // Si quieres HTML: $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // $mail->AltBody = 'Este es el cuerpo en texto plano para clientes no-HTML'; // Opcional si usas HTML

        $mail->send();
        return true; // Éxito

    } catch (Exception $e) {
        error_log("Error al enviar email a $to con PHPMailer: {$mail->ErrorInfo}");
        return false; // Error
    }
}

?>