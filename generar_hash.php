<?php
// Define la contraseña que quieres usar
$miContraseña = 'BiofixAdmin2025*'; 

// Genera el hash seguro
$hashGenerado = password_hash($miContraseña, PASSWORD_DEFAULT);

// Muestra el resultado para que lo puedas copiar
echo "Copia esta línea y pégala en la consulta SQL para la contraseña:<br><br>";
echo "<strong>" . $hashGenerado . "</strong>";
?>
