<?php

/**
 * validaciones.php
 *
 * Este archivo contiene funciones de validación reutilizables para la aplicación.
 */

/**
 * Valida la fortaleza de una contraseña.
 *
 * @param string $password La contraseña a validar.
 * @return bool True si la contraseña es suficientemente fuerte, False de lo contrario.
 */
function validarFortalezaContraseña(string $password): bool
{
    // Requisitos mínimos: 8 caracteres, una mayúscula, una minúscula, un número y un símbolo
    $longitudMinima = 8;
    $tieneMayuscula = preg_match('/[A-Z]/', $password);
    $tieneMinuscula = preg_match('/[a-z]/', $password);
    $tieneNumero = preg_match('/[0-9]/', $password);
    $tieneSimbolo = preg_match('/[^a-zA-Z0-9]/', $password);

    return (strlen($password) >= $longitudMinima && $tieneMayuscula && $tieneMinuscula && $tieneNumero && $tieneSimbolo);
}

?>