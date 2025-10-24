<?php
// work_order_view.php — vista de empleado: aceptar y comentar (Adaptado)
require_once __DIR__ . '/db.php';
// --- MODIFICADO: Usar auth.php unificado ---
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/enterprise_helper.php'; // Usa la versión modificada
// --- FIN MODIFICADO ---

// Helpers
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']); if(!$ok){ header('Location: '.basename($_SERVER['PHP_SELF']).'?e=csrf'); exit; } } }
if (!function_exists('set_flash')) { function set_flash($t,$m){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $_SESSION['flash']=['type'=>$t,'msg'=>$m]; } }
if (!function_exists('get_flash_msg')) { function get_flash_msg($clear=true){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $m=$_SESSION['flash']??null; if($clear)unset($_SESSION['flash']); return $m?('<div class="alert alert-'.$m['type'].'">'.$m['msg'].'</div>'):''; } }

$id = (int)($_GET['id'] ?? 0);
$po = null; $items = []; $comments = []; $msg = get_flash_msg();
$employee_id = current_employee_id($pdo); // Obtener ID de hr_empleados del usuario logueado
$user_id = $_SESSION['user_id'] ?? null; // ID de global_users

if ($id > 0) {
    // --- MODIFICADO: Leer OT y JOINs adaptados ---
    $st = $pdo->prepare("
        SELECT po.*,
               co.name AS company_name, cl.name AS client_name, pr.name AS project_name,
               wh.name AS warehouse_name,
               assign.nombre AS assigned_name, assign.apellidos AS assigned_surname,
               req.nombre AS requester_name, req.apellidos AS requester_surname
        FROM inv_purchase_orders po
        LEFT JOIN inv_companies co ON co.id = 1 -- Asumiendo company_id no está en PO
        LEFT JOIN inv_clients cl ON cl.id = po.client_id
        LEFT JOIN inv_projects pr ON pr.id = po.project_id
        LEFT JOIN inv_warehouses wh ON wh.id = po.warehouse_id
        LEFT JOIN hr_empleados assign ON assign.id = po.assigned_employee_id -- Unir con hr_empleados
        LEFT JOIN hr_empleados req ON req.id = po.requester_employee_id -- Unir con hr_empleados
        WHERE po.id=? AND po.supplier_id IS NULL
    ");
    $st->execute([$id]);
    $po = $st->fetch();
    if (!$po) { header('Location: work_orders.php'); exit; }

    // Leer líneas
    $st_items = $pdo->prepare("
        SELECT i.*, p.sku, p.name as product_name
        FROM inv_purchase_order_lines i
        LEFT JOIN inv_products p ON p.id = i.product_id
        WHERE i.order_id=? ORDER BY i.id
    ");
    $st_items->execute([$id]);
    $items = $st_items->fetchAll();

    // Leer comentarios
    $st_comm = $pdo->prepare("
        SELECT c.*, u.username
        FROM inv_work_order_comments c
        LEFT JOIN global_users u ON u.id = c.user_id
        WHERE c.work_order_id=? ORDER BY c.created_at DESC
    ");
    $st_comm->execute([$id]);
    $comments = $st_comm->fetchAll();
    // --- FIN MODIFICADO ---
} else { header('Location: work_orders.php'); exit; }

// Procesar POST (comentar, aceptar, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_or_redirect();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'comment' && !empty($_POST['comment'])) {
            // --- MODIFICADO: Insertar en inv_work_order_comments ---
            $sql = "INSERT INTO inv_work_order_comments (work_order_id, user_id, comment) VALUES (?, ?, ?)";
            $pdo->prepare($sql)->execute([$id, $user_id, trim($_POST['comment'])]);
            // --- FIN MODIFICADO ---
            set_flash('success', 'Comentario añadido.');
        } elseif ($action === 'accept' && $employee_id && (int)$po['assigned_employee_id'] === $employee_id) {
            // --- MODIFICADO: Actualizar inv_purchase_orders ---
            $pdo->prepare("UPDATE inv_purchase_orders SET status='in_progress' WHERE id=?")->execute([$id]);
            // --- FIN MODIFICADO ---
            set_flash('success', 'Orden aceptada, estado cambiado a "En Progreso".');
        } elseif ($action === 'complete' && $employee_id && (int)$po['assigned_employee_id'] === $employee_id) {
            // --- MODIFICADO: Actualizar inv_purchase_orders ---
            $pdo->prepare("UPDATE inv_purchase_orders SET status='done' WHERE id=?")->execute([$id]);
            // --- FIN MODIFICADO ---
            set_flash('success', 'Orden marcada como completada.');
        }
        header("Location: work_order_view.php?id=" . $id); exit;
    } catch (Throwable $e) { $msg = '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>'; }
}

