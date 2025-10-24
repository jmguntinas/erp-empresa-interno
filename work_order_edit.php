<?php
// work_order_edit.php — editar OT (Adaptado para inv_, hr_, global_)
require_once __DIR__ . '/db.php';
// --- MODIFICADO: Usar auth.php unificado ---
require_once __DIR__ . '/auth.php'; require_login(); require_role(['Admin General', 'Admin Inventario']); // Proteger
// --- FIN MODIFICADO ---

// Helpers mínimos (adaptados)
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']); if(!$ok){ header('Location: '.basename($_SERVER['PHP_SELF']).'?e=csrf'); exit; } } }
if (!function_exists('set_flash')) { function set_flash($t,$m){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $_SESSION['flash']=['type'=>$t,'msg'=>$m]; } }
if (!function_exists('get_flash_msg')) { function get_flash_msg($clear=true){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $m=$_SESSION['flash']??null; if($clear)unset($_SESSION['flash']); return $m?('<div class="alert alert-'.$m['type'].'">'.$m['msg'].'</div>'):''; } }

$id = (int)($_GET['id'] ?? 0);
$po = null; $items = []; $msg = get_flash_msg();

if ($id > 0) {
    // --- MODIFICADO: Leer OT de inv_purchase_orders (sin supplier_id) ---
    $st = $pdo->prepare("SELECT * FROM inv_purchase_orders WHERE id=? AND supplier_id IS NULL");
    $st->execute([$id]);
    $po = $st->fetch();
    if (!$po) { header('Location: work_orders.php'); exit; } // No encontrado o no es OT
    // Leer líneas
    $st_items = $pdo->prepare("SELECT * FROM inv_purchase_order_lines WHERE order_id=?");
    $st_items->execute([$id]);
    $items = $st_items->fetchAll();
    // --- FIN MODIFICADO ---
}

// Cargar listas para selects
// --- MODIFICADO: Usar tablas inv_*, hr_empleados ---
$companies = $pdo->query("SELECT id, name FROM inv_companies ORDER BY name")->fetchAll();
$clients = $pdo->query("SELECT id, name FROM inv_clients ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM inv_projects ORDER BY name")->fetchAll();
// Empleados de RRHH (nombre completo)
$employees = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellidos) as name FROM hr_empleados ORDER BY apellidos, nombre")->fetchAll();
$warehouses = $pdo->query("SELECT id, name FROM inv_warehouses ORDER BY name")->fetchAll();
// --- FIN MODIFICADO ---

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_or_redirect();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $po_number = trim($_POST['po_number'] ?? '');
        $company_id = (int)($_POST['company_id'] ?? 1); // Asumir 1 si no viene
        $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $assigned_employee_id = (int)($_POST['assigned_employee_id'] ?? 0) ?: null;
        $requester_employee_id = (int)($_POST['requester_employee_id'] ?? 0) ?: null;
        $warehouse_id = (int)($_POST['warehouse_id'] ?? 0) ?: null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? ($id ? $po['status'] : 'pending'); // Mantener estado o 'pending' por defecto
        $order_date = $_POST['order_date'] ?: date('Y-m-d'); // Fecha actual por defecto
        $due_date = $_POST['due_date'] ?: null;
        $user_id = $_SESSION['user_id'] ?? null; // Usuario global

        try {
            if ($id > 0) { // Update
                // --- MODIFICADO: Update inv_purchase_orders ---
                // Añadidos campos que faltaban y renombrado created_by_user_id
                // Quitados po_number, company_id, description, due_date si no existen en la tabla
                $sql = "UPDATE inv_purchase_orders SET client_id=?, project_id=?, assigned_employee_id=?, requester_employee_id=?, warehouse_id=?, status=?, order_date=?, expected_date=?, notes=? WHERE id=? AND supplier_id IS NULL";
                $pdo->prepare($sql)->execute([
                    $client_id, $project_id, $assigned_employee_id, $requester_employee_id,
                    $warehouse_id, $status, $order_date, $due_date, $description, $id
                ]);
                // --- FIN MODIFICADO ---
                set_flash('success', "Orden de Trabajo #{$id} actualizada.");
            } else { // Insert
                // --- MODIFICADO: Insert en inv_purchase_orders ---
                // Faltaba created_by_user_id, notes
                // Quitados po_number, company_id, description, due_date si no existen
                $sql = "INSERT INTO inv_purchase_orders (client_id, project_id, assigned_employee_id, requester_employee_id, warehouse_id, status, order_date, expected_date, created_by_user_id, supplier_id, notes) VALUES (?,?,?,?,?,?,?,?,?, NULL, ?)";
                $pdo->prepare($sql)->execute([
                    $client_id, $project_id, $assigned_employee_id, $requester_employee_id,
                    $warehouse_id, $status, $order_date, $due_date, $user_id, $description
                ]);
                $id = (int)$pdo->lastInsertId();
                // --- FIN MODIFICADO ---
                set_flash('success', "Orden de Trabajo #{$id} creada.");
            }
            header("Location: work_order_edit.php?id=" . $id); // Recargar para ver cambios
            exit;
        } catch (Throwable $e) { $msg = '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>'; }
    }
}

