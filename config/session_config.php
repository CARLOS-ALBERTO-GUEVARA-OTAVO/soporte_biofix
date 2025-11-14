<?php
/**
 * session_config.php
 *
 * Configura y arranca la sesión de forma segura.
 * Este archivo debe ser incluido al principio de cada página protegida.
 */

// 1. Configurar los parámetros de la cookie de sesión ANTES de iniciar la sesión.
// El 'lifetime' de 0 significa que la cookie expirará cuando el navegador se cierre.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true, // La cookie no será accesible por JavaScript (previene ataques XSS)
    'samesite' => 'Lax' // Ayuda a prevenir ataques CSRF
]);

// 2. Iniciar la sesión con la configuración ya aplicada.
session_start();