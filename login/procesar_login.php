<?php
session_start();
require '../bd/db.php'; // Usamos la conexión PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Por favor, completa todos los campos.';
        header('Location: login.php');
        exit;
    }

    try {
        // 1. Buscamos al usuario por su email y traemos la información de su estado.
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.contraseña, r.nombre_rol, a.nombre_area
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                JOIN areas a ON u.id_area = a.id_area
                WHERE u.correo = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Verificamos si el usuario existe y si la contraseña es correcta
        if ($user && password_verify($password, $user['contraseña'])) {
            // Las credenciales son correctas, creamos la sesión.
                $_SESSION['usuario_id'] = $user['id_usuario']; // ID del usuario
                $_SESSION['usuario_nombre'] = $user['nombre']; // Nombre del usuario
                $_SESSION['usuario_rol'] = $user['nombre_rol']; // Nombre del rol (ej: 'administrador')
                $_SESSION['usuario_area'] = $user['nombre_area']; // Nombre del área (ej: 'TICS')
                $_SESSION['usuario_email'] = $user['correo']; // Guardamos el email para enviar correos

                // Redirigir según el rol del usuario
                if ($user['nombre_rol'] == 'administrador') {
                    header('Location: ../administrador/dashboard.php');
                } else {
                    header('Location: ../index.php');
                }
        } else {
            // Credenciales incorrectas (email no encontrado o contraseña errónea)
            $_SESSION['error'] = 'Correo o contraseña incorrectos.';
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        // En caso de un error de base de datos
        $_SESSION['error'] = 'Error del sistema. Por favor, intenta más tarde.';
        // podrías loggear el error real: error_log($e->getMessage());
        header('Location: login.php');
        exit;
    }
} else {
    // Si alguien intenta acceder directamente a este archivo
    header('Location: login.php');
    exit;
}