$pageTitle = $id ? "Editar OT #{$id}" : "Nueva Orden de Trabajo";
include __DIR__ . '/partials/header.php';
?>
<h4><i class="bi bi-tools"></i> <?= h($pageTitle) ?></h4>
<?= $msg ?>
<form method="post" class="row g-3">
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <div class="col-md-4"><label class="form-label">Empresa</label>
      <select class="form-select form-select-sm" name="company_id">
          <?php foreach($companies as $c): ?><option value="<?= h($c['id']) ?>" <?= ($po['company_id']??1)==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-md-4"><label class="form-label">Cliente</label>
      <select class="form-select form-select-sm" name="client_id">
          <option value="">—</option>
          <?php foreach($clients as $c): ?><option value="<?= h($c['id']) ?>" <?= ($po['client_id']??'')==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-md-4"><label class="form-label">Proyecto</label>
      <select class="form-select form-select-sm" name="project_id">
          <option value="">—</option>
          <?php foreach($projects as $p): ?><option value="<?= h($p['id']) ?>" <?= ($po['project_id']??'')==$p['id']?'selected':'' ?>><?= h($p['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-md-4"><label class="form-label">Asignado a</label>
      <select class="form-select form-select-sm" name="assigned_employee_id">
          <option value="">—</option>
          <?php foreach($employees as $e): ?><option value="<?= h($e['id']) ?>" <?= ($po['assigned_employee_id']??'')==$e['id']?'selected':'' ?>><?= h($e['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-md-4"><label class="form-label">Solicitante</label>
      <select class="form-select form-select-sm" name="requester_employee_id">
          <option value="">—</option>
          <?php foreach($employees as $e): ?><option value="<?= h($e['id']) ?>" <?= ($po['requester_employee_id']??'')==$e['id']?'selected':'' ?>><?= h($e['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-md-4"><label class="form-label">Almacén</label>
      <select class="form-select form-select-sm" name="warehouse_id">
          <option value="">—</option>
          <?php foreach($warehouses as $w): ?><option value="<?= h($w['id']) ?>" <?= ($po['warehouse_id']??'')==$w['id']?'selected':'' ?>><?= h($w['name']) ?></option><?php endforeach; ?>
      </select>
  </div>
  <div class="col-12"><label class="form-label">Descripción / Notas</label>
      <textarea class="form-control form-control-sm" name="description" rows="3"><?= h($po['notes'] ?? '') // Usar notes ?></textarea>
  </div>
  <div class="col-md-4"><label class="form-label">Estado</label>
      <select class="form-select form-select-sm" name="status">
          <option value="pending" <?= ($po['status']??'pending')=='pending'?'selected':'' ?>>Pendiente</option>
          <option value="in_progress" <?= ($po['status']??'')=='in_progress'?'selected':'' ?>>En Progreso</option>
          <option value="done" <?= ($po['status']??'')=='done'?'selected':'' ?>>Completada</option>
          <option value="cancelled" <?= ($po['status']??'')=='cancelled'?'selected':'' ?>>Cancelada</option>
      </select>
  </div>
   <div class="col-md-4"><label class="form-label">Fecha Orden</label>
      <input type="date" class="form-control form-control-sm" name="order_date" value="<?= h($po['order_date'] ?? date('Y-m-d')) ?>">
  </div>
  <div class="col-md-4"><label class="form-label">Fecha Límite</label>
      <input type="date" class="form-control form-control-sm" name="due_date" value="<?= h($po['expected_date'] ?? '') // Usar expected_date ?>">
  </div>
  <div class="col-12 text-end">
    <a href="work_orders.php" class="btn btn-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar Orden Trabajo</button>
  </div>
</form>
<?php
// Incluir listado/edición de líneas si la OT ya existe
if ($id > 0) {
    // Aquí iría la lógica para mostrar/editar las líneas (items)
    // similar a como se hace en pedido_edit.php, pero adaptada
    // a la tabla inv_purchase_order_lines y usando $id como order_id.
    echo '<div class="card mt-4"><div class="card-header">Líneas de la Orden</div><div class="card-body">';
    echo 'Funcionalidad de líneas no portada en este ejemplo.';
    echo '</div></div>';
}
?>
<?php include __DIR__ . '/partials/footer.php'; ?>