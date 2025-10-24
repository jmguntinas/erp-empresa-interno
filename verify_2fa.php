<?php
// verify_2fa.php - Página para verificar el código TOTP
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Requerir Composer Autoloader ---
require_once __DIR__ . '/vendor/autoload.php'; 
// --- Fin Autoloader ---

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // Para establish_session
require_once __DIR__ . '/i18n.php'; // Para ti()
require_once __DIR__ . '/common_helpers.php'; // Para h()

use PragmaRX\Google2FAQRCode\Google2FA;

$APP_LANG = i18n_get_lang();
$msg = get_flash_msg(); // Mostrar mensajes flash (ej. desde profile_2fa)
$msg_type = $_SESSION['flash']['type'] ?? 'info';
$err = ''; // Errores específicos de esta página

// Verificar que el usuario está en el paso intermedio de 2FA
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['2fa_user_id'];
$username = $_SESSION['2fa_username'] ?? 'Usuario'; // Recuperar username

// Instanciar Google2FA
$google2fa = new Google2FA();

// Procesar el envío del código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $err = ti($pdo, 'error.csrf', 'Error de validación (CSRF). Intente recargar la página.');
    } else {
        $code = trim($_POST['code'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            $err = ti($pdo, 'error.2fa_verify_formato_codigo', 'El código debe ser de 6 dígitos numéricos.');
        } else {
            try {
                // 1. Obtener el secreto del usuario desde la BD
                $stmt = $pdo->prepare("SELECT twofa_secret FROM global_users WHERE id = ? AND twofa_enabled = TRUE");
                $stmt->execute([$user_id]);
                $secret = $stmt->fetchColumn();

                if (!$secret) {
                    // Si no hay secreto o 2FA se desactivó mientras tanto, abortar
                    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_username'], $_SESSION['2fa_redirect_url']); // Limpiar todo
                    $err = ti($pdo, 'error.2fa_verify_setup_incompleto', 'Error: No se encontró la configuración 2FA activa para este usuario.');
                } else {
                    // 2. Verificar el código con la librería
                    // El tercer parámetro (window) permite una ventana de tiempo (ej. 1 = +/- 30s)
                    $isValid = $google2fa->verifyKey($secret, $code, 1); 

                    if ($isValid) {
                        // 3. Código VÁLIDO: Establecer la sesión final
                        establish_session($pdo, $user_id, $username); // Usar la función centralizada

                        // Redirigir al dashboard o URL original guardada
                        $redirect_url = $_SESSION['2fa_redirect_url'] ?? 'index.php';
                        unset($_SESSION['2fa_redirect_url']); // Limpiar URL guardada
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        // 4. Código INVÁLIDO
                        $err = ti($pdo, 'error.2fa_codigo_invalido', 'El código de verificación es incorrecto.');
                    }
                }
            } catch (Throwable $e) {
                error_log("Error verifying 2FA code for user $user_id: " . $e->getMessage());
                $err = ti($pdo, 'error.2fa_verify_error_inesperado', 'Ocurrió un error al verificar el código.');
            }
        }
    }
}

// $csrf_token está disponible globalmente por incluir auth.php (o se genera si falta)
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="<?= h($APP_LANG) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ti($pdo, 'ui.2fa.verify.titulo_pagina', 'Verificación Doble Factor') ?></title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
   <style>
    html, body { height: 100%; }
    body { display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; }
    .form-card { max-width: 400px; width: 100%; padding: 1rem; }
  </style>
</head>
<body>
  <div class="form-card card shadow-sm">
    <div class="card-body">
      <h4 class="card-title text-center mb-4"><?= ti($pdo, 'ui.2fa.verify.titulo_pagina', 'Verificación de Seguridad') ?></h4>
      <p class="text-center text-muted"><?= ti($pdo, 'ui.2fa.verify.info', 'Introduce el código de 6 dígitos de tu aplicación de autenticación (ej. Microsoft Authenticator).') ?></p>

      <?= $msg ? '<div class="alert alert-'.h($msg_type).'">'.h($msg).'</div>' : '' ?>
      <?php if ($err): ?>
        <div class="alert alert-danger py-2"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
        <div class="mb-3">
          <label for="code" class="form-label"><?= ti($pdo, 'ui.2fa.verify.label_codigo', 'Código de Verificación') ?></label>
          <input type="text" inputmode="numeric" pattern="[0-9]{6}" class="form-control" id="code" name="code" required autocomplete="off" maxlength="6" autofocus style="font-size: 1.2rem; text-align: center;">
        </div>
        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary"><?= ti($pdo, 'ui.2fa.verify.btn_verificar', 'Verificar e Iniciar Sesión') ?></button>
        </div>
         <div class="text-center mt-3">
            <?php // Ofrecer logout si el usuario se queda atascado ?>
            <a href="logout.php" class="small"><?= ti($pdo, 'ui.2fa.verify.link_cancelar', 'Cancelar e ir a Login') ?></a> 
         </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>