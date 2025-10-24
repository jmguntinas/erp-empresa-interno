<?php
// auth.php - Funciones de autenticación y sesión

// Iniciar la sesión si no está ya iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Generación de Token CSRF de Sesión ---
// Generar y almacenar el token CSRF en la sesión si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Hacemos que el token esté disponible globalmente
$csrf_token = $_SESSION['csrf_token'];
// --- Fin Token CSRF ---

/**
 * Función require_login()
 * Comprueba si el usuario está logueado (tiene 'user_id' en sesión).
 * Si no, guarda la URL actual y redirige a login.php
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; 
        header('Location: login.php');
        exit;
    }
}

/**
 * Función attempt_login()
 * Intenta validar usuario/contraseña contra 'global_users'.
 * Devuelve el ID del usuario si es exitoso, false si no.
 */
function attempt_login($username, $password) {
    global $pdo; // Obtener la conexión $pdo
    
    // Asegurarse de que $pdo está disponible (si db.php no fue incluido antes)
    if (!$pdo && file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    }
    if (!$pdo) return false; // No se pudo cargar la conexión

    try {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM global_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar usuario y hash de contraseña
        if ($user && password_verify($password, $user['password_hash'])) {
            // Éxito: Devolver el ID del usuario
            // La sesión final se establecerá después de la posible verificación 2FA
            return $user['id']; 
        } else {
            // Fallo
            return false;
        }

    } catch (PDOException $e) {
        error_log("Error en attempt_login: " . $e->getMessage()); 
        return false;
    }
}

/**
 * Función (NUEVA) para establecer la sesión final del usuario
 * Se llama después de attempt_login (si no hay 2FA) o después de verify_2fa
 */
function establish_session(PDO $pdo, int $user_id, string $username): void {
     session_regenerate_id(true); // Prevenir fijación de sesión
     $_SESSION['user_id'] = $user_id;
     $_SESSION['username'] = $username;

     // Cargar roles
     try {
        $stmt_roles = $pdo->prepare("SELECT r.role_name FROM global_roles r JOIN global_user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt_roles->execute([$user_id]);
        $_SESSION['roles'] = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
     } catch (Throwable $e) {
         error_log("Error loading roles for user $user_id: " . $e->getMessage());
         $_SESSION['roles'] = []; // Asignar array vacío si fallan los roles
     }
     
     // Limpiar token CSRF antiguo y variables temporales 2FA
     unset($_SESSION['csrf_token'], $_SESSION['2fa_user_id'], $_SESSION['2fa_username']);
}


/**
 * Función has_role()
 * Verifica si el usuario logueado tiene al menos uno de los roles requeridos.
 */
function has_role($roles_requeridos = []) {
    if (!isset($_SESSION['roles']) || !is_array($_SESSION['roles'])) {
        return false; // No hay roles definidos o no es un array
    }
    
    // Si no se requieren roles específicos, permitir acceso si está logueado
    if (empty($roles_requeridos)) {
        return isset($_SESSION['user_id']); 
    }
    
    if (is_string($roles_requeridos)) {
        $roles_requeridos = [$roles_requeridos]; // Convertir a array
    }
    
    // Rol 'Admin General' (o como lo llames) tiene acceso a todo
    if (in_array('Admin General', $_SESSION['roles'])) {
        return true;
    }

    // Comprobar si hay intersección entre roles requeridos y roles del usuario
    $interseccion = array_intersect($roles_requeridos, $_SESSION['roles']);
    return !empty($interseccion);
}

/**
 * Función require_role()
 * Verifica si el usuario tiene los roles requeridos, si no, muestra error y termina.
 */
function require_role($roles_requeridos = []) {
    if (!has_role($roles_requeridos)) {
        http_response_code(403); // Forbidden
        // Incluir header/footer si quieres una página de error más bonita
        // include __DIR__ . '/partials/header.php'; 
        echo '<div class="container mt-5"><div class="alert alert-danger">Acceso denegado. No tienes los permisos necesarios.</div></div>';
        // include __DIR__ . '/partials/footer.php';
        exit;
    }
}

/**
 * Función logout()
 * Cierra la sesión del usuario.
 */
function logout() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = array(); // Limpiar variables de sesión

    // Borrar cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy(); // Destruir la sesión
}

?>