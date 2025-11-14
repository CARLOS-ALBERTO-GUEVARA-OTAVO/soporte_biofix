<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Asegúrate de que la ruta a autoload.php sea correcta según tu estructura
require 'vendor/autoload.php'; 

function enviar_correo_ticket($destinatario, $asunto, $cuerpo) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP (ajusta con tus credenciales)
        $mail->SMTPDebug = 0; // Desactiva el debug para producción. Usa 2 para ver errores.
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Servidor SMTP de Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aprendiz.gct@biofix.com.co'; // Tu usuario SMTP de Gmail
        $mail->Password   = 'ykpjybfjoowokxmj'; // Tu contraseña de aplicación de Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usar SMTPS (SSL)
        $mail->Port       = 465; // Puerto para SMTPS (SSL)

        // Remitente y destinatario
        // El remitente DEBE ser el mismo que el Username para evitar errores con Gmail
        $mail->setFrom('aprendiz.gct@biofix.com.co', 'Sistema de Soporte Biofix');
        
        // 1. Destinatario principal (el usuario que crea el ticket o recibe la respuesta)
        $mail->addAddress($destinatario);
        // 2. Copia para el área de TICS
        $mail->addAddress('aprendiz.gct@biofix.com.co', 'Soporte TICS');
        // 3. Copia OCULTA para la Gerencia General
        $mail->addBCC('Carlosgo1822@gmail.com', 'Gerencia General');

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Puedes registrar el error en un log en lugar de mostrarlo
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>