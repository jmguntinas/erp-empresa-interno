<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/bootstrap_tables.php';

require_once __DIR__ . '/../i18n.php';
i18n_set_lang_from_request(); // permite cambiar con ?lang=es o ?lang=en
$APP_LANG = i18n_get_lang();

// Cargar empresas para el combo
$companies = [];
try {
  // --- MODIFICADO ---
  // Apunta a la nueva tabla 'inv_companies'
  $stmt = $pdo->query("SELECT id, name FROM inv_companies ORDER BY name");
  // --- FIN MODIFICADO ---
  $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $companies = [];
}

$activeCompanyId = isset($_SESSION['active_company_id']) ? (int)$_SESSION['active_company_id'] : 0;
$currentUrl = $_SERVER['REQUEST_URI'] ?? 'index.php';
$csrfToken = function_exists('csrf_token') ? csrf_token() : null;
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Inventario') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.6/dist/bootstrap-table.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
  /* Fix layout */
  .layout { display: flex; min-height: 100vh; }
  .layout-sidebar { min-width: 280px; max-width: 280px; }
  .layout-main { flex-grow: 1; }
  .nav-link.active { font-weight: 500; }
</style>
</head>
<body class="bg-light-subtle">

<header class="navbar navbar-expand-lg bg-light border-bottom sticky-top p-3">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand">
      <i class="bi bi-box-seam-fill me-2"></i>
      <strong><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Gestor de Inventario') ?></strong>
	  <li class="nav-item dropdown">
  <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
    <?= strtoupper($APP_LANG) ?>
  </a>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><a class="dropdown-item" href="?lang=es">Español (ES)</a></li>
    <li><a class="dropdown-item" href="?lang=en">English (EN)</a></li>
  </ul>
</li>

    </a>

    <form action="buscar.php" method="get" class="ms-auto d-flex" role="search">
      <input class="form-control form-control-sm" name="q" placeholder="Buscar (productos, proveedores, ...)" style="min-width:260px">
      <button class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-search"></i><span> Buscar</span></button>
    </form>

    <div class="ms-3">
      <i class="bi bi-person-circle me-1"></i>
      <span class="small">Hola, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></span>
      <a class="btn btn-sm btn-outline-danger ms-2" href="logout.php" title="Salir"><i class="bi bi-box-arrow-right"></i><span> Salir</span></a>
    </div>
  </div>
</header>

<div class="layout">
  <?php
  include_once __DIR__ . '/sidebar.php'; 
  ?>
  <main class="layout-main p-4">