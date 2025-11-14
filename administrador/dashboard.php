<?php
// Incluimos la configuración de sesión segura que destruye la cookie al cerrar el navegador.
require_once __DIR__ . '/../config/session_config.php';

// --- REFUERZO DE SEGURIDAD CONTRA CACHÉ DEL NAVEGADOR ---
// Estas instrucciones le ordenan al navegador no guardar una copia de la página.
// Así, si el usuario cierra sesión y usa el botón "atrás", el navegador pedirá la página de nuevo
// y nuestro código PHP lo redirigirá al login porque la sesión ya no existe.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Generar un token CSRF si no existe uno en la sesión.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// 1. Generar un Nonce para la Política de Seguridad de Contenido (CSP)
$nonce = base64_encode(random_bytes(16));
require '../validaciones.php';
require '../vendor/autoload.php'; // Requerido para las librerías de Google y PhpSpreadsheet

// 1. Verificar si el usuario ha iniciado sesión.
// 2. Verificar si el usuario tiene el rol de Administrador (asumimos que el ID del rol es 1).
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] != 'administrador') {
    // Si no es un administrador, redirigir a la página principal o de login.
    header('Location: ../index.php');
    exit;
}

// Conexión a la base de datos PDO
require '../bd/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_ticket') {
    // Aquí iría la lógica para actualizar el estado y prioridad de un ticket
}

// --- LÓGICA PARA GESTIÓN DE USUARIOS (ADD/EDIT/DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_action'])) {
    // 1. VERIFICACIÓN DE TOKEN CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error de seguridad. La solicitud ha sido rechazada.'];
        header("Location: dashboard.php");
        exit;
    }

    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $id_rol = (int)($_POST['id_rol'] ?? 0);
    $id_area = (int)($_POST['id_area'] ?? 0);

    if ($_POST['user_action'] == 'add_user') {
        // Validación para agregar
        if (!empty($nombre) && !empty($correo) && !empty($password) && $id_rol > 0 && $id_area > 0) {
            if (!validarFortalezaContraseña($password)) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'La contraseña no cumple con los requisitos de seguridad.'];
                header("Location: dashboard.php");
                exit;
            }

            // 2. VERIFICACIÓN DE CORREO DUPLICADO
            $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
            $stmt_check->execute([$correo]);
            if ($stmt_check->fetch()) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'El correo electrónico ya está registrado. Por favor, utiliza otro.'];
                header("Location: dashboard.php");
                exit;
            }

             $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nombre, correo, contraseña, id_rol, id_area) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nombre, $correo, $hashed_password, $id_rol, $id_area]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario agregado correctamente.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al agregar usuario: Faltan datos o son inválidos.'];
        }
    } elseif ($_POST['user_action'] == 'edit_user') {
        $id_usuario = (int)($_POST['id_usuario'] ?? 0); // El error estaba aquí, el campo se llama 'id_usuario'
        if ($id_usuario > 0 && !empty($nombre) && !empty($correo) && $id_rol > 0 && $id_area > 0) {
            if (!empty($password)) {
                 if (!validarFortalezaContraseña($password)) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'La nueva contraseña no cumple con los requisitos de seguridad.'];
                    header("Location: dashboard.php");
                    exit;
                 }

                // 2. VERIFICACIÓN DE CORREO DUPLICADO (al editar)
                $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?");
                $stmt_check->execute([$correo, $id_usuario]);
                if ($stmt_check->fetch()) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'El correo electrónico ya pertenece a otro usuario.'];
                    header("Location: dashboard.php");
                    exit;
                }

                 // Si se proporciona una nueva contraseña, la actualizamos
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "UPDATE usuarios SET nombre = ?, correo = ?, contraseña = ?, id_rol = ?, id_area = ? WHERE id_usuario = ?"
                );
                $stmt->execute([$nombre, $correo, $hashed_password, $id_rol, $id_area, $id_usuario]);
            } else {
                // 2. VERIFICACIÓN DE CORREO DUPLICADO (al editar sin cambiar contraseña)
                $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?");
                $stmt_check->execute([$correo, $id_usuario]);
                if ($stmt_check->fetch()) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'El correo electrónico ya pertenece a otro usuario.'];
                    header("Location: dashboard.php");
                    exit;
                }

                // Si no, actualizamos todo excepto la contraseña
                $stmt = $pdo->prepare(
                    "UPDATE usuarios SET nombre = ?, correo = ?, id_rol = ?, id_area = ? WHERE id_usuario = ?"
                );
                $stmt->execute([$nombre, $correo, $id_rol, $id_area, $id_usuario]);
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario actualizado correctamente.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al actualizar usuario: Faltan datos o son inválidos.'];
        }
    }
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user_action']) && $_GET['user_action'] == 'delete_user') {
    $id_usuario = (int)($_GET['id_usuario'] ?? 0);
    // 1. VERIFICACIÓN DE TOKEN CSRF para la acción de eliminar
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error de seguridad. La acción de eliminar ha sido rechazada.'];
        header("Location: dashboard.php");
        exit;
    }

    if ($id_usuario > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario eliminado correctamente.'];
        } catch (PDOException $e) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al eliminar usuario: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de usuario inválido para eliminar.'];
    }
    header("Location: dashboard.php");
    exit;
}

