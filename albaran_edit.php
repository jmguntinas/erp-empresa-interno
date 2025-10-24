<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; 
// --- MODIFICADO: Eliminado check_csrf() ---
require_once __DIR__ . '/alerts.php';
require_once __DIR__ . '/movements_helper.php'; // <-- NUEVO helper para movimientos

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header('Location: albaranes.php'); exit; }

$msg='';
$dn = $pdo->prepare("
  SELECT dn.*, s.name AS supplier_name, w.name AS warehouse_name, co.name AS company_name,
         c.name AS client_name, c.internal_ref AS client_ref,
         p.name AS project_name, p.internal_ref AS project_ref
  FROM inv_delivery_notes dn
  JOIN inv_suppliers s ON s.id=dn.supplier_id
  JOIN inv_warehouses w ON w.id=dn.warehouse_id
  JOIN inv_companies co ON co.id=dn.company_id
  LEFT JOIN inv_clients c ON c.id=dn.client_id
  LEFT JOIN inv_projects p ON p.id=dn.project_id
  WHERE dn.id=?
");
$dn->execute([$id]); $alb = $dn->fetch();
if(!$alb){ header('Location: albaranes.php'); exit; }

$companies  = $pdo->query("SELECT id,name FROM inv_companies WHERE is_active=1 ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id,name FROM inv_suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT id,name FROM inv_warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
$clients    = $pdo->query("SELECT id,name FROM inv_clients WHERE is_active=1 ORDER BY name")->fetchAll();
$projects    = $pdo->query("SELECT id,name FROM inv_projects WHERE status='active' ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  
  if(isset($_POST['update_dn_header'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $st = $pdo->prepare("UPDATE inv_delivery_notes SET
      company_id=:company_id, supplier_id=:supplier_id, warehouse_id=:warehouse_id, client_id=:client_id, project_id=:project_id,
      notes=:notes, delivery_ref=:delivery_ref, delivery_date=:delivery_date
      WHERE id=:id");
    $st->execute([
      'id' => $id,
      'company_id' => (int)$_POST['company_id'],
      'supplier_id' => (int)$_POST['supplier_id'],
      'warehouse_id' => (int)$_POST['warehouse_id'],
      'client_id' => $_POST['client_id'] ? (int)$_POST['client_id'] : null,
      'project_id' => $_POST['project_id'] ? (int)$_POST['project_id'] : null,
      'notes' => $_POST['notes'] ?? '',
      'delivery_ref' => $_POST['delivery_ref'] ?? '',
      'delivery_date' => $_POST['delivery_date'] ? $_POST['delivery_date'] : null,
    ]);
    $msg=set_alert('success','Cabecera actualizada');
    header('Location: albaran_edit.php?id='.$id); exit;
  }
  
  if(isset($_POST['add_item'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $pid = (int)$_POST['product_id'];
    $qty = (float)$_POST['qty'];
    $cost = (float)$_POST['cost'];
    if ($pid && $qty > 0) {
      $st = $pdo->prepare("INSERT INTO inv_delivery_note_lines (delivery_note_id, product_id, qty, cost)
                           VALUES (?, ?, ?, ?)");
      $st->execute([$id, $pid, $qty, $cost]);
      $msg=set_alert('success','Línea añadida');
      header('Location: albaran_edit.php?id='.$id); exit;
    } else {
      $msg=set_alert('danger','Producto/cantidad inválidos');
    }
  }

  if(isset($_POST['del_item'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $item_id = (int)$_POST['item_id'];
    $st = $pdo->prepare("DELETE FROM inv_delivery_note_lines WHERE id=? AND delivery_note_id=?");
    $st->execute([$item_id, $id]);
    $msg=set_alert('success','Línea eliminada');
    header('Location: albaran_edit.php?id='.$id); exit;
  }

  if(isset($_POST['post_dn'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $items = $pdo->prepare("SELECT di.*, p.sku FROM inv_delivery_note_lines di
                            LEFT JOIN inv_products p ON p.id=di.product_id
                            WHERE di.delivery_note_id=?");
    $items->execute([$id]);
    foreach($items->fetchAll() as $it){
      create_movement($pdo, $it['product_id'], $alb['warehouse_id'], (float)$it['qty'], 'IN', 'Albarán '.$id, $id);
    }
    $pdo->prepare("UPDATE inv_delivery_notes SET status='posted' WHERE id=?")->execute([$id]);
    $msg=set_alert('success','Albarán contabilizado, movimientos creados.');
    header('Location: albaran_edit.php?id='.$id); exit;
  }
}


$items = $pdo->prepare("
  SELECT di.*, p.name AS product_name, p.sku AS product_sku
  FROM inv_delivery_note_lines di
  LEFT JOIN inv_products p ON p.id=di.product_id
  WHERE di.delivery_note_id=?
");
$items->execute([$id]);

$pageTitle = 'Albarán #'.$id;
require __DIR__ . '/partials/header.php'; 
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-truck-front me-2"></i> Albarán #<?= $id ?></h4>
  <div>
    <span class="badge fs-6 text-bg-<?= $alb['status']=='draft'?'secondary':'success' ?>"><?= $alb['status'] ?></span>
  </div>
</div>

<?= $msg ?>

<div class="row">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header">Cabecera</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
          <input type="hidden" name="update_dn_header" value="1">
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Empresa</label>
              <select name="company_id" class="form-select" <?= $alb['status']!='draft'?'disabled':'' ?>>
                <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$alb['company_id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label>Proveedor</label>
              <select name="supplier_id" class="form-select" <?= $alb['status']!='draft'?'disabled':'' ?>>
                <?php foreach($suppliers as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$alb['supplier_id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Almacén destino</label>
              <select name="warehouse_id" class="form-select" <?= $alb['status']!='draft'?'disabled':'' ?>>
                <?php foreach($warehouses as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$alb['warehouse_id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label>Cliente (opcional)</label>
              <select name="client_id" class="form-select" <?= $alb['status']!='draft'?'disabled':'' ?>>
                <option value="">-- ninguno --</option>
                <?php foreach($clients as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$alb['client_id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Proyecto (opcional)</label>
              <select name="project_id" class="form-select" <?= $alb['status']!='draft'?'disabled':'' ?>>
                <option value="">-- ninguno --</option>
                <?php foreach($projects as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$alb['project_id']?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Ref. Albarán</label>
              <input name="delivery_ref" class="form-control" value="<?= htmlspecialchars($alb['delivery_ref']??'') ?>" <?= $alb['status']!='draft'?'disabled':'' ?>>
            </div>
            <div class="col-md-6 mb-2">
              <label>Fecha Albarán</label>
              <input name="delivery_date" type="date" class="form-control" value="<?= htmlspecialchars($alb['delivery_date']??'') ?>" <?= $alb['status']!='draft'?'disabled':'' ?>>
            </div>
          </div>
          <div class="mb-2">
            <label>Notas</label>
            <textarea name="notes" class="form-control" rows="2" <?= $alb['status']!='draft'?'disabled':'' ?>><?= htmlspecialchars($alb['notes']??'') ?></textarea>
          </div>
          
          <?php if($alb['status']=='draft'): ?>
          <button type="submit" class="btn btn-primary">Guardar cabecera</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <?php if($alb['status']=='draft'): ?>
    <div class="card">
      <div class="card-header">Añadir línea</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
          <input type="hidden" name="add_item" value="1">
          <div class="mb-2">
            <label>Producto</label>
            <select name="product_id" class="form-select" id="product_search" data-placeholder="Buscar producto..."></select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Cantidad</label>
              <input name="qty" type="number" step="1" class="form-control" value="1">
            </div>
            <div class="col-md-6 mb-2">
              <label>Coste unitario</label>
              <input name="cost" type="number" step="0.01" class="form-control" value="0.00">
            </div>
          </div>
          <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir línea</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>


<div class="card mt-3">
  <div class="card-header">Líneas del albarán</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>SKU</th><th>Producto</th><th>Cantidad</th><th>Coste</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <?php foreach($items as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['product_sku'] ?? '-') ?></td>
            <td><?= htmlspecialchars($it['product_name'] ?? 'Producto #'.$it['product_id']) ?></td>
            <td><?= (float)$it['qty'] ?></td>
            <td><?= number_format((float)$it['cost'],2,',','.') ?> €</td>
            <td class="text-end">
              <?php if($alb['status']=='draft'): ?>
              <form method="post" onsubmit="return confirm('¿Eliminar línea?')" style="display:inline-block">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="del_item" value="1">
                <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; if(!$items): ?>
          <tr><td colspan="5" class="text-center text-muted">Sin líneas</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($alb['status']=='draft'): ?>
      <form method="post" onsubmit="return confirm('Se crearán movimientos de entrada en el almacén. ¿Continuar?')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="post_dn" value="1">
        <button class="btn btn-success"><i class="bi bi-check-circle"></i> Contabilizar albarán</button>
      </form>
    <?php endif; ?>
    
  </div>
</div>


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#product_search').select2({
        theme: 'bootstrap-5',
        ajax: {
            url: 'api/products_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
              return { q: params.term };
            },
            processResults: function (data, params) {
              return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 2,
        templateResult: (repo) => repo.loading ? 'Buscando...' : (repo.text || 'Producto'),
        templateSelection: (repo) => repo.text || 'Seleccionar producto'
    });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>