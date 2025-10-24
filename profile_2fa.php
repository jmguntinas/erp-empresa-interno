<?php
// profile_2fa.php - Página para que el usuario gestione su 2FA
require_once __DIR__ . '/auth.php'; require_login(); 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php'; // Para ti()
require_once __DIR__ . '/common_helpers.php'; // Para h(), set_flash(), get_flash_msg()
// --- Cargar Autoloader de Composer ---
require_once __DIR__ . '/vendor/autoload.php'; 
// --- Fin Autoloader ---

use PragmaRX\Google2FAQRCode\Google2FA; // Usar la librería
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$APP_LANG = i18n_get_lang();

$user_id = $_SESSION['user_id'];
$msg = get_flash_msg();
$msg_type = $_SESSION['flash']['type'] ?? 'info'; 

$twofa_enabled = false;
$qrCodeSvg = null; // Cambiado de qrCodeUrl a qrCodeSvg
$secretKey = null;

$google2fa = new Google2FA();

try {
    $stmtUser = $pdo->prepare("SELECT username, email, twofa_secret, twofa_enabled FROM global_users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception(ti($pdo, 'error.usuario_no_encontrado', "Usuario no encontrado."));

    $twofa_enabled = (bool)$user['twofa_enabled'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
             throw new Exception(ti($pdo, 'error.csrf', 'Error de validación (CSRF).'));
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'generate_secret' && !$twofa_enabled) {
            $secretKey = $google2fa->generateSecretKey();
            $_SESSION['2fa_temp_secret'] = $secretKey;
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                ti($pdo, 'app.nombre', 'Gestor Inventario'), // Nombre de la aplicación
                $user['email'] ?? $user['username'], // Identificador del usuario
                $secretKey
            );
             // Generar SVG directamente
             $writer = new Writer( new ImageRenderer( new RendererStyle(200), new SvgImageBackEnd() ) );
             $qrCodeSvg = $writer->writeString($qrCodeUrl); // Guardar SVG en variable

            $msg = ti($pdo, 'info.2fa_escanear_verificar', 'Escanea el código QR con tu app (ej. Microsoft Authenticator) y verifica introduciendo un código.');
            $msg_type = 'info';

        } elseif ($action === 'verify_and_enable' && isset($_SESSION['2fa_temp_secret'])) {
            $secretKey = $_SESSION['2fa_temp_secret']; 
            $verificationCode = trim($_POST['verification_code'] ?? '');
            if (empty($verificationCode)) throw new Exception(ti($pdo, 'error.2fa_codigo_requerido', 'Debes introducir el código de verificación.'));

            $isValid = $google2fa->verifyKey($secretKey, $verificationCode);

            if ($isValid) {
                $stmtEnable = $pdo->prepare("UPDATE global_users SET twofa_secret = ?, twofa_enabled = TRUE WHERE id = ?");
                $stmtEnable->execute([$secretKey, $user_id]);
                unset($_SESSION['2fa_temp_secret']);
                $twofa_enabled = true; 
                set_flash('success', ti($pdo, 'ok.2fa_activado', '¡Doble autenticación activada correctamente!'));
                header('Location: profile_2fa.php'); 
                exit;
            } else {
                $msg = ti($pdo, 'error.2fa_codigo_invalido', 'El código de verificación es incorrecto. Inténtalo de nuevo.');
                $msg_type = 'danger';
                 // Regenerar SVG para la vista
                 $qrCodeUrl = $google2fa->getQRCodeUrl(ti($pdo, 'app.nombre', 'Gestor Inventario'), $user['email'] ?? $user['username'], $secretKey);
                 $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
                 $qrCodeSvg = $writer->writeString($qrCodeUrl);
            }
        } elseif ($action === 'disable_2fa' && $twofa_enabled) {
            $currentCode = trim($_POST['current_code'] ?? '');
            if (empty($currentCode)) throw new Exception(ti($pdo, 'error.2fa_codigo_actual_requerido', 'Necesitas el código actual de tu app para desactivar 2FA.'));

             if ($google2fa->verifyKey($user['twofa_secret'], $currentCode)) {
                 $stmtDisable = $pdo->prepare("UPDATE global_users SET twofa_secret = NULL, twofa_enabled = FALSE WHERE id = ?");
                 $stmtDisable->execute([$user_id]);
                 unset($_SESSION['2fa_temp_secret']); 
                 $twofa_enabled = false; 
                 set_flash('success', ti($pdo, 'ok.2fa_desactivado', 'Doble autenticación desactivada.'));
                 header('Location: profile_2fa.php');
                 exit;
             } else {
                  throw new Exception(ti($pdo, 'error.2fa_codigo_invalido_desactivar', 'El código actual es incorrecto. No se pudo desactivar 2FA.'));
             }
        }
    } // Fin POST
    elseif (isset($_SESSION['2fa_temp_secret'])) { // Si no es POST pero hay secreto temporal (error previo)
         $secretKey = $_SESSION['2fa_temp_secret'];
         $qrCodeUrl = $google2fa->getQRCodeUrl(ti($pdo, 'app.nombre', 'Gestor Inventario'), $user['email'] ?? $user['username'], $secretKey);
         $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
         $qrCodeSvg = $writer->writeString($qrCodeUrl);
         if (empty($msg)) { 
            $msg = ti($pdo, 'info.2fa_escanear_verificar', 'Escanea el código QR con tu app (ej. Microsoft Authenticator) y verifica introduciendo un código.');
            $msg_type = 'info';
         }
    }
} catch (Throwable $e) {
    $msg = ti($pdo, 'error.generico', 'Error: ') . h($e->getMessage());
    $msg_type = 'danger';
    if (isset($_POST['action']) && $_POST['action'] === 'verify_and_enable') {
        unset($_SESSION['2fa_temp_secret']);
    }
}

