<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; 
// --- MODIFICADO: Eliminado check_csrf() ---
require_once __DIR__ . '/enterprise_helper.php'; // Usa la versión modificada

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header('Location: pedidos.php'); exit; }

$msg='';

// --- MODIFICADO: Usar tablas inv_*, hr_empleados, global_users ---
$po = $pdo->prepare("
  SELECT po.*, s.name AS supplier_name, co.name AS company_name,
         hr.nombre AS requester_name, -- Leer de hr_empleados
         c.name AS client_name, p.name AS project_name
  FROM inv_purchase_orders po
  LEFT JOIN inv_suppliers s ON s.id=po.supplier_id
  LEFT JOIN inv_companies co ON co.id=1 -- Asumiendo company_id no está en PO, coger la principal
  LEFT JOIN global_users u ON u.id=po.created_by_user_id -- Unir por global_users
  LEFT JOIN hr_empleados hr ON hr.user_id=u.id -- Vincular a hr_empleados
  LEFT JOIN inv_clients c ON c.id=null -- Asumiendo client_id no está en PO
  LEFT JOIN inv_projects p ON p.id=null -- Asumiendo project_id no está en PO
  WHERE po.id=?
");
$po->execute([$id]); $pedido = $po->fetch();
if(!$pedido){ header('Location: pedidos.php'); exit; }

$companies  = $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active
$suppliers  = $pdo->query("SELECT id,name FROM inv_suppliers ORDER BY name")->fetchAll(); // Quitado is_active
$products   = $pdo->query("SELECT id,sku,name, purchase_price as cost FROM inv_products ORDER BY name")->fetchAll(); // Usar purchase_price
$warehouses = $pdo->query("SELECT id,name FROM inv_warehouses ORDER BY name")->fetchAll(); // Quitado is_active
$users      = $pdo->query("SELECT id, username as name FROM global_users WHERE is_active=1 ORDER BY username")->fetchAll(); // Leer de global_users
// --- FIN MODIFICADO ---

// Lógica POST (sin cambios de estructura, pero con validación CSRF y tablas inv_)
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['update_po_header'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    try {
        // --- MODIFICADO: Actualizar inv_purchase_orders ---
        $st = $pdo->prepare("UPDATE inv_purchase_orders SET
          supplier_id=:supplier_id, warehouse_id=:warehouse_id, created_by_user_id=:user_id,
          notes=:notes, order_date=:order_date, expected_date=:expected_date, status=:status
          WHERE id=:id");
        $st->execute([
          'id' => $id,
          'supplier_id' => (int)$_POST['supplier_id'],
          'warehouse_id' => (int)$_POST['warehouse_id'],
          'user_id' => $_POST['user_id'] ? (int)$_POST['user_id'] : null,
          'notes' => $_POST['notes'] ?? '',
          'order_date' => $_POST['order_date'] ?: null,
          'expected_date' => $_POST['expected_date'] ?: null,
          'status' => $_POST['status'] ?? 'draft',
        ]);
        // --- FIN MODIFICADO ---
        set_flash('success','Cabecera actualizada');
        header('Location: pedido_edit.php?id='.$id); exit;
    } catch (Throwable $e) { $msg=set_alert('danger','Error: '.$e->getMessage()); }
  }

  if(isset($_POST['add_item'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 1.0);
    $cost = (float)($_POST['cost'] ?? 0.0);
    $disc = (float)($_POST['discount'] ?? 0.0);
    $vat = (float)($_POST['vat'] ?? 0.0);
    $desc = trim($_POST['description'] ?? '');
    $ref = trim($_POST['reference'] ?? '');

    if ($pid && $qty > 0 && $desc !== '') {
        try {
            // --- MODIFICADO: Insertar en inv_purchase_order_lines ---
            $st = $pdo->prepare("INSERT INTO inv_purchase_order_lines
              (order_id, product_id, quantity, unit_price, discount_percent, vat_percent, description, reference)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([$id, $pid, $qty, $cost, $disc, $vat, $desc, $ref]);
            // --- FIN MODIFICADO ---
            set_flash('success','Línea añadida');
            header('Location: pedido_edit.php?id='.$id); exit;
        } catch (Throwable $e) { $msg=set_alert('danger','Error: '.$e->getMessage()); }
    } else { $msg=set_alert('danger','Producto, Cantidad y Descripción requeridos'); }
  }

  if(isset($_POST['del_item'])){
    // --- MODIFICADO: Validación CSRF ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación (CSRF). Intente recargar la página.');
    }
    // --- FIN MODIFICADO ---
    $item_id = (int)$_POST['item_id'];
    try {
        // --- MODIFICADO: Eliminar de inv_purchase_order_lines ---
        $st = $pdo->prepare("DELETE FROM inv_purchase_order_lines WHERE id=? AND order_id=?");
        $st->execute([$item_id, $id]);
        // --- FIN MODIFICADO ---
        set_flash('success','Línea eliminada');
        header('Location: pedido_edit.php?id='.$id); exit;
    } catch (Throwable $e) { $msg=set_alert('danger','Error: '.$e->getMessage()); }
  }
}
$msg .= get_flash_msg();

// --- MODIFICADO: Leer de inv_purchase_order_lines y unir con inv_products ---
$items = $pdo->prepare("
  SELECT i.*, p.name, p.sku, w.name as whname
  FROM inv_purchase_order_lines i
  LEFT JOIN inv_products p ON p.id=i.product_id
  LEFT JOIN inv_warehouses w ON w.id=null -- Asumiendo warehouse_id no está en líneas de PO
  WHERE i.order_id=? -- Columna renombrada
  ORDER BY i.id
");
// --- FIN MODIFICADO ---
$items->execute([$id]); $items = $items->fetchAll();

$pageTitle = 'Pedido #'.$id;
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-clipboard-check me-2"></i> Pedido Compra #<?= $id ?></h4>
  <div>
    <span class="badge fs-6 text-bg-<?= $pedido['status']=='draft'?'secondary':'primary' ?>"><?= htmlspecialchars($pedido['status']) ?></span>
    <a href="export_lines.php?type=po&id=<?= $id ?>&format=pdf" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
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
          <input type="hidden" name="update_po_header" value="1">
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Proveedor *</label>
              <select name="supplier_id" class="form-select" required>
                <?php foreach($suppliers as $s) echo '<option value="'.$s['id'].'"'.($s['id']==$pedido['supplier_id']?' selected':'').'>'.htmlspecialchars($s['name']).'</option>' ?>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label>Almacén Destino *</label>
              <select name="warehouse_id" class="form-select" required>
                <?php foreach($warehouses as $w) echo '<option value="'.$w['id'].'"'.($w['id']==$pedido['warehouse_id']?' selected':'').'>'.htmlspecialchars($w['name']).'</option>' ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label>Fecha Pedido</label>
              <input name="order_date" type="date" class="form-control" value="<?= htmlspecialchars($pedido['order_date']??'') ?>">
            </div>
            <div class="col-md-6 mb-2">
              <label>Fecha Esperada</label>
              <input name="expected_date" type="date" class="form-control" value="<?= htmlspecialchars($pedido['expected_date']??'') ?>">
            </div>
          </div>
           <div class="row">
             <div class="col-md-6 mb-2">
                <label>Usuario Creador</label>
                <select name="user_id" class="form-select">
                    <option value="">-- Sistema --</option>
                     <?php foreach($users as $u) echo '<option value="'.$u['id'].'"'.($u['id']==$pedido['created_by_user_id']?' selected':'').'>'.htmlspecialchars($u['name']).'</option>' ?>
                </select>
            </div>
            <div class="col-md-6 mb-2">
                <label>Estado</label>
                <select name="status" class="form-select">
                    <option value="draft" <?= $pedido['status']=='draft'?'selected':'' ?>>Borrador</option>
                    <option value="sent" <?= $pedido['status']=='sent'?'selected':'' ?>>Enviado</option>
                    <option value="confirmed" <?= $pedido['status']=='confirmed'?'selected':'' ?>>Confirmado</option>
                    <option value="received" <?= $pedido['status']=='received'?'selected':'' ?>>Recibido</option>
                    <option value="cancelled" <?= $pedido['status']=='cancelled'?'selected':'' ?>>Cancelado</option>
                </select>
            </div>
           </div>
          <div class="mb-2">
            <label>Notas</label>
            <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($pedido['notes']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Guardar cabecera</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card">
      <div class="card-header">Añadir línea</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
          <input type="hidden" name="add_item" value="1">
          <div class="mb-2">
            <label>Producto *</label>
            <select name="product_id" class="form-select" id="product_search" data-placeholder="Buscar producto..." required></select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2"><label>Descripción</label><input name="description" id="fld_desc" class="form-control form-control-sm"></div>
            <div class="col-md-6 mb-2"><label>Referencia</label><input name="reference" id="fld_ref" class="form-control form-control-sm"></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-2"><label>Cantidad *</label><input name="qty" type="number" step="any" class="form-control form-control-sm" value="1" required></div>
            <div class="col-md-4 mb-2"><label>Coste Unit.</label><input name="cost" id="fld_cost" type="number" step="0.01" class="form-control form-control-sm" value="0.00"></div>
            <div class="col-md-4 mb-2"><label>Dto %</label><input name="discount" id="fld_disc" type="number" step="0.01" class="form-control form-control-sm" value="0.00"></div>
          </div>
           <div class="row">
             <div class="col-md-4 mb-2"><label>IVA %</label><input name="vat" id="fld_vat" type="number" step="0.01" class="form-control form-control-sm" value="0.00"></div>
           </div>
          <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir línea</button>
        </form>
      </div>
    </div>
  </div>
</div>


<div class="card mt-3">
  <div class="card-header">Líneas del pedido</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>SKU</th><th>Producto/Desc.</th><th class="text-end">Cant.</th><th class="text-end">Coste</th><th class="text-end">Dto%</th><th class="text-end">IVA%</th><th class="text-end">Subtotal</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <?php $total = 0; foreach($items as $it):
              $qty = (float)($it['quantity'] ?? 0);
              $cost = (float)($it['unit_price'] ?? 0); // Columna renombrada
              $disc = (float)($it['discount_percent'] ?? 0);
              $vat = (float)($it['vat_percent'] ?? 0);
              $sub = $qty * $cost * (1 - $disc/100);
              $total += $sub * (1 + $vat/100);
          ?>
          <tr>
            <td><?= htmlspecialchars($it['sku'] ?? '-') ?></td>
            <td><?= htmlspecialchars($it['description']) ?></td>
            <td class="text-end"><?= $qty ?></td>
            <td class="text-end"><?= number_format($cost,2,',','.') ?> €</td>
            <td class="text-end"><?= number_format($disc,2,',','.') ?></td>
            <td class="text-end"><?= number_format($vat,2,',','.') ?></td>
            <td class="text-end"><?= number_format($sub,2,',','.') ?> €</td>
            <td class="text-end">
              <form method="post" onsubmit="return confirm('¿Eliminar línea?')" style="display:inline-block">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="del_item" value="1">
                <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                <button classJ="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; if(empty($items)): ?>
          <tr><td colspan="8" class="text-center text-muted">Sin líneas</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="text-end fw-bold">Total (con IVA)</td><td class="text-end fw-bold"><?= number_format($total, 2,',','.') ?> €</td><td></td></tr>
        </tfoot>
      </table>
    </div>
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
            // --- MODIFICADO: Usar API adaptada ---
            url: 'api/products_search.php', 
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data, params) { return { results: data.results }; },
            // --- FIN MODIFICADO ---
            cache: true
        },
        minimumInputLength: 1, 
        templateResult: (repo) => repo.loading ? 'Buscando...' : (repo.text || 'Producto'),
        templateSelection: (repo) => repo.text || 'Seleccionar producto'
    }).on('select2:select', function(e){
        const data = e.params.data;
        $('#fld_desc').val(data.text || '');
        $('#fld_ref').val(data.ref || '');
        if (data.suggested) {
            $('#fld_cost').val(data.suggested.unit_cost || 0);
            $('#fld_disc').val(data.suggested.discount || 0);
        }
        if (data.vat_percent) { $('#fld_vat').val(data.vat_percent); }
    });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>