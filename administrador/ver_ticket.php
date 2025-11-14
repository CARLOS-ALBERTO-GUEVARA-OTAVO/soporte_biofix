<?php
session_start();
require '../bd/db.php';
require '../enviar_correo.php';

// 1. Seguridad: Verificar si es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'administrador') {
    header('Location: ../login/login.php');
    exit;
}

// 2. Validar el ID del ticket desde la URL
$id_ticket = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_ticket) {
    header('Location: dashboard.php');
    exit;
}

// 3. Manejo de formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción para actualizar estado y urgencia
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
        $nuevo_estado = $_POST['estado'];
        $nuevo_nivel_urgencia = $_POST['nivel_urgencia'];

        $stmt = $pdo->prepare("UPDATE tickets SET estado = ?, nivel_urgencia = ? WHERE id_ticket = ?");
        $stmt->execute([$nuevo_estado, $nuevo_nivel_urgencia, $id_ticket]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'El estado y la urgencia del ticket han sido actualizados.'];
    }

    // Acción para añadir una respuesta
    if (isset($_POST['action']) && $_POST['action'] == 'add_response') {
        $mensaje = trim($_POST['mensaje']);
        if (!empty($mensaje)) {
            // Insertar la respuesta en el historial
            $stmt = $pdo->prepare("INSERT INTO historial_respuestas (id_ticket, id_usuario, mensaje) VALUES (?, ?, ?)");
            $stmt->execute([$id_ticket, $_SESSION['usuario_id'], $mensaje]);

            // Cambiar el estado del ticket a "en proceso" si estaba "pendiente"
            $stmt_update = $pdo->prepare("UPDATE tickets SET estado = 'en proceso' WHERE id_ticket = ? AND estado = 'pendiente'");
            $stmt_update->execute([$id_ticket]);

            // Obtener datos del ticket y del usuario para el correo
            $stmt_info = $pdo->prepare(
                "SELECT t.numero_radicado, t.tipo_soporte, t.descripcion_problema, u.nombre, u.correo 
                 FROM tickets t JOIN usuarios u ON t.id_usuario = u.id_usuario WHERE t.id_ticket = ?"
            );
            $stmt_info->execute([$id_ticket]);
            $info_ticket = $stmt_info->fetch();

            // Preparar y enviar el correo de notificación
            $asunto_correo = "Respuesta a tu Ticket de Soporte: " . $info_ticket['numero_radicado'];
            $cuerpo_correo = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Hemos respondido a tu solicitud de soporte</h2>
                    <p>Hola <strong>" . htmlspecialchars($info_ticket['nombre']) . "</strong>,</p>
                    <p>Un administrador ha añadido una nueva respuesta a tu ticket con número de radicado: <strong>" . htmlspecialchars($info_ticket['numero_radicado']) . "</strong>.</p>
                    
                    <div style='background-color: #f9f9f9; border-left: 5px solid #0284c7; padding: 15px; margin: 20px 0;'>
                        <h4 style='margin-top: 0; color: #333;'>Nueva respuesta del equipo de soporte:</h4>
                        <p style='margin-bottom: 0;'><em>" . nl2br(htmlspecialchars($mensaje)) . "</em></p>
                    </div>

                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>

                    <h4 style='color: #555;'>Recordatorio de tu solicitud original:</h4>
                    <ul>
                        <li><strong>Tipo de Soporte:</strong> " . htmlspecialchars($info_ticket['tipo_soporte']) . "</li>
                        <li><strong>Tu descripción del problema:</strong> " . htmlspecialchars($info_ticket['descripcion_problema']) . "</li>
                    </ul>
                    <p>Puedes ver el historial completo de tu solicitud en la plataforma.</p>
                    <br>
                    <p><strong>Equipo de Soporte Biofix</strong></p>
                </div>
            ";
            
            enviar_correo_ticket($info_ticket['correo'], $asunto_correo, $cuerpo_correo);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Tu respuesta ha sido enviada y registrada.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'El mensaje de respuesta no puede estar vacío.'];
        }
    }
    // Redirigir para evitar reenvío de formulario
    header("Location: ver_ticket.php?id=" . $id_ticket);
    exit;
}

// 4. Obtener toda la información del ticket para mostrarla
$stmt = $pdo->prepare(
    "SELECT t.*, u.nombre as usuario_nombre, u.correo as usuario_correo, a.nombre_area as usuario_area
     FROM tickets t
     JOIN usuarios u ON t.id_usuario = u.id_usuario
     JOIN areas a ON u.id_area = a.id_area
     WHERE t.id_ticket = ?"
);
$stmt->execute([$id_ticket]);
$ticket = $stmt->fetch();

