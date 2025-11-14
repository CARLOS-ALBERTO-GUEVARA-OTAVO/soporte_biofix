<?php
// Incluimos la configuración de sesión segura que destruye la cookie al cerrar el navegador.
require_once __DIR__ . '/config/session_config.php';

// --- REFUERZO DE SEGURIDAD CONTRA CACHÉ DEL NAVEGADOR ---
// Estas instrucciones le ordenan al navegador no guardar una copia de la página.
// Así, si el usuario cierra sesión y usa el botón "atrás", el navegador pedirá la página de nuevo
// y nuestro código PHP lo redirigirá al login porque la sesión ya no existe.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 1. Si el usuario no ha iniciado sesión, se le redirige a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login/login.php');
    exit;
}

// 2. REFUERZO DE SEGURIDAD: Si el usuario es un administrador, no debe estar en el portal de usuario.
// Se le redirige a su propio dashboard para evitar la vulnerabilidad de acceso indebido.
if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'administrador') {
    header('Location: administrador/dashboard.php');
    exit;
}

// 1. Conectar a la BD y obtener los tickets del usuario
require 'bd/db.php';
$id_usuario_actual = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id_ticket, numero_radicado, tipo_soporte, descripcion_problema, estado, fecha_creacion 
         FROM tickets WHERE id_usuario = ? ORDER BY fecha_creacion DESC"
    );
    $stmt->execute([$id_usuario_actual]);
    $tickets_usuario = $stmt->fetchAll();
} catch (PDOException $e) {
    $tickets_usuario = []; // En caso de error, la lista estará vacía
    error_log("Error al obtener tickets: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Archivos de Google Drive</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal_tickets.css"> <!-- Estilos de la tabla de tickets -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* --- Reset Básico y Variables de Color --- */
        :root {
            --color-bg: #f8f9fa;
            --color-surface: #ffffff;
            --color-primary: #007bff;
            --color-primary-dark: #0056b3;
            --color-text-primary: #212529;
            --color-text-secondary: #6c757d;
            --color-border: #dee2e6;
            --color-success: #198754;
            --color-success-bg: #d1e7dd;
            --color-error: #dc3545;
            --color-error-bg: #f8d7da;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            /* Colores para estados de tickets */
            --status-pendiente-bg: #f1f5f9; /* slate-100 */
            --status-pendiente-text: #475569; /* slate-600 */
            --status-enproceso-bg: #cffafe; /* cyan-100 */
            --status-enproceso-text: #0e7490; /* cyan-700 */
            --status-resuelto-bg: #dcfce7; /* green-100 */
            --status-resuelto-text: #166534; /* green-800 */
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-primary);
            margin: 0;
        }

        /* --- Modal de Advertencia de Inactividad --- */
        .inactivity-modal {
            display: none; /* Oculto por defecto */
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }
        .inactivity-modal-content {
            background-color: #fff;
            padding: 25px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
            max-width: 400px;
        }
        
        /* --- Estilos para el Modal de Ticket --- */
        .ticket-modal {
            display: none;
            opacity: 0;
            position: fixed;
            z-index: 1060;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease-in-out;
        }
        .ticket-modal.show {
            display: flex;
            opacity: 1;
        }
        .ticket-modal-content {
            background: white;
            padding: 24px 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: var(--shadow-md);
            transform: translateY(-20px);
            transition: transform 0.3s ease-in-out;
        }
        .ticket-modal.show .ticket-modal-content {
            transform: translateY(0);
        }
        .close-modal {
            position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer;
            color: #aaa; font-weight: bold;
        }
        .close-modal:hover {
            color: #333;
        }
        .ticket-form-group {
            margin-bottom: 16px;
        }
        .ticket-form-group label {
            display: block; margin-bottom: 6px; font-weight: 500; color: var(--color-text-secondary);
        }
        .ticket-form-group select, .ticket-form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        /* --- Barra de Información del Usuario --- */
        .user-info-header {
            background-color: var(--color-surface);
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.9rem;
            color: var(--color-text-secondary);
            box-shadow: var(--shadow-sm);
        }

        .container {
            /* Aumentamos el ancho máximo para aprovechar mejor las pantallas grandes */
            max-width: 1200px; 
            margin: 20px auto;
            padding: 0 15px;
        }

        .card {
            /* La tarjeta ahora ocupará el 100% del ancho de su contenedor padre */
            width: 100%; 
            background-color: var(--color-surface);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .card-wrapper {
            display: flex; /* Usamos flexbox para centrar la tarjeta fácilmente */
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--color-border);
        }

        .header-top { 
            display: flex; 
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px;
        }

        #folder-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text-primary);
            margin: 0;
        }

        .status {
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: opacity 0.5s ease-in-out;
            margin: 0;
        }
        .status-success {
            background-color: var(--color-success-bg);
            color: var(--color-success);
        }
        .status-error {
            background-color: var(--color-error-bg);
            color: var(--color-error);
        }

        main {
            padding: 16px 24px 24px;
        }

        /* --- Breadcrumbs (Ruta de navegación) --- */
        #breadcrumb-container {
            margin-bottom: 16px;
            font-size: 0.95rem;
            color: var(--color-text-secondary);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        #breadcrumb-container a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        #breadcrumb-container a:hover {
            color: var(--color-primary-dark);
            text-decoration: underline;
        }
        #breadcrumb-container .separator { margin: 0 8px; }
        #breadcrumb-container .current-folder { font-weight: 500; color: var(--color-text-primary); }

        /* --- Lista de Archivos --- */
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-item {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start; /* Alineamos al inicio para mejor estructura vertical */
            padding: 12px 8px;
            border-bottom: 1px solid var(--color-border);
            transition: background-color 0.2s ease-in-out;
        }
        .file-item:last-child { border-bottom: none; }
        .file-item:hover { background-color: var(--color-bg); }

        .file-item a {
            flex-grow: 1;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1f2937;
            font-weight: 500;
            font-size: 1rem; /* Aumentamos el tamaño del nombre del archivo */
            min-width: 250px; /* Evita que el nombre se comprima demasiado */
            padding-top: 4px; /* Pequeño ajuste vertical */
        }
        .file-item a:hover { color: var(--color-primary-dark); }
        .file-icon { width: 22px; height: 22px; margin-right: 14px; flex-shrink: 0; }

        .file-dates {
            display: flex;
            flex-direction: column;
            align-items: flex-end; /* Alineamos las fechas a la derecha */
            font-size: 0.875rem; /* Aumentamos el tamaño de la fuente de las fechas */
            color: var(--color-text-secondary);
            flex-shrink: 0;
            padding-left: 16px;
            text-align: right; /* Alineamos el texto a la derecha */
            line-height: 1.5; /* Mejoramos el espaciado entre líneas */
        }

        .no-files { color: var(--color-text-secondary); padding: 40px 0; text-align: center; font-style: italic; }

        /* --- Formulario de Búsqueda --- */
        .search-form { display: flex; flex-grow: 1; max-width: 400px; }
        .search-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: 6px 0 0 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus { 
            outline: none; 
            border-color: var(--color-primary); 
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); 
        }

        .search-button {
            padding: 10px 16px;
            border: 1px solid var(--color-primary);
            background-color: var(--color-primary);
            color: white;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .search-button:hover { background-color: var(--color-primary-dark); }

        /* --- Ubicación del archivo en resultados de búsqueda --- */
        .file-location {
            font-size: 0.875rem; /* Mismo tamaño que las fechas */
            color: var(--color-text-secondary);
            text-align: right; /* Aseguramos alineación a la derecha */
            margin-bottom: 4px; /* Espacio entre la ubicación y la fecha de modificación */
        }
        .file-location > span {
            font-weight: 500; /* Hacemos "En carpeta:" un poco más notorio */
            color: #555;
        }

        .file-location a { color: var(--color-primary); text-decoration: none; font-weight: normal; }
        .file-location a:hover { text-decoration: underline; }

        /* --- Botón de Cerrar Sesión --- */
        .footer-actions {
            margin-top: 24px;
            padding: 16px 24px;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logout-link {
            color: var(--color-error);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .logout-link:hover {
            background-color: var(--color-error-bg);
        }
        
        /* --- Estilos de la tabla de tickets --- */
        .tickets-table {
            width: 100%;
            border-collapse: separate; /* Usar separate para border-spacing */
            border-spacing: 0 8px; /* Espacio entre filas */
        }

        .tickets-table th,
        .tickets-table td {
            padding: 12px 16px;
            text-align: left;
            vertical-align: middle;
        }

        .tickets-table thead th {
            background-color: #e2e8f0; /* slate-200 */
            color: #475569; /* slate-700 */
            font-weight: 600;
            font-size: 0.875rem; /* text-sm */
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #cbd5e1; /* slate-300 */
        }

        .tickets-table tbody tr {
            background-color: var(--color-surface);
            border-radius: 8px; /* Bordes redondeados para cada fila */
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease-in-out;
        }

        .tickets-table tbody tr:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* Estilos específicos para las filas de ticket */
        .ticket-row {
            cursor: pointer;
        }

        .ticket-row.expanded {
            background-color: #f0f9ff; /* sky-50 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
        }

        .ticket-row td {
            border: none; /* Eliminar bordes internos de celdas */
        }

        .ticket-row td:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .ticket-row td:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        /* Detalles de la fila (oculta por defecto) */
        .details-row {
            display: none; /* Ocultar por defecto */
            background-color: #f8fafc; /* slate-50 */
            border-radius: 8px;
            margin-top: -8px; /* Para que se "pegue" a la fila principal */
            margin-bottom: 8px;
            box-shadow: var(--shadow-sm);
        }

        .details-row.show {
            display: table-row;
        }

        .details-row td {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0; /* slate-200 */
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .request-section, .response-section {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .request-section {
            background-color: #e0f2fe; /* light blue */
            border-left: 4px solid #0ea5e9; /* sky-500 */
        }

        .response-section {
            background-color: #eef2ff; /* indigo-50 */
            border-left: 4px solid #6366f1; /* indigo-500 */
        }

        .support-ticket-btn {
            background-color: var(--color-primary);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .support-ticket-btn:hover { background-color: var(--color-primary-dark); }

        .ver-respuesta-btn {
            background-color: var(--color-primary);
            color: white;
            padding: 6px 12px; /* Ajustar padding */
            border-radius: 999px;
            font-size: 0.8rem; /* Ajustar tamaño de fuente */
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s;
            display: inline-flex; /* Para alinear icono y texto */
            align-items: center;
            gap: 6px;
        }
        .ver-respuesta-btn:hover { background-color: var(--color-primary-dark); transform: translateY(-1px); }
        .ver-respuesta-btn.expanded .fas { transform: rotate(180deg); }

        /* --- Media Queries para Responsividad --- */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }
            .search-form {
                width: 100%;
                max-width: none;
            }
            .file-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .file-dates {
                align-items: flex-start; /* Mantenemos la alineación para móvil */
                padding-left: 0;
                margin-top: 8px;
                width: 100%;
                font-size: 0.75rem;
            }
            /* Responsive table for tickets */
            .tickets-table {
                border-spacing: 0 4px;
            }
            .tickets-table th,
            .tickets-table td {
                padding: 8px 12px;
                font-size: 0.875rem;
            }
            .tickets-table thead {
                display: none; /* Ocultar encabezado en móviles */
            }
            .tickets-table tbody tr {
                display: block;
                margin-bottom: 12px;
            }
            .tickets-table tbody tr td {
                display: block;
                text-align: right;
                padding-left: 40%; /* Espacio para la etiqueta */
                position: relative;
            }
            .tickets-table tbody tr td::before {
                content: attr(data-label);
                position: absolute;
                left: 12px;
                width: 35%;
                text-align: left;
                font-weight: 600;
                color: #64748b; /* slate-600 */
            }
            .tickets-table tbody tr td:first-child {
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
            }
            .tickets-table tbody tr td:last-child {
                border-bottom-left-radius: 8px;
                border-bottom-right-radius: 8px;
            }
            .details-row td {
                padding: 15px;
            }
            .ver-respuesta-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <header class="user-info-header">
        <p style="margin: 0;">
            <span>
                Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong> | 
                Área: <strong><?php echo htmlspecialchars($_SESSION['usuario_area']); ?></strong>
            </span>
            <a href="login/logout.php" class="logout-link" style="float: right; margin-top: -5px;">
                Cerrar sesión <i class="fas fa-sign-out-alt"></i>
            </a>
        </p>
    </header>

    <!-- Modal de Advertencia de Inactividad -->
    <div id="inactivity-warning-modal" class="inactivity-modal">
        <div class="inactivity-modal-content">
            <h2>¡Tu sesión está a punto de expirar!</h2>
            <p>Por seguridad, tu sesión se cerrará automáticamente por inactividad.</p>
            <p>La sesión se cerrará en <strong id="countdown-timer">10</strong> segundos.</p>
            <p><small>Mueve el mouse o presiona cualquier tecla para continuar.</small></p>
        </div>
    </div>

    <!-- Modal para Crear Ticket de Soporte -->
    <div id="ticket-modal" class="ticket-modal"> <!-- La clase 'show' se añade con JS -->
        <div class="ticket-modal-content">
            <span id="close-ticket-modal" class="close-modal">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 24px; color: var(--color-text-primary);">Crear Ticket de Soporte</h2>
            <form id="ticket-form">
                <div class="ticket-form-group">
                    <label for="tipo_soporte">Tipo de Soporte</label>
                    <select id="tipo_soporte" name="tipo_soporte" required>
                        <option value="">Seleccione un problema...</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="Red">Red</option>
                        <option value="Correo">Correo</option>
                        <option value="Sistema Interno">Sistema Interno</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="ticket-form-group">
                    <label for="descripcion_problema">Describa su problema</label>
                    <textarea id="descripcion_problema" name="descripcion_problema" rows="5" required placeholder="Sea lo más detallado posible..."></textarea>
                </div>
                <div id="ticket-status-message" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 5px;"></div>
                <button type="submit" class="search-button" style="width: 100%; border-radius: 6px;">Enviar Ticket</button>
            </form>
        </div>
    </div>

    <div class="container">
        <!-- Encabezado de la Página -->
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 class="page-title" style="font-size: 1.75rem; font-weight: 700;">Portal de Soporte Técnico</h1>
            <a href="#" id="create-ticket-btn" class="support-ticket-btn">
                <i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Crear Ticket de Soporte
            </a>
        </div>

        <!-- Tarjeta con la Tabla de Tickets -->
        <div class="card">
            <div class="card-body">
                <h2 class="card-title">Mis Tickets Registrados</h2>
                <div class="overflow-x-auto"> <!-- Contenedor para scroll horizontal en pantallas pequeñas -->
                    <?php if (empty($tickets_usuario)): ?>
                        <p class="text-center py-8 text-slate-500 italic">
                            No has creado ningún ticket de soporte. Haz clic en "Crear Ticket de Soporte" para empezar.
                        </p>
                    <?php else: ?>
                        <table class="tickets-table">
                            <thead>
                                <tr>
                                    <th>Radicado</th>
                                    <th>Tipo de Soporte</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets_usuario as $ticket): ?>
                                    <?php
                                    $is_resolved = strtolower($ticket['estado']) == 'resuelto';
                                    $respuesta_admin = null;
                                    if ($is_resolved) {
                                        $stmt_respuesta = $pdo->prepare(
                                            "SELECT h.mensaje FROM historial_respuestas h
                                             JOIN usuarios u ON h.id_usuario = u.id_usuario
                                             WHERE h.id_ticket = ? AND u.id_rol IN (1, 2) 
                                             ORDER BY h.fecha_respuesta DESC LIMIT 1"
                                        );
                                        $stmt_respuesta->execute([$ticket['id_ticket']]);
                                        $respuesta_admin = $stmt_respuesta->fetchColumn();
                                    }
                                    $row_class = !$is_resolved ? 'ticket-row-expandable' : '';
                                    ?>                                    <tr class="ticket-row" data-ticket-id="<?php echo $ticket['id_ticket']; ?>">
                                        <td class="font-mono text-sky-600 font-semibold"><?php echo htmlspecialchars($ticket['numero_radicado']); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['tipo_soporte']); ?></td>
                                        <td class="max-w-sm truncate" title="<?php echo htmlspecialchars($ticket['descripcion_problema']); ?>"><?php echo htmlspecialchars($ticket['descripcion_problema']); ?></td>
                                        <td>
                                            <span class="px-3 py-1 font-bold text-xs rounded-full" style="background-color: var(--status-<?php echo str_replace(' ', '', strtolower($ticket['estado'])); ?>-bg); color: var(--status-<?php echo str_replace(' ', '', strtolower($ticket['estado'])); ?>-text);">
                                                <?php echo htmlspecialchars(ucfirst($ticket['estado'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($is_resolved && $respuesta_admin): ?>
                                                <button class="ver-respuesta-btn" data-ticket-id="<?php echo $ticket['id_ticket']; ?>">Ver Respuesta <i class="fas fa-chevron-down ml-2"></i></button>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="details-row" id="details-<?php echo $ticket['id_ticket']; ?>">
                                        <td colspan="5">
                                            <div class="request-section">
                                                <p style="font-weight: bold; color: var(--color-text-secondary); margin-top:0; margin-bottom: 8px;">Tu Solicitud Original:</p>
                                                <p style="margin-bottom: 4px;"><strong>Asunto:</strong> <?php echo htmlspecialchars($ticket['descripcion_problema']); ?></p>
                                                <p style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0;"><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?></p>
                                            </div>
                                            <?php if ($respuesta_admin): ?>
                                                <div class="response-section">
                                                    <p style="font-weight: bold; color: #3730a3; margin-top:0; margin-bottom: 8px;">Respuesta del Equipo de Soporte:</p>
                                                    <p style="color: #4338ca; margin-bottom: 0;"><?php echo nl2br(htmlspecialchars($respuesta_admin)); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div> <!-- Fin de card-body -->
        </div> <!-- Fin de card -->
    </div>

    <!-- Script del temporizador de inactividad. La configuración se pasa mediante atributos data-*. -->
    <!-- Para cambiar el tiempo de inactividad: -->
    <!-- 1 minuto  = 60000 -->
    <!-- 5 minutos = 300000 -->
    <!-- 10 minutos = 600000 -->
    <script src="config/inactivity-timer.js" data-logout-url="login/logout.php" data-timeout="60000"></script>
    <script src="portal_tickets.js"></script> <!-- Lógica de la tabla de tickets -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Lógica para el Modal de Crear Ticket ---
            const ticketModal = document.getElementById('ticket-modal');
            const createTicketBtn = document.getElementById('create-ticket-btn');
            const closeTicketModalBtn = document.getElementById('close-ticket-modal');
            const ticketForm = document.getElementById('ticket-form');
            const statusMessage = document.getElementById('ticket-status-message');

            // Abrir modal
            createTicketBtn.addEventListener('click', function(e) {
                e.preventDefault();
                ticketModal.classList.add('show');
            });

            // Cerrar modal con el botón 'x'
            closeTicketModalBtn.addEventListener('click', function() {
                ticketModal.classList.remove('show');
            });

            // Cerrar modal al hacer clic fuera del contenido
            ticketModal.addEventListener('click', function(e) {
                if (e.target === ticketModal) {
                    ticketModal.classList.remove('show');
                }
            });

            // --- Lógica para enviar el formulario del ticket ---
            ticketForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(ticketForm);
                const submitButton = ticketForm.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = 'Enviando...';

                fetch('crear_ticket.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    statusMessage.style.display = 'block';
                    statusMessage.textContent = data.message;
                    if (data.status === 'success') {
                        statusMessage.style.backgroundColor = 'var(--color-success-bg)';
                        statusMessage.style.color = 'var(--color-success)';
                        ticketForm.reset();
                        // Recargar la página después de 3 segundos para ver el nuevo ticket
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        statusMessage.style.backgroundColor = 'var(--color-error-bg)';
                        statusMessage.style.color = 'var(--color-error)';
                    }
                })
                .catch(error => {
                    statusMessage.style.display = 'block';
                    statusMessage.textContent = 'Ocurrió un error de red. Por favor, inténtalo de nuevo.';
                    statusMessage.style.backgroundColor = 'var(--color-error-bg)';
                    statusMessage.style.color = 'var(--color-error)';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Enviar Ticket';
                });
            });
        });
    </script>
</body>
</html>
