<?php
// set_company.php — guarda la empresa activa en sesión y redirige donde estabas (Adaptado)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/db.php';

// Si tienes check_csrf y csrf_token, actívalo:
if (function_exists('check_csrf') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  try { check_csrf(); } catch (Throwable $e) { /* si falla, puedes decidir bloquear */ }
}

$active = isset($_POST['active_company_id']) ? (int)$_POST['active_company_id'] : 0;
$return = isset($_POST['return']) ? $_POST['return'] : 'index.php';

/**
 * Valores válidos:
 * 0 => Todas las empresas (no aplicar filtro)
 * >0 => ID existente en inv_companies
 */
$valid = false;

if ($active === 0) { // Permitir "Todas"
    $valid = true;
} elseif ($active > 0) {
  try {
    // --- MODIFICADO: Verificar en inv_companies ---
    $stm = $pdo->prepare("SELECT COUNT(*) FROM inv_companies WHERE id = ?");
    // --- FIN MODIFICADO ---
    $stm->execute([$active]);
    $valid = ((int)$stm->fetchColumn() > 0);
  } catch (Throwable $e) {
    $valid = false;
  }
}

if ($valid) {
  $_SESSION['active_company_id'] = $active;
} else {
  // Si no es válido, poner "Todas" por defecto
  $_SESSION['active_company_id'] = 0;
}

// Limpiar la URL de retorno para evitar XSS
$return = filter_var($return, FILTER_SANITIZE_URL);
// Redirección segura
if (substr($return, 0, 1) === '/' || filter_var($return, FILTER_VALIDATE_URL)) {
    // Evitar redirección a dominios externos si no empieza por /
     if (substr($return, 0, 1) !== '/' && parse_url($return, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
         $return = 'index.php';
     }
} else {
    $return = 'index.php'; // Fallback
}


header('Location: ' . $return);
exit; // Asegurar que termina
?>