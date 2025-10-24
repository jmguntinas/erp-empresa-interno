<?php
// forgot_password.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common_helpers.php'; // Para h() y $csrf_token (si lo usas aquí)
require_once __DIR__ . '/mailer.php'; // Para app_send_mail()

$msg = '';
$msg_type = 'danger'; // Para Bootstrap alert class

// Generar token CSRF si no existe (importante para formularios públicos)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = 'Error de validación (CSRF). Intente recargar la página.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Por favor, introduce una dirección de email válida.';
        } else {
            try {
                // 1. Verificar si el usuario existe
                $stmtUser = $pdo->prepare("SELECT id FROM global_users WHERE email = ? AND is_active = 1");
                $stmtUser->execute([$email]);
                $user = $stmtUser->fetch();

                if ($user) {
                    // 2. Generar token seguro
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + 60 * 60); // Token válido por 1 hora

                    // 3. Borrar tokens antiguos para este email e insertar el nuevo
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                    $stmtInsert = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmtInsert->execute([$email, password_hash($token, PASSWORD_DEFAULT), $expires_at]); // Guardar hash del token

                    // 4. Crear enlace de reseteo
                    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

                    // 5. Enviar email (usa tu función de mailer.php)
                    $subject = "Recuperación de Contraseña";
                    $body = "Hola,\n\nHas solicitado restablecer tu contraseña.\n\n" .
                            "Haz clic en el siguiente enlace para continuar (válido por 1 hora):\n" .
                            $resetLink . "\n\n" .
                            "Si no solicitaste esto, ignora este mensaje.\n";

                    if (app_send_mail($email, $subject, $body)) {
                        $msg = 'Se han enviado instrucciones para restablecer tu contraseña a tu email.';
                        $msg_type = 'success';
                    } else {
                        $msg = 'Error al enviar el email. Inténtalo de nuevo más tarde.';
                    }
                } else {
                    // No revelar si el email existe o no por seguridad
                    $msg = 'Si tu email está registrado, recibirás instrucciones en breve.';
                    $msg_type = 'info'; // Usar info o success para no dar pistas
                }

            } catch (Throwable $e) {
                error_log("Error en forgot_password: " . $e->getMessage());
                $msg = 'Ocurrió un error inesperado. Inténtalo más tarde.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
   <style>
    html, body { height: 100%; }
    body { display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; }
    .form-card { max-width: 450px; width: 100%; padding: 1rem; }
  </style>
</head>
<body>
  <div class="form-card card shadow-sm">
    <div class="card-body">
      <h4 class="card-title text-center mb-4">Recuperar Contraseña</h4>

      <?php if ($msg): ?>
        <div class="alert alert-<?= h($msg_type) ?> py-2"><?= h($msg) ?></div>
      <?php endif; ?>

      <?php if ($msg_type !== 'success'): // No mostrar formulario si ya se envió ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <div class="mb-3">
          <label for="email" class="form-label">Tu dirección de email</label>
          <input type="email" class="form-control" id="email" name="email" required placeholder="Introduce tu email registrado" value="<?= h($_POST['email'] ?? '') ?>" autofocus>
        </div>
        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary">Enviar enlace de recuperación</button>
        </div>
        <div class="text-center mt-3">
            <a href="login.php">Volver a Iniciar Sesión</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>