// --- Obtener datos para la tabla de usuarios ---
$stmt_users = $pdo->query(
    "SELECT u.id_usuario, u.nombre, u.correo, u.id_rol, u.id_area, r.nombre_rol, a.nombre_area, u.fecha_registro 
     FROM usuarios u
     JOIN roles r ON u.id_rol = r.id_rol
     JOIN areas a ON u.id_area = a.id_area
     ORDER BY u.nombre ASC"
);
$users = $stmt_users->fetchAll();
$total_users = count($users);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 2. Política de Seguridad de Contenido (CSP) con Nonce -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://cdn.tailwindcss.com 'nonce-<?php echo $nonce; ?>'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self';">

    <title>Dashboard Administrador - Usuarios</title>
    <!-- Tailwind CSS -->
    <style>
        /* Pequeño ajuste para los mensajes de error de los inputs */
        .input-error-message { color: #dc2626; font-size: 0.8rem; margin-top: 4px; display: none; }

        /* Estilos para el modal de usuarios */
        .user-modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .user-modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-button:hover, .close-button:focus { color: black; text-decoration: none; cursor: pointer; }

        /* Estilos para las pestañas */
        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button.active {
            border-bottom-color: #0284c7; /* sky-600 */
            color: #0284c7;
        }

        /* --- Modal de Advertencia de Inactividad (mantener oscuro) --- */
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
            background-color: #1f2937; /* Darker background for warning */
            padding: 25px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
            max-width: 400px;
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Estilos de Validación Personalizados -->
    <link rel="stylesheet" href="../css/validaciones.css">
</head>
<body class="bg-slate-100 font-sans text-slate-800">
    <nav class="bg-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div>
                <a class="text-white text-xl font-bold flex items-center" href="#">
                    <i class="fas fa-shield-halved mr-2"></i> Dashboard de Soporte
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm">Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></span>
                <a href="../login/logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Modal de Advertencia de Inactividad -->
    <div id="inactivity-warning-modal" class="inactivity-modal">
        <div class="inactivity-modal-content text-slate-200">
            <h2 class="text-2xl font-bold mb-2">¡Tu sesión está a punto de expirar!</h2>
            <p>Por seguridad, tu sesión se cerrará automáticamente por inactividad.</p>
            <p>La sesión se cerrará en <strong id="countdown-timer" class="text-red-500">10</strong> segundos.</p>
            <p class="mt-4 text-sm text-slate-400">Mueve el mouse o presiona cualquier tecla para continuar.</p>
        </div>
    </div>

    <div class="container mx-auto mt-6">
        <!-- Mensajes de feedback -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Navegación de Pestañas -->
        <div class="border-b border-slate-300">
            <nav class="flex space-x-4" aria-label="Tabs">
                <button id="tab-users-btn" class="tab-button active font-semibold px-3 py-2 border-b-2 border-transparent text-slate-500 hover:text-sky-600">
                    <i class="fas fa-users mr-2"></i> Gestión de Usuarios
                </button>
                <button id="tab-tickets-btn" class="tab-button font-semibold px-3 py-2 border-b-2 border-transparent text-slate-500 hover:text-sky-600">
                    <i class="fas fa-ticket-alt mr-2"></i> Gestión de Tickets
                </button>
            </nav>
        </div>

        <!-- Contenido de las Pestañas -->
        <div class="mt-6">
            <!-- Pestaña de Gestión de Usuarios -->
            <div id="tab-users-content">
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-slate-700">Usuarios Registrados (Total: <?php echo $total_users; ?>)</h2>
                        <button id="openAddUserModal" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white font-bold px-5 py-2 rounded-lg shadow-md hover:from-sky-600 hover:to-sky-700 transition duration-300">
                            <i class="fas fa-user-plus mr-2"></i> Agregar Usuario
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Nombre</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Correo</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Rol</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Área</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <?php foreach ($users as $user): ?>
                                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['id_usuario']); ?></td>
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($user['nombre']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['correo']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars(ucfirst($user['nombre_rol'])); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($user['nombre_area']); ?></td>
                                        <td class="py-3 px-4">
                                            <button class="text-yellow-500 hover:text-yellow-600 mr-3 edit-user-btn" 
                                                    data-id="<?php echo $user['id_usuario']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($user['nombre']); ?>"
                                                    data-correo="<?php echo htmlspecialchars($user['correo']); ?>"
                                                    data-id_rol="<?php echo $user['id_rol']; ?>"
                                                    data-id_area="<?php echo $user['id_area']; ?>">
                                                <i class="fas fa-edit"></i>
                                                <span class="ml-1 hidden sm:inline">Editar</span>
                                            </button>
                                            <button class="text-red-500 hover:text-red-600 delete-user-btn"
                                                    data-id="<?php echo $user['id_usuario']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($user['nombre']); ?>">
                                                <i class="fas fa-trash"></i><span class="ml-1 hidden sm:inline">Eliminar</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pestaña de Gestión de Tickets -->
            <div id="tab-tickets-content" style="display: none;">
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-slate-700">Tickets de Soporte Recientes</h2>
                        <a href="generar_reporte.php" class="bg-green-600 text-white font-bold px-5 py-2 rounded-lg shadow-md hover:bg-green-700 transition duration-300">
                            <i class="fas fa-file-excel mr-2"></i> Generar Reporte
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Radicado</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Usuario</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Área</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Tipo Soporte</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Urgencia</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Estado</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-slate-600 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <?php
                                $stmt_tickets = $pdo->query(
                                    "SELECT t.id_ticket, t.numero_radicado, t.tipo_soporte, t.nivel_urgencia, t.estado, u.nombre as usuario_nombre, a.nombre_area as usuario_area
                                     FROM tickets t
                                     JOIN usuarios u ON t.id_usuario = u.id_usuario
                                     JOIN areas a ON u.id_area = a.id_area
                                    LEFT JOIN historial_respuestas h ON t.id_ticket = h.id_ticket
                                    LEFT JOIN usuarios u_admin ON h.id_usuario = u_admin.id_usuario AND u_admin.id_rol IN (1, 2)

                                     ORDER BY t.fecha_creacion DESC"
                                );
                                $tickets = $stmt_tickets->fetchAll();
                                if (count($tickets) > 0):
                                    foreach ($tickets as $ticket):
                                ?>
                                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                                            <td class="py-3 px-4 font-mono text-sky-600"><?php echo htmlspecialchars($ticket['numero_radicado']); ?></td>
                                            <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($ticket['usuario_nombre']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($ticket['usuario_area'] ?? 'N/A'); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($ticket['tipo_soporte']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($ticket['nivel_urgencia']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars(ucfirst($ticket['estado'])); ?></td>
                                            <td class="py-3 px-4">
                                                <a href="ver_ticket.php?id=<?php echo $ticket['id_ticket']; ?>" class="text-sky-600 hover:text-sky-800 font-semibold"><i class="fas fa-eye mr-1"></i> Ver</a>
                                            </td>
                                        </tr>
                                <?php 
                                    endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-8 text-slate-500 italic">No hay tickets de soporte registrados actualmente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Usuario -->
    <div id="userModal" class="user-modal">
        <div class="user-modal-content bg-white text-slate-800">
            <span class="close-button">&times;</span>
            <h2 id="userModalTitle" class="text-2xl font-bold text-slate-700 mb-6">Agregar Nuevo Usuario</h2>
            <form id="userForm" method="POST" action="dashboard.php">
                <input type="hidden" name="user_action" id="userAction" value="add_user">
                <input type="hidden" name="id_usuario" id="idUsuario">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label for="nombre" class="block text-sm font-medium text-slate-600">Nombre Completo</label>
                        <input type="text" name="nombre" id="nombre" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 transition-colors" required>
                        <div id="nombre-error" class="input-error-message"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="correo" class="block text-sm font-medium text-slate-600">Correo Electrónico</label>
                        <input type="email" name="correo" id="correo" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 transition-colors" required>
                        <div id="correo-error" class="input-error-message"></div>
                    </div>

                    <div class="mb-4">
                        <label for="id_rol" class="block text-sm font-medium text-slate-600">Rol</label>
                        <select name="id_rol" id="id_rol" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500" required>
                            <option value="">Seleccionar rol...</option>
                            <?php $stmt_roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol"); while ($rol = $stmt_roles->fetch()) { echo "<option value='{$rol['id_rol']}'>" . htmlspecialchars(ucfirst($rol['nombre_rol'])) . "</option>"; } ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="id_area" class="block text-sm font-medium text-slate-600">Área</label>
                        <select name="id_area" id="id_area" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500" required>
                            <option value="">Seleccionar área...</option>
                            <?php $stmt_areas = $pdo->query("SELECT id_area, nombre_area FROM areas ORDER BY nombre_area"); while ($area = $stmt_areas->fetch()) { echo "<option value='{$area['id_area']}'>" . htmlspecialchars($area['nombre_area']) . "</option>"; } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-slate-600">Contraseña <span id="passwordHint" class="text-slate-500 text-xs">(Dejar en blanco para no cambiar)</span></label>
                    <input type="password" name="password" id="password" class="mt-1 block w-full p-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 transition-colors">
                    <ul id="password-strength" class="validation-list">
                        <li id="length-check" class="validation-item"><i class="fas fa-times-circle"></i> Al menos 8 caracteres.</li>
                        <li id="upper-check" class="validation-item"><i class="fas fa-times-circle"></i> Al menos una mayúscula.</li>
                        <li id="lower-check" class="validation-item"><i class="fas fa-times-circle"></i> Al menos una minúscula.</li>
                        <li id="number-check" class="validation-item"><i class="fas fa-times-circle"></i> Al menos un número.</li>
                        <li id="symbol-check" class="validation-item"><i class="fas fa-times-circle"></i> Al menos un símbolo.</li>
                    </ul>
                </div>

                <button type="submit" id="saveUserBtn" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white font-bold px-5 py-3 rounded-lg shadow-md hover:from-sky-600 hover:to-sky-700 transition duration-300 w-full disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-save mr-2"></i> Guardar Usuario
                </button>
            </form>
        </div>
    </div>

    <!-- Modal de Confirmación para Eliminar Usuario -->
    <div id="deleteUserModal" class="user-modal">
        <div class="user-modal-content bg-white text-slate-800 max-w-md">
            <span id="closeDeleteModal" class="close-button">&times;</span>
            <h2 class="text-2xl font-bold text-red-600 mb-4"><i class="fas fa-exclamation-triangle mr-2"></i> Confirmar Eliminación</h2>
            <p class="mb-6">
                ¿Estás seguro de que quieres eliminar permanentemente al usuario <strong id="deleteUserName" class="text-red-700"></strong>?
                <br>
                <span class="text-sm text-slate-600">Esta acción es irreversible.</span>
            </p>
            <div class="flex justify-end gap-4">
                <button id="cancelDeleteBtn" class="px-6 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 transition">
                    Cancelar
                </button>
                <a id="confirmDeleteBtn" href="#" class="px-6 py-2 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition">
                    Sí, Eliminar
                </a>
            </div>
        </div>
    </div>

    <!-- Script del temporizador de inactividad. La configuración se pasa mediante atributos data-*. -->
    <!-- Para cambiar el tiempo de inactividad: -->
    <!-- 1 minuto  = 60000 -->
    <!-- 5 minutos = 300000 -->
    <!-- 10 minutos = 600000 -->
    <script src="../config/inactivity-timer.js" data-logout-url="../login/logout.php" data-timeout="60000"></script>
    <!-- 
        CORRECCIÓN: Se apunta a la ruta correcta del archivo de validaciones.
        El archivo estaba en la carpeta /css en lugar de /js.
    -->
    <script src="../css/validaciones.js"></script>
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Verificación de seguridad: si las funciones de validación no se cargaron, no continuar para evitar errores.
            if (typeof validarNombre !== 'function' || typeof actualizarUIListaPassword !== 'function') {
                console.error("Error Crítico: El archivo 'validaciones.js' no se cargó correctamente o contiene errores. La funcionalidad de los formularios está desactivada.");
                return; // Detiene la ejecución para no romper los botones.
            }

            // --- Lógica de Pestañas ---
            const tabUsersBtn = document.getElementById('tab-users-btn');
            const tabTicketsBtn = document.getElementById('tab-tickets-btn');
            const tabUsersContent = document.getElementById('tab-users-content');
            const tabTicketsContent = document.getElementById('tab-tickets-content');

            tabUsersBtn.addEventListener('click', () => {
                tabUsersContent.style.display = 'block';
                tabTicketsContent.style.display = 'none';
                tabUsersBtn.classList.add('active');
                tabTicketsBtn.classList.remove('active');
            });

            tabTicketsBtn.addEventListener('click', () => {
                tabUsersContent.style.display = 'none';
                tabTicketsContent.style.display = 'block';
                tabTicketsBtn.classList.add('active');
                tabUsersBtn.classList.remove('active');
            });

            // --- Lógica del Modal de Usuarios ---
            const userModal = document.getElementById('userModal');
            const closeButton = userModal.querySelector('.close-button');
            const openAddUserModalBtn = document.getElementById('openAddUserModal');
            const userForm = document.getElementById('userForm');
            const userModalTitle = document.getElementById('userModalTitle');
            const userActionInput = document.getElementById('userAction');
            const idUsuarioInput = document.getElementById('idUsuario');
            const nombreInput = document.getElementById('nombre');
            const correoInput = document.getElementById('correo');
            const passwordInput = document.getElementById('password');
            const passwordHint = document.getElementById('passwordHint');
            const idRolSelect = document.getElementById('id_rol');
            const idAreaSelect = document.getElementById('id_area');
            const saveUserBtn = document.getElementById('saveUserBtn');

            // Elementos para validación
            const nombreError = document.getElementById('nombre-error');
            const correoError = document.getElementById('correo-error');
            const passwordStrengthList = document.getElementById('password-strength');
            const passwordChecks = {
                length: document.getElementById('length-check'),
                upper: document.getElementById('upper-check'),
                lower: document.getElementById('lower-check'),
                number: document.getElementById('number-check'),
                symbol: document.getElementById('symbol-check'),
            };

            // Función para validar todo el formulario y habilitar/deshabilitar el botón de guardar
            function validarFormulario() {
                const esNombreValido = validarNombre(nombreInput.value);
                const esCorreoValido = validarCorreo(correoInput.value);
                
                let esPasswordValido = false;
                const password = passwordInput.value;

                if (passwordInput.required) { // Si es agregar usuario, la contraseña es obligatoria
                    const strength = validarFortalezaPassword(password);
                    esPasswordValido = Object.values(strength).every(Boolean);
                } else { // Si es editar usuario
                    if (password.length === 0) { // Si está vacía, es válida (no se cambia)
                        esPasswordValido = true;
                    } else { // Si se está escribiendo una nueva, debe ser fuerte
                        const strength = validarFortalezaPassword(password);
                        esPasswordValido = Object.values(strength).every(Boolean);
                    }
                }
                
                saveUserBtn.disabled = !(esNombreValido && esCorreoValido && esPasswordValido);
            }

            // Event listeners para validación en tiempo real
            nombreInput.addEventListener('input', () => {
                const isValid = validarNombre(nombreInput.value);
                actualizarUIValidacionCampo(nombreInput, nombreError, isValid, 'El nombre debe tener al menos 3 caracteres.');
                validarFormulario();
            });

            correoInput.addEventListener('input', () => {
                const isValid = validarCorreo(correoInput.value);
                actualizarUIValidacionCampo(correoInput, correoError, isValid, 'Por favor, introduce un correo válido.');
                validarFormulario();
            });

            passwordInput.addEventListener('input', () => {
                const password = passwordInput.value;
                const strength = validarFortalezaPassword(password);
                actualizarUIListaPassword(passwordChecks.length, strength.length);
                actualizarUIListaPassword(passwordChecks.upper, strength.upper);
                actualizarUIListaPassword(passwordChecks.lower, strength.lower);
                actualizarUIListaPassword(passwordChecks.number, strength.number);
                actualizarUIListaPassword(passwordChecks.symbol, strength.symbol);
                validarFormulario();
            });

            // --- Lógica del Modal de Eliminación ---
            const deleteModal = document.getElementById('deleteUserModal');
            const closeDeleteModalBtn = document.getElementById('closeDeleteModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const deleteUserNameSpan = document.getElementById('deleteUserName');
            const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    const id = e.currentTarget.dataset.id;
                    const nombre = e.currentTarget.dataset.nombre;

                    deleteUserNameSpan.textContent = nombre;
                    confirmDeleteBtn.href = `?user_action=delete_user&id_usuario=${id}&csrf_token=${csrfToken}`;
                    deleteModal.style.display = 'flex';
                });
            });

            closeDeleteModalBtn.addEventListener('click', () => deleteModal.style.display = 'none');
            cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');


            openAddUserModalBtn.addEventListener('click', () => {
                userModalTitle.textContent = 'Agregar Nuevo Usuario';
                userActionInput.value = 'add_user';
                idUsuarioInput.value = '';
                userForm.reset();
                passwordInput.required = true; // Contraseña es requerida al agregar
                passwordHint.style.display = 'none'; // Ocultar hint de contraseña
                passwordStrengthList.style.display = 'block';
                // Al agregar, el botón debe estar deshabilitado hasta que la contraseña sea válida
                saveUserBtn.disabled = true; // Deshabilitado por defecto
                // Asegurarse de que todos los checks empiecen en rojo
                Object.values(passwordChecks).forEach(el => actualizarUIListaPassword(el, false));
                userModal.style.display = 'flex';
            });

            document.querySelectorAll('.edit-user-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    const id = e.currentTarget.dataset.id;
                    const nombre = e.currentTarget.dataset.nombre;
                    const correo = e.currentTarget.dataset.correo;
                    const id_rol = e.currentTarget.dataset.id_rol;
                    const id_area = e.currentTarget.dataset.id_area;

                    userModalTitle.textContent = 'Editar Usuario';
                    userActionInput.value = 'edit_user';
                    idUsuarioInput.value = id;
                    nombreInput.value = nombre;
                    correoInput.value = correo;
                    idRolSelect.value = id_rol;
                    idAreaSelect.value = id_area;
                    passwordInput.value = ''; // Limpiar contraseña por seguridad
                    passwordInput.required = false; // Contraseña no es requerida al editar
                    passwordHint.style.display = 'inline'; // Mostrar hint de contraseña
                    // Mostrar la lista de fortaleza, pero no deshabilitar el botón si no se cambia la contraseña
                    if (passwordStrengthList) passwordStrengthList.style.display = 'block';
                    saveUserBtn.disabled = false; // Habilitado por defecto al editar
                    // Resetear los checks (se validarán si el usuario escribe)
                    Object.values(passwordChecks).forEach(el => actualizarUIListaPassword(el, false));
                    userModal.style.display = 'flex';
                });
            });

            closeButton.addEventListener('click', () => {
                userModal.style.display = 'none';
                // Resetear todos los mensajes y estilos de error al cerrar
                actualizarUIValidacionCampo(nombreInput, nombreError, true, '');
                actualizarUIValidacionCampo(correoInput, correoError, true, '');
                passwordStrengthList.style.display = 'none';
                Object.values(passwordChecks).forEach(el => actualizarUIListaPassword(el, false));
            });

            // Se ha eliminado el evento que cerraba el modal al hacer clic fuera.
            // Ahora, el modal solo se puede cerrar con el botón 'x', como fue solicitado.
            // window.addEventListener('click', (event) => { ... });

            // --- LÓGICA PARA DESTRUIR LA SESIÓN AL CERRAR LA PESTAÑA ---
            // Utilizamos 'visibilitychange' porque es más fiable que 'beforeunload'.
            // Se activa cuando la pestaña deja de estar visible.
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    navigator.sendBeacon('logout_on_close.php', '');
                }
            });
       });
    </script>

    <?php $pdo = null; // Cierra la conexión PDO ?>
</body>
</html>