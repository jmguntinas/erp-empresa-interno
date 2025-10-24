<?php
// departamentos.php — CRUD básico
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

// Compatibilidad: obtener $pdo si tu proyecto usa db() en lugar de variable global
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('db')) { $pdo = db(); }
}

// Helpers (con guards para evitar redeclaración)
if (!function_exists('start_session')) { function start_session(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); } }
if (!function_exists('csrf_token')) { function csrf_token(){ start_session(); if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ start_session(); $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']); if(!$ok){ header('Location: '.basename($_SERVER['PHP_SELF']).'?e=csrf'); exit; } } }
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }

// --- MODIFICADO: Definir tablas ---
$table_dept = 'inv_departments';
$table_comp = 'inv_companies';
// --- FIN MODIFICADO ---

$msg = '';

// Procesar acciones POST (crear/eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf_or_redirect();
  $action = $_POST['action'] ?? '';
  $id     = (int)($_POST['id'] ?? 0);
  $name   = trim($_POST['name'] ?? '');
  $code   = trim($_POST['code'] ?? '');
  $comp_id = (int)($_POST['company_id'] ?? 0);

  try {
    if ($action === 'create' && $name !== '' && $comp_id > 0) {
      // --- MODIFICADO: Insertar en 'inv_departments' ---
      $st = $pdo->prepare("INSERT INTO $table_dept (name, code, company_id) VALUES (?, ?, ?)");
      // --- FIN MODIFICADO ---
      $st->execute([$name, $code ?: null, $comp_id]);
      $msg = '<div class="alert alert-success">Departamento creado.</div>';
    
    } elseif ($action === 'delete' && $id > 0) {
      // --- MODIFICADO: Eliminar de 'inv_departments' ---
      $st = $pdo->prepare("DELETE FROM $table_dept WHERE id = ?");
      // --- FIN MODIFICADO ---
      $st->execute([$id]);
      $msg = '<div class="alert alert-info">Departamento eliminado.</div>';
    }
  } catch (PDOException $e) {
    $msg = '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>';
  }
}

// Filtro por compañía
$filter_company_id = (int)($_GET['company_id'] ?? 0);
$where = ' WHERE 1=1 ';
$params = [];
if ($filter_company_id > 0) {
  $where .= " AND d.company_id = ? ";
  $params[] = $filter_company_id;
}

// Cargar listas
// --- MODIFICADO: Leer de 'inv_companies' y 'inv_departments' ---
$companies = $pdo->query("SELECT id, name FROM $table_comp ORDER BY name")->fetchAll();
$st_list = $pdo->prepare("
  SELECT d.*, c.name AS company
  FROM $table_dept d
  JOIN $table_comp c ON c.id = d.company_id
  $where
  ORDER BY c.name, d.name
");
$st_list->execute($params);
$list = $st_list->fetchAll();
// --- FIN MODIFICADO ---

$pageTitle = 'Departamentos';
require __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3">Departamentos</h4>
<?= $msg ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row gx-2 gy-2 align-items-center">
      <div class="col-md-4">
        <label for="f_comp" class="visually-hidden">Empresa</label>
        <select name="company_id" id="f_comp" class="form-select" onchange="this.form.submit()">
          <option value="">-- Filtrar por Empresa --</option>
          <?php foreach($companies as $c): ?>
            <option value="<?= h($c['id']) ?>" <?= $c['id']==$filter_company_id ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-outline-secondary">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="card-title">Nuevo Departamento</h5>
    <form method="post" class="row gx-2 gy-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input name="name" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Código (opc)</label>
        <input name="code" class="form-control form-control-sm">
      </div>
      <div class="col-md-4">
        <label class="form-label">Empresa</label>
        <select name="company_id" class="form-select form-select-sm" required>
          <option value="">-- Selecciona --</option>
          <?php foreach($companies as $c): ?>
            <option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Crear</button>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
<table class="table table-sm table-striped align-middle">
  <thead class="table-light">
    <tr>
      <th>Empresa</th>
      <th>Nombre</th>
      <th>Código</th>
      <th class="text-end">Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach($list as $d): ?>
    <tr>
      <td><?= h($d['company']) ?></td>
      <td><?= h($d['name']) ?></td>
      <td><?= h($d['code'] ?? '-') ?></td>
      <td class="text-end">
        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar el departamento \"<?= h($d['name']) ?>\"?');">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= h($d['id']) ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if (empty($list)): ?>
    <tr><td colspan="4" class="text-center text-muted">No hay departamentos que coincidan con el filtro.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>