// Si el ticket no existe, redirigir
if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// 5. Obtener el historial de respuestas
$stmt_historial = $pdo->prepare(
    "SELECT h.*, u.nombre as autor_nombre, r.nombre_rol as autor_rol
     FROM historial_respuestas h
     JOIN usuarios u ON h.id_usuario = u.id_usuario
     JOIN roles r ON u.id_rol = r.id_rol
     WHERE h.id_ticket = ? ORDER BY h.fecha_respuesta ASC"
);
$stmt_historial->execute([$id_ticket]);
$historial = $stmt_historial->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viendo Ticket #<?php echo htmlspecialchars($ticket['numero_radicado']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 font-sans text-slate-800">
    <nav class="bg-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a class="text-white text-xl font-bold flex items-center" href="dashboard.php">
                <i class="fas fa-arrow-left mr-3"></i> Volver al Dashboard
            </a>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm">Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></span>
                <a href="../login/logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna de Información y Gestión -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Detalles del Ticket -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-2xl font-bold text-slate-700 mb-4 border-b pb-3">Detalles del Ticket</h2>
                <div class="space-y-3 text-sm">
                    <p><strong>Radicado:</strong> <span class="font-mono text-sky-600"><?php echo htmlspecialchars($ticket['numero_radicado']); ?></span></p>
                    <p><strong>Fecha de Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?></p>
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($ticket['usuario_nombre']); ?></p>
                    <p><strong>Correo:</strong> <?php echo htmlspecialchars($ticket['usuario_correo']); ?></p>
                    <p><strong>Área:</strong> <?php echo htmlspecialchars($ticket['usuario_area']); ?></p>
                    <p><strong>Tipo de Soporte:</strong> <?php echo htmlspecialchars($ticket['tipo_soporte']); ?></p>
                </div>
            </div>

            <!-- Formulario de Gestión -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-xl font-bold text-slate-700 mb-4">Gestionar Ticket</h3>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-4 p-3 rounded-md text-sm <?php echo $_SESSION['message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                <form method="POST" action="ver_ticket.php?id=<?php echo $id_ticket; ?>">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-4">
                        <label for="nivel_urgencia" class="block text-sm font-medium text-slate-600">Nivel de Urgencia</label>
                        <select name="nivel_urgencia" id="nivel_urgencia" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500">
                            <option value="básico" <?php echo $ticket['nivel_urgencia'] == 'básico' ? 'selected' : ''; ?>>Básico</option>
                            <option value="leve" <?php echo $ticket['nivel_urgencia'] == 'leve' ? 'selected' : ''; ?>>Leve</option>
                            <option value="urgente" <?php echo $ticket['nivel_urgencia'] == 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="estado" class="block text-sm font-medium text-slate-600">Estado del Ticket</label>
                        <select name="estado" id="estado" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500">
                            <option value="pendiente" <?php echo $ticket['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="en proceso" <?php echo $ticket['estado'] == 'en proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="resuelto" <?php echo $ticket['estado'] == 'resuelto' ? 'selected' : ''; ?>>Resuelto</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white font-bold px-5 py-2 rounded-lg shadow-md hover:from-sky-600 hover:to-sky-700 transition duration-300 w-full">
                        <i class="fas fa-save mr-2"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>

        <!-- Columna de Conversación -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold text-slate-700 mb-4 border-b pb-3">Conversación</h2>
            <div class="space-y-6">
                <!-- Mensaje Original del Usuario -->
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                        <i class="fas fa-user text-slate-500"></i>
                    </div>
                    <div class="flex-1 bg-slate-100 p-4 rounded-lg rounded-tl-none">
                        <div class="flex justify-between items-center">
                            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($ticket['usuario_nombre']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?></p>
                        </div>
                        <p class="mt-2 text-slate-700"><?php echo nl2br(htmlspecialchars($ticket['descripcion_problema'])); ?></p>
                    </div>
                </div>

                <!-- Historial de Respuestas -->
                <?php foreach ($historial as $respuesta): ?>
                    <?php if ($respuesta['autor_rol'] == 'administrador' || $respuesta['autor_rol'] == 'soporte'): ?>
                        <!-- Respuesta del Administrador (derecha) -->
                        <div class="flex items-start gap-4 flex-row-reverse">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-sky-500 flex items-center justify-center">
                                <i class="fas fa-shield-halved text-white"></i>
                            </div>
                            <div class="flex-1 bg-sky-100 p-4 rounded-lg rounded-tr-none">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold text-sky-800"><?php echo htmlspecialchars($respuesta['autor_nombre']); ?> (Soporte)</p>
                                    <p class="text-xs text-slate-500"><?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_respuesta'])); ?></p>
                                </div>
                                <p class="mt-2 text-slate-700"><?php echo nl2br(htmlspecialchars($respuesta['mensaje'])); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Respuesta del Usuario (izquierda) -->
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                                <i class="fas fa-user text-slate-500"></i>
                            </div>
                            <div class="flex-1 bg-slate-100 p-4 rounded-lg rounded-tl-none">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($respuesta['autor_nombre']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_respuesta'])); ?></p>
                                </div>
                                <p class="mt-2 text-slate-700"><?php echo nl2br(htmlspecialchars($respuesta['mensaje'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Formulario para Nueva Respuesta -->
            <div class="mt-8 border-t pt-6">
                <form method="POST" action="ver_ticket.php?id=<?php echo $id_ticket; ?>">
                    <input type="hidden" name="action" value="add_response">
                    <div>
                        <label for="mensaje" class="block text-sm font-medium text-slate-600 mb-2">Escribir una nueva respuesta</label>
                        <textarea name="mensaje" id="mensaje" rows="5" class="w-full p-3 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500" placeholder="Escribe tu respuesta aquí..." required></textarea>
                    </div>
                    <div class="mt-4 text-right">
                        <button type="submit" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white font-bold px-6 py-2 rounded-lg shadow-md hover:from-sky-600 hover:to-sky-700 transition duration-300">
                            <i class="fas fa-paper-plane mr-2"></i> Enviar Respuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>