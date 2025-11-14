/**
 * c:/xampp/htdocs/soporte_biofix/js/validaciones.js
 * 
 * Este archivo contiene las funciones de validación en tiempo real para los formularios.
 */

/**
 * Valida que el campo no esté vacío y tenga una longitud mínima.
 * @param {string} nombre El valor del campo de nombre.
 * @returns {boolean} True si es válido, de lo contrario False.
 */
function validarNombre(nombre) {
    return nombre.trim().length >= 3;
}

/**
 * Valida que el correo electrónico tenga un formato válido.
 * @param {string} correo El valor del campo de correo.
 * @returns {boolean} True si es válido, de lo contrario False.
 */
function validarCorreo(correo) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(correo);
}

/**
 * Valida la fortaleza de una contraseña y devuelve un objeto con los resultados.
 * @param {string} password La contraseña a validar.
 * @returns {object} Un objeto que indica qué reglas se cumplen.
 */
function validarFortalezaPassword(password) {
    return {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        symbol: /[^a-zA-Z0-9]/.test(password)
    };
}

/**
 * Actualiza la UI de un campo de texto (borde y mensaje de error).
 * @param {HTMLInputElement} inputElement El elemento del input.
 * @param {HTMLElement} errorElement El elemento donde se mostrará el error.
 * @param {boolean} isValid Si el campo es válido o no.
 * @param {string} errorMessage El mensaje de error a mostrar.
 */
function actualizarUIValidacionCampo(inputElement, errorElement, isValid, errorMessage) {
    if (isValid) {
        inputElement.classList.remove('border-red-500', 'focus:border-red-500');
        inputElement.classList.add('border-slate-300', 'focus:border-sky-500');
        errorElement.style.display = 'none';
        errorElement.textContent = '';
    } else {
        inputElement.classList.remove('border-slate-300', 'focus:border-sky-500');
        inputElement.classList.add('border-red-500', 'focus:border-red-500');
        errorElement.style.display = 'block';
        errorElement.textContent = errorMessage;
    }
}

/**
 * Actualiza la lista de requisitos de la contraseña.
 * @param {HTMLElement} element El elemento <li> de la lista.
 * @param {boolean} isValid Si la regla se cumple.
 */
function actualizarUIListaPassword(element, isValid) {
    if (!element) return;

    const icon = element.querySelector('i');
    element.className = 'validation-item ' + (isValid ? 'valid' : 'invalid');

    if (isValid) {
        icon.classList.remove('fa-times-circle');
        icon.classList.add('fa-check-circle');
    } else {
        icon.classList.remove('fa-check-circle');
        icon.classList.add('fa-times-circle');
    }
}