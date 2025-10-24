<?php
// login.php - Página pública de acceso
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Si ya está logueado (sesión final establecida), redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php'); // O tu página principal
    exit;
}

require_once __DIR__ . '/db.php';   // conexión PDO
require_once __DIR__ . '/auth.php'; // attempt_login, establish_session, $csrf_token
require_once __DIR__ . '/common_helpers.php'; // Para h()
require_once __DIR__ . '/i18n.php'; // Para ti()

$APP_LANG = i18n_get_lang();
$err = ''; // Variable para mensajes de error

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF (usando el token global de auth.php)
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $err = ti($pdo, 'error.csrf', 'Error de validación (CSRF). Intente recargar la página.');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $err = ti($pdo, 'error.login.campos_vacios', 'Por favor, introduce usuario y contraseña.');
        } else {
            // Intentar loguear (devuelve user_id o false)
            $login_result = attempt_login($username, $password);

            if ($login_result !== false) {
                $user_id = $login_result; // ID del usuario autenticado

                // --- Lógica 2FA ---
                try {
                    $stmt2FA = $pdo->prepare("SELECT twofa_enabled, twofa_secret FROM global_users WHERE id = ?");
                    $stmt2FA->execute([$user_id]);
                    $user2FA = $stmt2FA->fetch(PDO::FETCH_ASSOC);

                    if ($user2FA && $user2FA['twofa_enabled'] && !empty($user2FA['twofa_secret'])) {
                        // 2FA activado: Guardar datos temporales y redirigir a verificación
                        $_SESSION['2fa_user_id'] = $user_id; // ID del usuario pendiente de 2FA
                        $_SESSION['2fa_username'] = $username; // Guardar username para rellenar sesión después
                        // Guardar también la URL de redirección original si existe
                        if (isset($_SESSION['redirect_url'])) {
                            $_SESSION['2fa_redirect_url'] = $_SESSION['redirect_url'];
                            unset($_SESSION['redirect_url']);
                        }
                        header('Location: verify_2fa.php');
                        exit;
                    } else {
                        // 2FA NO activado: Establecer sesión final y redirigir
                        establish_session($pdo, $user_id, $username); // Nueva función para centralizar esto

                        $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
                        unset($_SESSION['redirect_url']); // Limpiar URL guardada
                        header('Location: ' . $redirect_url);
                        exit;
                    }
                } catch (Throwable $e) {
                     error_log("Error checking 2FA status for user $user_id: " . $e->getMessage());
                     $err = ti($pdo, 'error.login.verificar_2fa', 'Error al verificar estado de 2FA.');
                }
                // --- FIN Lógica 2FA ---

            } else {
                $err = ti($pdo, 'error.login.credenciales_invalidas', 'Usuario o contraseña incorrectos.');
            }
        }
    }
}

// $csrf_token está disponible globalmente por incluir auth.php

?>
<!DOCTYPE html>
<html lang="<?= h($APP_LANG) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ti($pdo, 'ui.login.titulo', 'Iniciar Sesión - Gestor') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    html, body { height: 100%; }
    body { display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; }
    .login-card { max-width: 400px; width: 100%; padding: 1rem; }
    .brand { font-weight: 500; color: var(--bs-primary); letter-spacing:.3px; }
  </style>
</head>
<body>
  <div class="login-card card shadow-sm">
    <div class="card-body">
      <div class="text-center mb-4">
        <div class="brand h4 mb-1"><?= ti($pdo, 'app.nombre_corto', 'GESTOR') ?></div>
        <small class="text-muted"><?= ti($pdo, 'ui.login.subtitulo', 'Inicia sesión para continuar') ?></small>
      </div>

      <?php if ($err): ?>
        <div class="alert alert-danger py-2"><?= h($err) ?></div>
      <?php elseif (isset($_GET['loggedout'])): ?>
        <div class="alert alert-success py-2"><?= ti($pdo, 'ok.sesion_cerrada', 'Sesión cerrada correctamente.') ?></div>
      <?php elseif (isset($_GET['reset_success'])): // Mensaje opcional desde reset_password.php ?>
         <div class="alert alert-success py-2"><?= ti($pdo, 'ok.pass_actualizada', '¡Contraseña actualizada con éxito! Ya puedes iniciar sesión.') ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') // Usar el token global ?>">
        <div class="mb-3">
          <label for="username" class="form-label"><?= ti($pdo, 'ui.login.label_usuario', 'Usuario') ?></label>
          <input type="text" class="form-control" id="username" name="username" required placeholder="<?= ti($pdo, 'ui.login.placeholder_usuario', 'Introduce tu usuario') ?>" value="<?= h($_POST['username'] ?? '') ?>" autofocus>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label"><?= ti($pdo, 'ui.login.label_pass', 'Contraseña') ?></label>
          <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
           <div class="text-end mt-1">
               <a href="forgot_password.php" class="small"><?= ti($pdo, 'ui.login.link_olvidaste', '¿Olvidaste tu contraseña?') ?></a>
           </div>
        </div>
        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i> <?= ti($pdo, 'ui.login.btn_acceder', 'Acceder') ?></button>
        </div>
      </form>

    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>