// Determinar color del badge de estado
$status_color = 'secondary';
if ($po['status'] === 'in_progress') $status_color = 'info';
elseif ($po['status'] === 'done') $status_color = 'success';
elseif ($po['status'] === 'cancelled') $status_color = 'danger';

$pageTitle = "Orden Trabajo #{$id}";
include __DIR__ . '/partials/header.php';
?>
<h4><i class="bi bi-tools"></i> <?= h($pageTitle) ?> <span class="badge bg-<?= $status_color ?>"><?= h($po['status']) ?></span></h4>
<?= $msg ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header py-2"><strong>Detalles</strong></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Empresa:</dt><dd class="col-sm-8"><?= h($po['company_name'] ?? '-') ?></dd>
          <dt class="col-sm-4">Cliente:</dt><dd class="col-sm-8"><?= h($po['client_name'] ?? '-') ?></dd>
          <dt class="col-sm-4">Proyecto:</dt><dd class="col-sm-8"><?= h($po['project_name'] ?? '-') ?></dd>
          <dt class="col-sm-4">Almacén:</dt><dd class="col-sm-8"><?= h($po['warehouse_name'] ?? '-') ?></dd>
          <dt class="col-sm-4">Asignado a:</dt><dd class="col-sm-8"><?= h(($po['assigned_name'] ?? '') . ' ' . ($po['assigned_surname'] ?? '-')) ?></dd>
          <dt class="col-sm-4">Solicitante:</dt><dd class="col-sm-8"><?= h(($po['requester_name'] ?? '') . ' ' . ($po['requester_surname'] ?? '-')) ?></dd>
          <dt class="col-sm-4">Fecha Orden:</dt><dd class="col-sm-8"><?= h($po['order_date'] ?? '-') ?></dd>
          <dt class="col-sm-4">Fecha Límite:</dt><dd class="col-sm-8"><?= h($po['expected_date'] ?? '-') // Usar expected_date ?></dd>
          <dt class="col-sm-4">Descripción:</dt><dd class="col-sm-8"><?= nl2br(h($po['notes'] ?? '-')) // Usar notes ?></dd>
        </dl>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header py-2"><strong>Líneas / Materiales</strong></div>
      <div class="card-body">
        <?php if ($items): ?>
          <ul class="list-group list-group-flush">
            <?php foreach($items as $it): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <?= h($it['description']) ?>
                <small class="d-block text-muted"><?= h($it['reference'] ?: $it['sku']) ?></small>
              </div>
              <span class="badge bg-primary rounded-pill"><?= h($it['quantity']) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No hay líneas definidas.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header py-2"><strong>Comentarios</strong></div>
      <div class="card-body">
        <?php if (empty($comments)): ?>
          <p class="text-muted">Sin comentarios.</p>
        <?php endif; ?>
        <?php foreach($comments as $c): ?>
          <div class="mb-2">
            <small class="text-muted"><?= h($c['username'] ?? 'Sistema') ?> - <?= h(date('d/m/Y H:i', strtotime($c['created_at']))) ?></small>
            <p class="mb-0"><?= nl2br(h($c['comment'])) ?></p>
          </div><hr class="my-2">
        <?php endforeach; ?>
        <form method="post" class="row g-2 mt-2">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="comment">
          <div class="col-12"><textarea class="form-control form-control-sm" name="comment" rows="2" placeholder="Añadir comentario..."></textarea></div>
          <div class="col-12 d-grid d-md-block"><button class="btn btn-sm btn-primary"><i class="bi bi-chat-text"></i> Comentar</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header py-2"><strong>Acciones</strong></div>
      <div class="card-body">
        <?php // Solo el asignado puede aceptar (si está pendiente) o completar (si está en progreso)
        if ($employee_id && (int)$po['assigned_employee_id'] === $employee_id): ?>
            <?php if ($po['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="accept">
                <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Aceptar orden</button>
              </form>
            <?php elseif ($po['status'] === 'in_progress'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Marcar esta orden como completada?')">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="complete">
                <button class="btn btn-success btn-sm"><i class="bi bi-check-all"></i> Marcar como Completada</button>
              </form>
            <?php endif; ?>
        <?php else: ?>
          <p class="text-muted small">No hay acciones disponibles para ti en este estado.</p>
        <?php endif; ?>
         <hr>
         <a href="work_orders.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Volver al listado</a>
         <?php if (has_role(['Admin General', 'Admin Inventario'])): ?>
            <a href="work_order_edit.php?id=<?= $id ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Editar (Admin)</a>
         <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>