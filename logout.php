<?php
// Iniciar la sesión para poder destruirla
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir a la página de login
header('Location: login.php');
exit;
?>