<?php
require_once __DIR__ . '/mailer.php';

$to = trim($_POST['to'] ?? (defined('ALERT_TO_EMAIL')?ALERT_TO_EMAIL:''));
$isHtml = isset($_POST['is_html']);
$sent = null; $error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!filter_var($to, FILTER_VALIDATE_EMAIL)){
    $error = 'Email no válido';
  } else {
    $subject = 'Prueba SMTP (sin Composer) - Gestor de Inventario';
    $body = $isHtml
      ? '<h3>Prueba SMTP</h3><p>Enviado el '.date('Y-m-d H:i:s').'</p>'
      : "Prueba SMTP\nEnviado el ".date('Y-m-d H:i:s');
    $ok = app_send_mail($to, $subject, $body, $isHtml);
    $sent = $ok;
    if(!$ok){ $error = 'No se pudo enviar. Revisa SMTP en config.php, autoloader y que los 3 archivos de PHPMailer estén en vendor/phpmailer/phpmailer/src.'; }
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prueba SMTP</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="p-4">
  <h4>Prueba de Email por SMTP (sin Composer)</h4>
  <?php if($sent===true): ?><div class="alert alert-success">¡Enviado correctamente a <strong><?= htmlspecialchars($to) ?></strong>!</div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="row g-2">
    <div class="col-md-6">
      <label class="form-label">Enviar a</label>
      <input class="form-control" name="to" value="<?= htmlspecialchars($to) ?>" placeholder="destino@tudominio.com">
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="is_html" id="is_html" <?= $isHtml?'checked':'' ?>>
        <label class="form-check-label" for="is_html">Enviar como HTML</label>
      </div>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Enviar prueba</button>
    </div>
  </form>
</body></html>
