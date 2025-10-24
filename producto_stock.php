<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header('Location: productos.php'); exit; }

// --- MODIFICADO: Leer compañías de inv_companies ---
$companies = $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active
// --- FIN MODIFICADO ---
$company = (int)($_GET['company'] ?? ($_SESSION['active_company_id'] ?? 0)); // Usar company_id 0 para "todas"

// --- MODIFICADO: Leer producto de inv_products y categoría de inv_categories ---
$prod = $pdo->prepare("
    SELECT p.*, c.name AS category
    FROM inv_products p
    LEFT JOIN inv_categories c ON c.id=p.category_id
    WHERE p.id=?
");
// --- FIN MODIFICADO ---
$prod->execute([$id]); $p=$prod->fetch();
if(!$p){ header('Location: productos.php'); exit; }

/* Stock por almacén */
$where=[]; $params=[ $id ];
if ($company > 0) {
    // --- MODIFICADO: Filtrar almacenes por company_id ---
    $where[]='w.company_id = ?'; $params[]=$company;
    // --- FIN MODIFICADO ---
}
$wsql = $where ? (' WHERE '.implode(' AND ',$where)) : '';

// --- MODIFICADO: Leer stock de inv_product_stock y unir con inv_warehouses ---
$st = $pdo->prepare("
  SELECT w.id, w.name, COALESCE(ps.quantity, 0) AS stock
  FROM inv_warehouses w
  LEFT JOIN inv_product_stock ps ON ps.warehouse_id = w.id AND ps.product_id = ?
  $wsql -- Aplicar filtro de compañía a los almacenes
");
// --- FIN MODIFICADO ---
$st->execute($params); $stockRows=$st->fetchAll();
$totalStock = array_sum(array_column($stockRows, 'stock'));

$pageTitle = 'Stock: '.($p['name'] ?? 'Producto #'.$id);
require __DIR__ . '/partials/header.php';
?>
<form class="d-flex align-items-center mb-3" method="get">
  <input type="hidden" name="id" value="<?= $id ?>">
  <h4>Stock: <?= htmlspecialchars($p['name']) ?></h4>
  <div class="col-md-3 ms-auto">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="0">-- Todos los Almacenes --</option>
      <?php foreach($companies as $co): ?><option value="<?= $co['id'] ?>" <?= $company===$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="ms-2"> <a class="btn btn-secondary btn-sm" href="productos.php">Volver</a>
  </div>
</form>

<div class="card mb-3">
  <div class="card-body d-flex align-items-center gap-3">
    <?php // --- MODIFICADO: Usar image_url ---
    if(!empty($p['image_url'])): ?>
        <img src="<?= htmlspecialchars($p['image_url']) ?>" style="height:64px;width:64px;object-fit:cover" alt="">
    <?php endif; ?>
    <div>
      <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
      <div class="text-muted small"><?= htmlspecialchars($p['sku'] ?? '') ?> · <?= htmlspecialchars($p['category'] ?? '-') ?></div>
    </div>
    <div class="ms-auto text-end">
      <?php // --- MODIFICADO: Usar sale_price y purchase_price --- ?>
      <div class="small text-muted">PVP / Coste</div>
      <div class="fw-semibold"><?= number_format((float)($p['sale_price'] ?? 0),2,',','.') ?> € / <?= number_format((float)($p['purchase_price'] ?? 0),2,',','.') ?> €</div>
       <?php // --- MODIFICADO: Usar min_stock_level --- ?>
       <div class="small <?= ($totalStock < (int)($p['min_stock_level'] ?? 0)) ? 'text-danger fw-bold' : 'text-muted' ?>">
           Stock Total: <?= $totalStock ?> (Mín: <?= (int)($p['min_stock_level'] ?? 0) ?>)
       </div>
    </div>
     <?php // --- FIN MODIFICADO --- ?>
  </div>
</div>

<div class="table-responsive">
<table class="table table-sm table-striped">
  <thead><tr><th>Almacén</th><th class="text-end">Stock</th></tr></thead>
  <tbody>
    <?php foreach($stockRows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td class="text-end"><?= number_format((float)$r['stock'], 2, ',', '.') // Mostrar decimales ?></td>
    </tr>
    <?php endforeach; if(empty($stockRows)): ?>
    <tr><td colspan="2" class="text-center text-muted">No hay almacenes para el filtro seleccionado.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
      <tr class="table-light fw-bold"><td class="text-end">Stock Total</td><td class="text-end"><?= number_format($totalStock, 2, ',', '.') ?></td></tr>
  </tfoot>
</table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>