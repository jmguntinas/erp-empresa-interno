<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

// --- MODIFICADO: Comprobar tablas con prefijo inv_ ---
try { foreach (['inv_products','inv_movements','inv_warehouses','inv_categories','inv_suppliers'] as $t) { $pdo->query("SELECT 1 FROM {$t} LIMIT 1"); } }
catch (Throwable $e) { header('Location: installer.php'); exit; } // Redirigir si faltan tablas clave
// --- FIN MODIFICADO ---

// KPIs
// --- MODIFICADO ---
$totalProductos = (int)$pdo->query("SELECT COUNT(*) FROM inv_products")->fetchColumn();
// --- FIN MODIFICADO ---

// --- MODIFICADO ---
$stockValor = $pdo->query("
  SELECT COALESCE(SUM(ps.quantity * p.purchase_price),0) AS val -- Usar quantity de product_stock y purchase_price
  FROM inv_product_stock ps
  JOIN inv_products p ON p.id = ps.product_id
  WHERE ps.quantity > 0 -- Solo contar stock positivo
")->fetchColumn(); // Nota: Esto calcula el valor a precio de coste
// --- FIN MODIFICADO ---

// --- MODIFICADO ---
$porDebajoMin = (int)$pdo->query("
  SELECT COUNT(*)
  FROM inv_products p
  JOIN inv_product_stock ps ON p.id = ps.product_id
  WHERE p.min_stock_level > 0 AND ps.quantity < p.min_stock_level
")->fetchColumn();
// --- FIN MODIFICADO ---

// Últimos movimientos
// --- MODIFICADO ---
$movimientos = $pdo->query("
  SELECT m.*, p.sku, p.name AS product, w.name AS warehouse, u.username AS uname
  FROM inv_movements m
  JOIN inv_products p ON p.id=m.product_id
  JOIN inv_warehouses w ON w.id=m.warehouse_id
  LEFT JOIN global_users u ON u.id=m.user_id -- Unir con global_users
  ORDER BY m.movement_date DESC
  LIMIT 10
")->fetchAll();
// --- FIN MODIFICADO ---

// Productos bajo mínimo (Top 10)
// --- MODIFICADO ---
$low = $pdo->query("
 SELECT p.id, p.sku, p.name, p.min_stock_level AS min_stock, ps.quantity AS stock
 FROM inv_products p
 JOIN inv_product_stock ps ON p.id = ps.product_id
 WHERE p.min_stock_level > 0 AND ps.quantity < p.min_stock_level
 ORDER BY (p.min_stock_level - ps.quantity) DESC -- Ordenar por mayor diferencia
 LIMIT 10
")->fetchAll();
// --- FIN MODIFICADO ---

$pageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>
<div class="row gy-3">
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <h5 class="card-title text-muted">Total Productos</h5>
      <p class="card-text fs-2"><?= $totalProductos ?></p>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <h5 class="card-title text-muted">Valor Stock (Coste)</h5>
      <p class="card-text fs-2"><?= number_format((float)$stockValor, 2, ',', '.') ?> €</p>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <h5 class="card-title text-muted">Alertas Stock Bajo</h5>
      <p class="card-text fs-2 <?= $porDebajoMin>0?'text-danger':'' ?>"><?= $porDebajoMin ?></p>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h5 class="card-title">Últimos Movimientos</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Fecha</th><th>Tipo</th><th>Cant.</th><th>Producto</th><th>Almacén</th><th>Usuario</th><th>Motivo</th><th>Ref.</th></tr></thead>
          <tbody>
          <?php foreach($movimientos as $m): ?>
            <tr>
              <td><small><?= date('d/m/y H:i', strtotime($m['movement_date'])) ?></small></td>
              <td><span class="badge bg-<?= $m['type']=='entrada'?'success':($m['type']=='salida'?'danger':'warning') ?>"><?= ucfirst($m['type']) ?></span></td>
              <td><?= (int)$m['quantity'] ?></td>
              <td><a href="productos_stock.php?id=<?= (int)$m['product_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($m['product'] ?? '?') ?></a></td>
              <td><?= htmlspecialchars($m['warehouse'] ?? '?') ?></td>
              <td><?= htmlspecialchars($m['uname'] ?? '-') ?></td>
              <td><?= htmlspecialchars($m['reason'] ?? '-') ?></td>
              <td><?= htmlspecialchars($m['reference_id'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="movimientos.php">Ver todos</a>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h5 class="card-title">Bajo de stock (Top 10)</h5>
      <?php if(empty($low)): ?>
        <p class="text-muted">Sin alertas de stock bajo.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>SKU</th><th>Producto</th><th>Stock</th><th>Mín.</th><th></th></tr></thead>
          <tbody>
          <?php foreach($low as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['sku']) ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td class="text-danger fw-bold"><?= (int)$r['stock'] ?></td>
              <td><?= (int)$r['min_stock'] ?></td>
              <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="movimientos.php?product_id=<?= $r['id'] ?>" title="Ver Movimientos"><i class="bi bi-list-ul"></i></a>
                  </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>