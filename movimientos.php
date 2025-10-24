<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();
require_once __DIR__ . '/movements_helper.php';

/* ---------- Parámetros de filtro ---------- */
// --- MODIFICADO: Leer de inv_companies, inv_products, inv_warehouses ---
$companies = $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active si no existe
$prodRows = $pdo->query("SELECT id, name, sku FROM inv_products ORDER BY name")->fetchAll();
$whRows   = $pdo->query("SELECT id, name FROM inv_warehouses ORDER BY name")->fetchAll();
// --- FIN MODIFICADO ---

$company = (int)($_GET['company'] ?? ($_SESSION['active_company_id'] ?? 0)); // No aplicaremos filtro por compañía en movimientos directamente, ya que están ligados a almacén/producto
$q       = trim($_GET['q'] ?? ''); // Búsqueda general (producto, motivo, ref)
$type    = $_GET['type'] ?? 'all'; // all, entrada, salida, ajuste
$product = (int)($_GET['product'] ?? 0);
$wh      = (int)($_GET['wh'] ?? 0);
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = defined('APP_PAGE_SIZE') ? (int)APP_PAGE_SIZE : 25;

/* ---------- Lógica de Crear/Editar (si POST o ?edit=ID) ---------- */
$editMove = null; $msg = '';
$editID = (int)($_GET['edit'] ?? 0);
if ($editID > 0) {
    // --- MODIFICADO: Leer de inv_movements ---
    $st = $pdo->prepare("SELECT * FROM inv_movements WHERE id=?");
    // --- FIN MODIFICADO ---
    $st->execute([$editID]);
    $editMove = $st->fetch();
    if (!$editMove) { $editID = 0; } // No encontrado, resetear
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $movId = (int)($_POST['id'] ?? $editID); // ID para editar o 0 para crear

  if ($action === 'save') {
    $pId = (int)($_POST['product_id'] ?? 0);
    $wId = (int)($_POST['warehouse_id'] ?? 0);
    $movType = $_POST['type'] ?? ''; // 'entrada', 'salida', 'ajuste'
    $qty = (float)($_POST['quantity'] ?? 0); // La cantidad introducida por el usuario
    $reason = trim($_POST['reason'] ?? '');
    $ref = trim($_POST['reference'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if ($pId > 0 && $wId > 0 && in_array($movType, ['entrada','salida','ajuste'])) {
        if ($movId > 0) {
            // *** EDITAR MOVIMIENTO (Complejo: requiere revertir el viejo y aplicar el nuevo) ***
            // Por simplicidad, deshabilitaremos la edición directa.
            // La forma correcta sería:
            // 1. Obtener movimiento antiguo.
            // 2. Revertirlo (crear movimiento inverso).
            // 3. Crear el nuevo movimiento.
            $msg = '<div class="alert alert-warning">La edición directa de movimientos no está implementada. Elimina y crea uno nuevo.</div>';
        } else {
            // *** CREAR MOVIMIENTO ***
            if ($movType === 'ajuste' && $qty == 0) {
                 $msg = '<div class="alert alert-danger">La cantidad para un ajuste no puede ser cero.</div>';
            } elseif ($movType !== 'ajuste' && $qty <= 0) {
                 $msg = '<div class="alert alert-danger">La cantidad para entradas/salidas debe ser mayor que cero.</div>';
            } else {
                // Usar el helper 'create_movement' que actualiza stock
                $ok = create_movement($pdo, $pId, $wId, $qty, $movType, $reason, null, $userId); // Ref ID se deja null para manuales

                if ($ok) {
                    set_flash('success', 'Movimiento creado correctamente.');
                    header('Location: movimientos.php?' . http_build_query(array_diff_key($_GET,['edit'=>1]))); exit;
                } else {
                     $msg = '<div class="alert alert-danger">Error al crear el movimiento.</div>';
                }
            }
        }
    } else {
      $msg = '<div class="alert alert-danger">Datos inválidos (Producto, Almacén, Tipo, Cantidad).</div>';
    }
  }
}
$msg .= get_flash_msg(); // Recoger mensajes flash

/* ---------- Construcción de la consulta ---------- */
$params=[]; $where=['1=1'];
if ($q !== ''){
  // --- MODIFICADO: Buscar en inv_products y inv_movements ---
  $where[]='(p.name LIKE ? OR p.sku LIKE ? OR m.reason LIKE ? OR m.reference_id LIKE ?)';
  array_push($params,"%$q%","%$q%","%$q%","%$q%");
  // --- FIN MODIFICADO ---
}
if (in_array($type, ['entrada','salida','ajuste'])) {
  // --- MODIFICADO: Filtrar por m.type ---
  $where[]='m.type = ?'; $params[]=$type;
  // --- FIN MODIFICADO ---
}
if ($product > 0) {
  // --- MODIFICADO: Filtrar por m.product_id ---
  $where[]='m.product_id = ?'; $params[]=$product;
  // --- FIN MODIFICADO ---
}
if ($wh > 0) {
  // --- MODIFICADO: Filtrar por m.warehouse_id ---
  $where[]='m.warehouse_id = ?'; $params[]=$wh;
  // --- FIN MODIFICADO ---
}
$whereStr = 'WHERE '.implode(' AND ',$where);

/* ---------- Paginación ---------- */
// --- MODIFICADO: Contar en inv_movements ---
$count=$pdo->prepare("SELECT COUNT(m.id) FROM inv_movements m LEFT JOIN inv_products p ON p.id=m.product_id $whereStr");
// --- FIN MODIFICADO ---
$count->execute($params);
$totalRows=(int)$count->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$offset=($page-1)*$perPage;

/* ---------- Listado ---------- */
// --- MODIFICADO: Select principal usando tablas inv_* y global_users ---
$sql = "
  SELECT m.*, p.sku, p.name AS product, w.name AS warehouse, u.username AS uname
  FROM inv_movements m
  JOIN inv_products p ON p.id=m.product_id
  JOIN inv_warehouses w ON w.id=m.warehouse_id
  LEFT JOIN global_users u ON u.id=m.user_id -- Unir con global_users
  $whereStr
  ORDER BY m.movement_date DESC, m.id DESC
  LIMIT $perPage OFFSET $offset
";
// --- FIN MODIFICADO ---
$rows=$pdo->prepare($sql); $rows->execute($params); $list=$rows->fetchAll();

$pageTitle = 'Movimientos';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-arrows-expand-vertical me-2"></i> Movimientos de Stock</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditar"><i class="bi bi-plus-lg"></i> Nuevo Movimiento</button>
</div>
<?= $msg ?>

<div class="card mb-3"><div class="card-body">
  <form method="get" class="row gx-2 gy-2 align-items-center">
    <div class="col-md-3"><input name="q" class="form-control form-control-sm" placeholder="Buscar prod, motivo, ref..." value="<?= h($q) ?>"></div>
    <div class="col-md-2"><select name="type" class="form-select form-select-sm">
      <option value="all" <?= $type=='all'?'selected':'' ?>>-- Tipo --</option>
      <option value="entrada" <?= $type=='entrada'?'selected':'' ?>>Entrada</option>
      <option value="salida" <?= $type=='salida'?'selected':'' ?>>Salida</option>
      <option value="ajuste" <?= $type=='ajuste'?'selected':'' ?>>Ajuste</option>
    </select></div>
    <div class="col-md-3"><select name="product" class="form-select form-select-sm">
      <option value="">-- Producto --</option>
      <?php foreach($prodRows as $r) echo '<option value="'.$r['id'].'"'.($r['id']==$product?' selected':'').'>'.h($r['sku'].' - '.$r['name']).'</option>' ?>
    </select></div>
    <div class="col-md-2"><select name="wh" class="form-select form-select-sm">
      <option value="">-- Almacén --</option>
      <?php foreach($whRows as $r) echo '<option value="'.$r['id'].'"'.($r['id']==$wh?' selected':'').'>'.h($r['name']).'</option>' ?>
    </select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary">Filtrar</button></div>
  </form>
</div></div>

<form method="post" id="formListado">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<div class="table-responsive">
  <table class="table table-sm table-striped table-hover align-middle">
    <thead>
      <tr>
        <th><input type="checkbox" id="chkAll" title="Seleccionar todos"></th>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Cant.</th>
        <th>Producto</th>
        <th>Almacén</th>
        <th>Usuario</th>
        <th>Motivo</th>
        <th>Ref.</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if($list): foreach($list as $r): ?>
      <tr>
        <td><input type="checkbox" name="sel[]" value="<?= (int)$r['id'] ?>"></td>
        <td><small><?= date('d/m/y H:i', strtotime($r['movement_date'])) ?></small></td>
        <td><span class="badge bg-<?= $r['type']=='entrada'?'success':($r['type']=='salida'?'danger':'warning') ?>"><?= h(ucfirst($r['type'])) ?></span></td>
        <td><?= h($r['quantity']) ?></td>
        <td><a href="productos_stock.php?id=<?= (int)$r['product_id'] ?>" class="text-decoration-none" title="<?= h($r['sku']) ?>"><?= h($r['product'] ?? '?') ?></a></td>
        <td><?= h($r['warehouse'] ?? '?') ?></td>
        <td><?= h($r['uname'] ?? '-') ?></td>
        <td><small><?= h($r['reason'] ?? '-') ?></small></td>
        <td><small><?= h($r['reference_id'] ?? '-') ?></small></td>
        <td class="text-end text-nowrap">
          <form method="post" action="movimientos_delete.php" class="d-inline" onsubmit="return confirm('¿Eliminar movimiento #<?= (int)$r['id'] ?>? Esta acción NO revierte el cambio de stock.');">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="10" class="text-center text-muted">Sin resultados</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</form>

<?php if($totalPages>1): ?>
<nav><ul class="pagination pagination-sm">
  <?php for($i=1;$i<=$totalPages;$i++): ?>
  <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>


<div class="modal fade" id="modalEditar" tabindex="-1" data-bs-backdrop="static" <?php if($editID>0 || $msg) echo 'data-force-show="1"'; ?> >
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editID ?>"> <div class="modal-header">
        <h5 class="modal-title"><?= $editID ? 'Editar Movimiento #'.$editID : 'Nuevo Movimiento Manual' ?></h5>
        <a class="btn-close" href="movimientos.php?<?= http_build_query(array_diff_key($_GET,['edit'=>1])) ?>"></a>
      </div>
      <div class="modal-body">
        <?php if($editID): ?>
          <div class="alert alert-warning">La edición directa no revierte el stock. Se recomienda eliminar y crear uno nuevo.</div>
        <?php endif; ?>
        <div class="mb-2">
          <label class="form-label">Producto *</label>
          <select class="form-select" name="product_id" required>
            <option value="">-- Selecciona --</option>
            <?php foreach($prodRows as $r) echo '<option value="'.$r['id'].'"'.($r['id']==($editMove['product_id']??0)?' selected':'').'>'.h($r['sku'].' - '.$r['name']).'</option>' ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Almacén *</label>
          <select class="form-select" name="warehouse_id" required>
            <option value="">-- Selecciona --</option>
            <?php foreach($whRows as $r) echo '<option value="'.$r['id'].'"'.($r['id']==($editMove['warehouse_id']??0)?' selected':'').'>'.h($r['name']).'</option>' ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label">Tipo *</label>
            <select class="form-select" name="type" required>
              <option value="entrada" <?= ($editMove['type']??'')=='entrada'?'selected':'' ?>>Entrada (+)</option>
              <option value="salida" <?= ($editMove['type']??'')=='salida'?'selected':'' ?>>Salida (-)</option>
              <option value="ajuste" <?= ($editMove['type']??'')=='ajuste'?'selected':'' ?>>Ajuste (+/-)</option>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label">Cantidad *</label>
            <input type="number" step="any" class="form-control" name="quantity" value="<?= h($editMove['quantity'] ?? '') ?>" required placeholder="Ej: 10 / -5">
            <div class="form-text">Positivo para Entrada, Negativo para Salida, +/- para Ajuste.</div>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Motivo</label>
          <input class="form-control" name="reason" value="<?= h($editMove['reason'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Referencia</label>
          <input class="form-control" name="reference" value="<?= h($editMove['reference_id'] ?? '') ?>"> </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-secondary" href="movimientos.php?<?= http_build_query(array_diff_key($_GET,['edit'=>1])) ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit" <?php if($editID) echo 'disabled title="Edición deshabilitada"'; ?> >Guardar</button> </div>
    </form>
  </div>
</div>
<script>
    // Script para mostrar modal si hay error o se está editando
    document.addEventListener('DOMContentLoaded', function(){
        const modal = document.getElementById('modalEditar');
        if (modal && modal.dataset.forceShow === '1') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
        // Cerrar modal al hacer clic fuera (si no estamos editando)
        modal.addEventListener('click', function(e) {
            if (modal.dataset.forceShow !== '1' && e.target === modal) {
                 window.location.href = 'movimientos.php?<?= http_build_query(array_diff_key($_GET,['edit'=>1])) ?>';
            }
        });
    });

  // Seleccionar/deseleccionar todo
  const chkAll = document.getElementById('chkAll');
  if (chkAll) {
    chkAll.addEventListener('change', function(e){
      document.querySelectorAll('#formListado input[name="sel[]"]').forEach(chk => chk.checked = e.target.checked);
    });
  }
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>