$pageTitle = ti($pdo, 'ui.2fa.titulo_pagina', 'Configuración Doble Autenticación (2FA)');
// Asumimos que header.php define $csrf_token globalmente al incluir auth.php
include __DIR__ . '/partials/header.php'; 
?>

<div class="container mt-4">
    <h4><i class="bi bi-shield-lock me-2"></i> <?= $pageTitle ?></h4>
    <?= $msg ? '<div class="alert alert-'.h($msg_type).'">'.h($msg).'</div>' : '' ?>
    <div class="card">
        <div class="card-body">
            <?php if ($twofa_enabled): ?>
                <h5 class="card-title"><?= ti($pdo, 'ui.2fa.estado_activado', 'Estado: Activado') ?></h5>
                <p><?= ti($pdo, 'ui.2fa.info_activado', 'La doble autenticación está activa...') ?></p>
                <form method="post" onsubmit="return confirm('<?= ti($pdo, 'ui.2fa.confirmar_desactivar', '¿Seguro?') ?>')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                    <input type="hidden" name="action" value="disable_2fa">
                    <div class="mb-3">
                        <label for="current_code" class="form-label"><?= ti($pdo, 'ui.2fa.codigo_actual_desactivar', 'Código actual para desactivar:') ?></label>
                        <input type="text" inputmode="numeric" pattern="[0-9]{6}" class="form-control" id="current_code" name="current_code" required autocomplete="off" maxlength="6" style="width: 150px;">
                    </div>
                    <button type="submit" class="btn btn-danger"><?= ti($pdo, 'btn.desactivar_2fa', 'Desactivar 2FA') ?></button>
                </form>
            <?php elseif ($secretKey): // Paso 2: Verificar ?>
                <h5 class="card-title"><?= ti($pdo, 'ui.2fa.paso2_titulo', 'Paso 2: Verifica') ?></h5>
                <p><?= ti($pdo, 'ui.2fa.paso2_info', 'Escanea el QR...') ?></p>
                 <div class="row">
                    <div class="col-md-4 text-center">
                        <?= $qrCodeSvg ?? '<p class="text-danger">Error QR.</p>' ?>
                    </div>
                    <div class="col-md-8">
                        <p><?= ti($pdo, 'ui.2fa.o_manual', 'O introduce manualmente:') ?></p>
                        <code class="d-block bg-light p-2 rounded mb-3"><?= h($secretKey) ?></code>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                            <input type="hidden" name="action" value="verify_and_enable">
                            <div class="mb-3">
                                <label for="verification_code" class="form-label"><?= ti($pdo, 'ui.2fa.codigo_verificacion', 'Código (6 dígitos)') ?></label>
                                <input type="text" inputmode="numeric" pattern="[0-9]{6}" class="form-control" id="verification_code" name="verification_code" required autocomplete="off" maxlength="6" style="width: 150px;">
                            </div>
                            <button type="submit" class="btn btn-primary"><?= ti($pdo, 'btn.verificar_activar_2fa', 'Verificar y Activar') ?></button>
                            <a href="profile_2fa.php" class="btn btn-secondary"><?= ti($pdo, 'btn.cancelar', 'Cancelar') ?></a>
                        </form>
                    </div>
                </div>
            <?php else: // Estado inicial: Desactivado ?>
                <h5 class="card-title"><?= ti($pdo, 'ui.2fa.estado_desactivado', 'Estado: Desactivado') ?></h5>
                <p><?= ti($pdo, 'ui.2fa.info_desactivado', 'Añade seguridad extra...') ?></p>
                <form method="post">
                     <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                     <input type="hidden" name="action" value="generate_secret">
                     <button type="submit" class="btn btn-success"><?= ti($pdo, 'btn.activar_2fa', 'Activar 2FA') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>