<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

$company_id = (int)($_POST['company_id'] ?? 0);
if ($company_id > 0) {
  // --- MODIFICADO: Verificar en inv_companies ---
  // Quitado is_active si no existe en tu tabla inv_companies
  $st = $pdo->prepare("SELECT id FROM inv_companies WHERE id=?");
  // --- FIN MODIFICADO ---
  $st->execute([$company_id]);
  if ($st->fetchColumn()) {
    $_SESSION['active_company_id'] = $company_id;
  }
} elseif ($company_id === 0 || $company_id === -1) { // Permitir 0 (Todas) o -1 (General)
     $_SESSION['active_company_id'] = $company_id;
}


// Volver a la página anterior o a index
$ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $ref);
exit; // Asegurar que termina
?>