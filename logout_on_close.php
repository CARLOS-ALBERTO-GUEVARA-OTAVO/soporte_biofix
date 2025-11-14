<?php
/**
 * logout_on_close.php
 *
 * Este script es llamado por navigator.sendBeacon() cuando el usuario cierra la pestaña.
 * Su única función es destruir la sesión activa en el servidor.
 */
require_once __DIR__ . '/config/session_config.php';

// Destruye todos los datos de la sesión.
session_destroy();