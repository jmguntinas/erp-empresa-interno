<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf(); // Asumiendo check_csrf si hay acciones POST futuras

// --- MODIFICADO: Leer compañías de inv_companies ---
$companies = $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active
// --- FIN MODIFICADO ---
$company = (int)($_GET['company'] ?? ($_SESSION['active_company_id'] ?? 0));
$q = trim($_GET['q'] ?? '');

/* Stock actual por producto (leyendo de inv_product_stock) */
$where=[]; $params=[];
if($q!==''){
    // --- MODIFICADO: Buscar en inv_products, inv_categories ---
    $where[]="(p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)";
    array_push($params,"%$q%","%$q%","%$q%");
    // --- FIN MODIFICADO ---
}
if ($company > 0) {
    // --- MODIFICADO: Filtrar por company_id en inv_products ---
    $where[]="p.company_id = ?"; // Asumiendo que inv_products tiene company_id
    $params[]=$company;
    // --- FIN MODIFICADO ---
}
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

// --- MODIFICADO: Consulta principal adaptada ---
$rows = $pdo->prepare("
  SELECT
    p.id, p.sku, p.name, p.sale_price AS pvp, p.purchase_price AS cost, c.name AS category,
    COALESCE(SUM(ps.quantity), 0) AS stock_total -- Sumar stock de todos los almacenes
  FROM inv_products p
  LEFT JOIN inv_categories c ON c.id = p.category_id
  LEFT JOIN inv_product_stock ps ON ps.product_id = p.id
  $wsql
  GROUP BY p.id, p.sku, p.name, p.sale_price, p.purchase_price, c.name -- Agrupar por producto
  ORDER BY p.name ASC
");
// --- FIN MODIFICADO ---
$rows->execute($params); $list=$rows->fetchAll();

$pageTitle = 'Reporte Inventario';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-bar-chart-line me-2"></i> Reporte Inventario</h4>
   <?php if (file_exists(__DIR__ . '/partials/export_buttons.php')): ?>
       <?php include __DIR__ . '/partials/export_buttons.php'; ?>
   <?php endif; ?>
</div>

<form class="card mb-3" method="get">
<div class="card-body row gx-2 gy-2 align-items-center">
  <div class="col-md-3">
    <select name="company" class="form-select" onchange="this.form.submit()">
      <option value="0">-- Todas las Empresas --</option>
      <?php foreach($companies as $co): ?><option value="<?= $co['id'] ?>" <?= $company===$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-7"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por producto, SKU o categoría"></div>
  <div class="col-md-2 text-md-end"><button class="btn btn-outline-secondary">Filtrar</button></div>
</div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>SKU</th><th>Producto</th><th>Categoría</th><th class="text-end">Stock Total</th><th class="text-end">Coste</th><th class="text-end">PVP</th></tr></thead>
  <tbody>
    <?php foreach($list as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['sku'] ?? '-') ?></td>
        <td><a href="producto_stock.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></a></td>
        <td><?= htmlspecialchars($r['category'] ?? '-') ?></td>
        <td class="text-end"><?= number_format((float)$r['stock_total'], 2, ',', '.') ?></td>
        <td class="text-end"><?= number_format((float)($r['cost'] ?? 0),2,',','.') ?> €</td>
        <td class="text-end"><?= number_format((float)($r['pvp'] ?? 0),2,',','.') ?> €</td>
      </tr>
    <?php endforeach; if(empty($list)): ?>
    <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>