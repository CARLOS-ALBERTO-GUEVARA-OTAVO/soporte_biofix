<?php
session_start();
require '../bd/db.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id_usuario'])) {
    $id_usuario = (int)$_GET['id_usuario'];

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
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Solicitud incorrecta.'];
}

header("Location: dashboard.php");
exit;
?>