<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

$q = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if ($q === '') {
  header('Location: index.php');
  exit;
}

function likeParam($q){ return '%'.$q.'%'; }

$results = [
  'products'   => [],
  'categories' => [],
  'suppliers'  => [],
  'warehouses' => [],
  'movements'  => [],
];
$counts = [];

try {
  // PRODUCTS
  // --- MODIFICADO ---
  if ($scope === 'all' || $scope === 'products') {
    $st = $pdo->prepare("SELECT id, sku, name, pvp, cost FROM inv_products
                         WHERE name LIKE ? OR sku LIKE ?
                         ORDER BY name LIMIT ?");
    $st->execute([likeParam($q), likeParam($q), $perPage]);
    $results['products'] = $st->fetchAll();
    $cst = $pdo->prepare("SELECT COUNT(*) FROM inv_products WHERE name LIKE ? OR sku LIKE ?");
    $cst->execute([likeParam($q), likeParam($q)]);
    $counts['products'] = (int)$cst->fetchColumn();
  }
  // --- FIN MODIFICADO ---

  // CATEGORIES
  // --- MODIFICADO ---
  if ($scope === 'all' || $scope === 'categories') {
    $st = $pdo->prepare("SELECT id, name, description FROM inv_categories
                         WHERE name LIKE ? OR description LIKE ?
                         ORDER BY name LIMIT ?");
    $st->execute([likeParam($q), likeParam($q), $perPage]);
    $results['categories'] = $st->fetchAll();
    $cst = $pdo->prepare("SELECT COUNT(*) FROM inv_categories WHERE name LIKE ? OR description LIKE ?");
    $cst->execute([likeParam($q), likeParam($q)]);
    $counts['categories'] = (int)$cst->fetchColumn();
  }
  // --- FIN MODIFICADO ---

  // SUPPLIERS
  // --- MODIFICADO ---
  if ($scope === 'all' || $scope === 'suppliers') {
    $st = $pdo->prepare("SELECT id, name, tax_id, email, phone FROM inv_suppliers
                         WHERE name LIKE ? OR tax_id LIKE ? OR email LIKE ?
                         ORDER BY name LIMIT ?");
    $st->execute([likeParam($q), likeParam($q), likeParam($q), $perPage]);
    $results['suppliers'] = $st->fetchAll();
    $cst = $pdo->prepare("SELECT COUNT(*) FROM inv_suppliers WHERE name LIKE ? OR tax_id LIKE ? OR email LIKE ?");
    $cst->execute([likeParam($q), likeParam($q), likeParam($q)]);
    $counts['suppliers'] = (int)$cst->fetchColumn();
  }
  // --- FIN MODIFICADO ---

  // WAREHOUSES
  // --- MODIFICADO ---
  if ($scope === 'all' || $scope === 'warehouses') {
    $st = $pdo->prepare("SELECT id, name, code, city, country FROM inv_warehouses
                         WHERE name LIKE ? OR code LIKE ? OR city LIKE ?
                         ORDER BY name LIMIT ?");
    $st->execute([likeParam($q), likeParam($q), likeParam($q), $perPage]);
    $results['warehouses'] = $st->fetchAll();
    $cst = $pdo->prepare("SELECT COUNT(*) FROM inv_warehouses WHERE name LIKE ? OR code LIKE ? OR city LIKE ?");
    $cst->execute([likeParam($q), likeParam($q), likeParam($q)]);
    $counts['warehouses'] = (int)$cst->fetchColumn();
  }
  // --- FIN MODIFICADO ---

  // MOVEMENTS
  // --- MODIFICADO ---
  if ($scope === 'all' || $scope === 'movements') {
    $st = $pdo->prepare("SELECT m.id, m.created_at, m.type, m.qty,
                               p.id AS product_id, p.name AS product, p.sku,
                               w.name AS warehouse,
                               m.reason, m.reference
                        FROM inv_movements m
                        JOIN inv_products p ON p.id=m.product_id
                        JOIN inv_warehouses w ON w.id=m.warehouse_id
                        WHERE p.name LIKE ? OR p.sku LIKE ? OR m.reason LIKE ? OR m.reference LIKE ?
                        ORDER BY m.id DESC LIMIT ?");
    $st->execute([likeParam($q), likeParam($q), likeParam($q), likeParam($q), $perPage]);
    $results['movements'] = $st->fetchAll();
    $cst = $pdo->prepare("SELECT COUNT(m.id) FROM inv_movements m
                          JOIN inv_products p ON p.id=m.product_id
                          WHERE p.name LIKE ? OR p.sku LIKE ? OR m.reason LIKE ? OR m.reference LIKE ?");
    $cst->execute([likeParam($q), likeParam($q), likeParam($q), likeParam($q)]);
    $counts['movements'] = (int)$cst->fetchColumn();
  }
  // --- FIN MODIFICADO ---

} catch (Throwable $e) {
  $error = $e->getMessage();
}

$pageTitle = 'Buscar: '.$q;
require __DIR__ . '/partials/header.php';

function render_title(string $icon, string $title, ?int $count) {
  $str = "<h4 class=\"mt-4\"><i class=\"bi bi-$icon me-2\"></i> $title";
  if ($count !== null) $str .= " <span class=\"badge bg-secondary\">$count</span>";
  $str .= "</h4>";
  return $str;
}
?>

<h3>Resultados de búsqueda: "<?= htmlspecialchars($q) ?>"</h3>

<?php if(!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>


<?php if($scope==='all' || $scope==='products'): ?>
<?= render_title('box-seam', 'Productos', $counts['products'] ?? null); ?>
<?php if(empty($results['products'])): ?>
  <div class="text-muted">Sin resultados.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>SKU</th><th>Nombre</th><th>PVP</th><th>Coste</th></tr></thead>
      <tbody>
        <?php foreach($results['products'] as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['sku'] ?? '-') ?></td>
            <td><a href="producto_stock.php?id=<?= (int)$r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['name']) ?></a></td>
            <td><?= number_format((float)($r['pvp'] ?? 0), 2) ?></td>
            <td><?= number_format((float)($r['cost'] ?? 0), 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="text-end">
      <a class="btn btn-sm btn-outline-secondary" href="productos.php?q=<?= urlencode($q) ?>"><i class="bi bi-view-list"></i> Ver todos</a>
    </div>
  </div>
<?php endif; endif; ?>


<?php if($scope==='all' || $scope==='categories'): ?>
<?= render_title('tags', 'Categorías', $counts['categories'] ?? null); ?>
<?php if(empty($results['categories'])): ?>
  <div class="text-muted">Sin resultados.</div>
<?php else: ?>
  <div class="list-group list-group-flush">
    <?php foreach($results['categories'] as $r): ?>
      <a href="categorias.php?q=<?= urlencode($r['name']) ?>" class="list-group-item list-group-item-action">
        <?= htmlspecialchars($r['name']) ?>
        <small class="text-muted d-block"><?= htmlspecialchars($r['description'] ?? '') ?></small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; endif; ?>


<?php if($scope==='all' || $scope==='suppliers'): ?>
<?= render_title('truck', 'Proveedores', $counts['suppliers'] ?? null); ?>
<?php if(empty($results['suppliers'])): ?>
  <div class="text-muted">Sin resultados.</div>
<?php else: ?>
  <div class="list-group list-group-flush">
    <?php foreach($results['suppliers'] as $r): ?>
      <a href="proveedores.php?q=<?= urlencode($r['name']) ?>" class="list-group-item list-group-item-action">
        <?= htmlspecialchars($r['name']) ?>
        <small class="text-muted d-block">
          <?= htmlspecialchars($r['tax_id'] ?? '') ?> <?= htmlspecialchars($r['email'] ?? '') ?>
        </small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; endif; ?>


<?php if($scope==='all' || $scope==='warehouses'): ?>
<?= render_title('buildings', 'Almacenes', $counts['warehouses'] ?? null); ?>
<?php if(empty($results['warehouses'])): ?>
  <div class="text-muted">Sin resultados.</div>
<?php else: ?>
  <div class="list-group list-group-flush">
    <?php foreach($results['warehouses'] as $r): ?>
      <a href="almacenes.php?q=<?= urlencode($r['name']) ?>" class="list-group-item list-group-item-action">
        <?= htmlspecialchars($r['name']) ?>
        <small class="text-muted d-block">
          <?= htmlspecialchars($r['code'] ?? '') ?> <?= htmlspecialchars($r['city'] ?? '') ?>
        </small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; endif; ?>


<?php if($scope==='all' || $scope==='movements'): ?>
<?= render_title('arrows-expand-vertical', 'Movimientos', $counts['movements'] ?? null); ?>
<?php if(empty($results['movements'])): ?>
  <div class="text-muted">Sin resultados.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Fecha</th><th>Tipo</th><th>Cant.</th><th>Producto</th><th>SKU</th><th>Almacén</th><th>Motivo/Ref.</th></tr></thead>
      <tbody>
        <?php foreach($results['movements'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><span class="badge bg-<?= $r['type']=='IN'?'success':'danger' ?>"><?= $r['type']=='IN'?'Entrada':'Salida' ?></span></td>
            <td><?= (int)$r['qty'] ?></td>
            <td><a href="producto_stock.php?id=<?= urlencode($r['id']) ?>" class="text-decoration-none"><?= htmlspecialchars($r['product']) ?></a></td>
            <td><?= htmlspecialchars($r['sku']) ?></td>
            <td><?= htmlspecialchars($r['warehouse']) ?></td>
            <td><?= htmlspecialchars(trim(($r['reason'] ?? '').' '.$r['reference'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="text-end">
      <a class="btn btn-sm btn-outline-secondary" href="movimientos.php?ref=<?= urlencode($q) ?>"><i class="bi bi-view-list"></i> Ver todos</a>
    </div>
  </div>
<?php endif; endif; ?>


<?php require __DIR__ . '/partials/footer.php'; ?>