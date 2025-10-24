<?php
// reset_password.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common_helpers.php'; // Para h()

$msg = '';
$msg_type = 'danger';
$token_valid = false;
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Generar token CSRF para el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


if (empty($token) || empty($email)) {
    $msg = 'Enlace inválido o incompleto.';
} else {
    try {
        // 1. Buscar el token en la BD (buscamos por email, luego comparamos hash)
        $stmt = $pdo->prepare("SELECT token, expires_at FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $reset_data = $stmt->fetch();

        // 2. Verificar si existe, si el token coincide y si no ha expirado
        if ($reset_data && password_verify($token, $reset_data['token']) && time() < strtotime($reset_data['expires_at'])) {
            $token_valid = true;
        } else {
             $msg = 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.';
        }

    } catch (Throwable $e) {
        error_log("Error validating reset token: " . $e->getMessage());
        $msg = 'Ocurrió un error al validar el enlace.';
    }
}

// Procesar el envío del formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    // Validar CSRF
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = 'Error de validación (CSRF). Intente recargar la página.';
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password) || strlen($password) < 8) { // Añadir validación de fortaleza si quieres
            $msg = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password_confirm) {
            $msg = 'Las contraseñas no coinciden.';
        } else {
            try {
                // 1. Hashear la nueva contraseña
                $new_password_hash = password_hash($password, PASSWORD_DEFAULT);

                // 2. Actualizar la contraseña del usuario en global_users
                $stmtUpdate = $pdo->prepare("UPDATE global_users SET password_hash = ? WHERE email = ?");
                $stmtUpdate->execute([$new_password_hash, $email]);

                // 3. Invalidar/Borrar el token de reseteo
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // 4. Mensaje de éxito y redirigir a login
                set_flash('success', '¡Contraseña actualizada con éxito! Ya puedes iniciar sesión.'); // Usar flash
                header('Location: login.php');
                exit;

            } catch (Throwable $e) {
                error_log("Error resetting password: " . $e->getMessage());
                $msg = 'Ocurrió un error al actualizar la contraseña.';
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
    <title>Restablecer Contraseña</title>
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
            <h4 class="card-title text-center mb-4">Restablecer Contraseña</h4>

            <?php if ($msg): ?>
                <div class="alert alert-<?= h($msg_type) ?> py-2"><?= h($msg) ?></div>
            <?php endif; ?>

            <?php if ($token_valid): // Solo mostrar formulario si el token es válido ?>
                <form method="post">
                     <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                     <?php // Pasar token y email de nuevo por si se pierde el GET en el POST ?>
                     <input type="hidden" name="token" value="<?= h($token) ?>">
                     <input type="hidden" name="email" value="<?= h($email) ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                     <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Guardar Nueva Contraseña</button>
                    </div>
                </form>
            <?php else: ?>
                 <div class="text-center mt-3">
                    <a href="forgot_password.php">Solicitar un nuevo enlace</a>
                 </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>