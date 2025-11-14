<?php
session_start();
require 'bd/db.php';
require 'enviar_correo.php';

// 1. Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'No has iniciado sesión.']);
    exit;
}

// 2. Procesar la solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_soporte = $_POST['tipo_soporte'] ?? '';
    $descripcion_problema = $_POST['descripcion_problema'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_email = $_SESSION['usuario_email']; // Asegúrate de guardar el email en la sesión al hacer login
    $usuario_nombre = $_SESSION['usuario_nombre'];

    // Validación correcta de los campos del formulario
    if (empty($tipo_soporte) || empty($descripcion_problema)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'El tipo de soporte y la descripción son obligatorios.']);
        exit;
    }

    try {
        // 3. Generar número de radicado único y secuencial
        // Obtenemos el ID más alto y le sumamos 1.
        $stmt_last_id = $pdo->query("SELECT MAX(id_ticket) as last_id FROM tickets");
        $last_id = $stmt_last_id->fetchColumn();
        $next_id = ($last_id) ? $last_id + 1 : 1; // Si no hay tickets, empezamos en 1
        $numero_radicado = 'BIO-' . str_pad($next_id, 5, '0', STR_PAD_LEFT); // Formato: BIO-00001

        // 4. Insertar el ticket en la base de datos
        $stmt = $pdo->prepare(
            "INSERT INTO tickets (numero_radicado, id_usuario, tipo_soporte, descripcion_problema, estado) VALUES (?, ?, ?, ?, 'pendiente')"
        );
        $stmt->execute([$numero_radicado, $usuario_id, $tipo_soporte, $descripcion_problema]);

        // 5. Enviar correo de confirmación
        $asunto_correo = "Confirmación de Ticket de Soporte: " . $numero_radicado;
        $cuerpo_correo = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2>¡Tu solicitud ha sido recibida!</h2>
                <p>Hola <strong>" . htmlspecialchars($usuario_nombre) . "</strong>,</p>
                <p>Hemos recibido tu ticket de soporte y lo estamos revisando. Tu número de radicado es:</p>
                <p style='font-size: 1.8em; font-weight: bold; color: #0284c7; margin: 10px 0;'>" . $numero_radicado . "</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>

                <h4 style='color: #555;'>Este es un resumen de tu solicitud:</h4>
                <div style='background-color: #f9f9f9; border-left: 5px solid #ccc; padding: 15px; margin-top: 10px;'>
                    <p><strong>Tipo de Soporte:</strong> " . htmlspecialchars($tipo_soporte) . "</p>
                    <p><strong>Descripción del Problema:</strong><br>" . nl2br(htmlspecialchars($descripcion_problema)) . "</p>
                </div>

                <p style='margin-top: 20px;'>Te contactaremos pronto. Gracias por tu paciencia.</p>
                <br>
                <p><strong>Equipo de Soporte Biofix</strong></p>
            </div>
        ";
        
        enviar_correo_ticket($usuario_email, $asunto_correo, $cuerpo_correo);

        // Mensaje de éxito mejorado
        $success_message = "Solicitud registrada con éxito bajo el radicado " . $numero_radicado . ". " .
                           "Se ha enviado una notificación a su correo electrónico. " .
                           "Puede visualizar el estado y seguimiento de su ticket en este portal.";

        echo json_encode(['status' => 'success', 'message' => $success_message]);

    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        error_log("Error al crear ticket: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error al conectar con la base de datos.']);
    }
}
?>