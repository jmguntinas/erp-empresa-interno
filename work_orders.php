<?php
// work_orders.php — listado + alta + borrar (Adaptado)
require_once __DIR__ . '/db.php';
// --- MODIFICADO: Usar auth.php unificado ---
require_once __DIR__ . '/auth.php'; require_login(); require_role(['Admin General', 'Admin Inventario', 'Gestor Almacén']); // Proteger
// --- FIN MODIFICADO ---

// Helpers mínimos (adaptados)
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']); if(!$ok){ header('Location: '.basename($_SERVER['PHP_SELF']).'?e=csrf'); exit; } } }
if (!function_exists('set_flash')) { function set_flash($t,$m){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $_SESSION['flash']=['type'=>$t,'msg'=>$m]; } }
if (!function_exists('get_flash_msg')) { function get_flash_msg($clear=true){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $m=$_SESSION['flash']??null; if($clear)unset($_SESSION['flash']); return $m?('<div class="alert alert-'.$m['type'].'">'.$m['msg'].'</div>'):''; } }

$msg = get_flash_msg();

// Borrar OT si se pide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf_or_redirect();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && has_role(['Admin General', 'Admin Inventario'])) { // Solo admins pueden borrar
        try {
            $pdo->beginTransaction();
            // --- MODIFICADO: Eliminar dependencias y la OT de inv_* ---
            $pdo->prepare("DELETE FROM inv_work_order_comments WHERE work_order_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM inv_purchase_order_lines WHERE order_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM inv_purchase_orders WHERE id=? AND supplier_id IS NULL")->execute([$id]);
            // --- FIN MODIFICADO ---
            $pdo->commit();
            set_flash('info', "Orden de Trabajo #{$id} eliminada.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('danger', 'Error al eliminar: ' . h($e->getMessage()));
        }
        header("Location: work_orders.php"); exit;
    }
}

// Listado de OTs (Pedidos sin proveedor)
// --- MODIFICADO: Consulta adaptada a inv_*, hr_empleados ---
$rows = $pdo->query("
    SELECT po.*,
           co.name AS company_name, cl.name AS client_name, pr.name AS project_name,
           assign.nombre AS assigned_name, assign.apellidos as assigned_surname
    FROM inv_purchase_orders po
    LEFT JOIN inv_companies co ON co.id = 1 -- Asumiendo company_id no está en PO
    LEFT JOIN inv_clients cl ON cl.id = po.client_id
    LEFT JOIN inv_projects pr ON pr.id = po.project_id
    LEFT JOIN hr_empleados assign ON assign.id = po.assigned_employee_id -- Unir con hr_empleados
    WHERE po.supplier_id IS NULL -- Condición para que sea OT
    ORDER BY po.id DESC
")->fetchAll();
// --- FIN MODIFICADO ---

$pageTitle = 'Órdenes de Trabajo';
include __DIR__ . '/partials/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-tools"></i> Órdenes de trabajo internas</h4>
  <a href="work_order_edit.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva OT</a>
</div>
<?= $msg ?>
<div class="table-responsive">
<table class="table table-sm table-striped align-middle">
<thead class="table-light">
  <tr>
      <th>ID</th>
      <th>Empresa</th>
      <th>Cliente</th>
      <th>Proyecto</th>
      <th>Asignado</th>
      <th>Fecha Orden</th>
      <th>Fecha Límite</th>
      <th>Estado</th>
      <th class="text-end">Acciones</th>
  </tr>
</thead>
<tbody>
<?php foreach($rows as $po): ?>
  <tr>
    <td><?= h($po['id']) ?></td>
    <td><?= h($po['company_name'] ?? '-') ?></td>
    <td><?= h($po['client_name'] ?? '-') ?></td>
    <td><?= h($po['project_name'] ?? '-') ?></td>
    <td><?= h(($po['assigned_name'] ?? '').' '.($po['assigned_surname'] ?? '-')) ?></td>
    <td><?= h($po['order_date']) ?></td>
    <td><?= h($po['expected_date'] ?? '-') // Usar expected_date ?></td>
    <td><?= h($po['status']) ?></td>
    <td class="text-end">
      <a class="btn btn-sm btn-outline-primary" href="work_order_view.php?id=<?= h($po['id']) ?>" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>
      <a class="btn btn-sm btn-outline-secondary" href="work_order_edit.php?id=<?= h($po['id']) ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
      <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar OT #<?= h($po['id']) ?>?');">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= h($po['id']) ?>">
        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
      </form>
    </td>
  </tr>
<?php endforeach; if (empty($rows)): ?>
  <tr><td colspan="9" class="text-center text-muted">No hay órdenes de trabajo.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>