<?php
// --- MODIFICADO: Cargar auth.php y db.php ---
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
// --- FIN MODIFICADO ---

// --- MODIFICADO: Usar $pdo global y helpers ---
// $pdo=db(); // Si usas la función db()
// Si db.php define $pdo globalmente, ya está disponible
if (!isset($pdo) || !$pdo instanceof PDO) { die('Error: $pdo no disponible.'); }
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']); if(!$ok){ header('Location: '.basename($_SERVER['PHP_SELF']).'?e=csrf'); exit; } } }
// --- FIN MODIFICADO ---

$id=(int)($_GET['id']??0);

// --- MODIFICADO: Usar tablas inv_ ---
$po=$pdo->prepare("SELECT * FROM inv_purchase_orders WHERE id=?"); $po->execute([$id]); $po=$po->fetch();
if(!$po){ http_response_code(404); echo 'Pedido no encontrado'; exit; }

$items=$pdo->prepare("
  SELECT i.*, p.name AS product_name, p.sku
  FROM inv_purchase_order_lines i
  LEFT JOIN inv_products p ON p.id=i.product_id
  WHERE i.order_id=? -- Columna renombrada
  ORDER BY i.id
");
// --- FIN MODIFICADO ---
$items->execute([$id]); $items=$items->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf_or_redirect();
  $dn_date=$_POST['dn_date']?:date('Y-m-d');
  $wh=$po['warehouse_id']??null;
  $user_id = $_SESSION['user_id'] ?? null; // ID del usuario global

  // --- MODIFICADO: Insertar en inv_delivery_notes y usar created_by_user_id ---
  $st_dn = $pdo->prepare("
    INSERT INTO inv_delivery_notes
      (purchase_order_id, supplier_id, delivery_ref, delivery_date, status, warehouse_id, notes, created_by_user_id)
    VALUES (?, ?, ?, ?, 'draft', ?, NULL, ?)
  ");
  $st_dn->execute([
      $id, // purchase_order_id
      $po['supplier_id'],
      'Desde PO #'.$id, // delivery_ref (ejemplo)
      $dn_date,
      $wh,
      $user_id
  ]);
  $dn_id=(int)$pdo->lastInsertId();
  // --- FIN MODIFICADO ---

  foreach($items as $it){
    $q=max(0,(float)($_POST['qty_'.$it['id']]??0)); // Permitir decimales
    if($q>0){
      // --- MODIFICADO: Insertar en inv_delivery_note_lines, usar quantity y unit_price ---
      $price=(float)($it['unit_price']??0); // Usar unit_price del pedido
      $pdo->prepare("
        INSERT INTO inv_delivery_note_lines
          (note_id, product_id, quantity, unit_price, description, reference)
        VALUES (?,?,?,?,?,?)
      ")->execute([
          $dn_id,
          $it['product_id'],
          $q,
          $price,
          $it['description'], // Copiar descripción
          $it['reference'] // Copiar referencia
      ]);
      // --- FIN MODIFICADO ---
    }
  }
  // --- MODIFICADO: Redirigir a albaran_edit.php ---
  header('Location: albaran_edit.php?id='.$dn_id); exit;
  // --- FIN MODIFICADO ---
}

$pageTitle = 'Generar Albarán desde Pedido #'.$id; // Añadido título
include __DIR__ . '/partials/header.php'; ?>

<h4 class="mb-3"><i class="bi bi-box-arrow-in-down"></i> Generar Albarán Parcial · Pedido #<?= h($id) ?></h4>
<form method="post" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<div class="col-md-3"><label class="form-label">Fecha Albarán</label>
<input type="date" class="form-control form-control-sm" name="dn_date" value="<?= date('Y-m-d') ?>" required></div>
<div class="col-12">
<div class="table-responsive">
<table class="table table-sm table-striped align-middle">
<thead class="table-light"><tr><th>SKU</th><th>Producto</th><th>Pedida</th><th>Recibir ahora</th></tr></thead>
<tbody>
<?php foreach($items as $it): ?>
<tr>
    <td><?= h($it['sku']) ?></td>
    <td><?= h($it['description']) ?></td>
    <td><?= h($it['quantity']) ?></td>
    <td style="width:15%">
        <input type="number" step="any" class="form-control form-control-sm" name="qty_<?= h($it['id']) ?>" value="<?= h($it['quantity']) ?>" min="0" max="<?= h($it['quantity']) ?>">
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="text-end">
  <a href="pedidos.php" class="btn btn-secondary">Cancelar</a>
  <button type="submit" class="btn btn-primary">Generar Albarán</button>
</div>
</div>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>