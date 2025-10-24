<?php
// api/product_info.php
declare(strict_types=1);

/*
  Devuelve la información de un producto y sugerencias para completar una línea de pedido.

  Entrada (GET):
    - product_id (int)  -> ID del producto (prioridad más alta)
    - ref (string)      -> Referencia/SKU (si no se pasa product_id)

  Salida (JSON):
  {
    "ok": true,
    "product": {
      "id": 12,
      "name": "Nombre",
      "reference": "REF-001",
      "description": "Desc...",
      "vat_percent": 21.00,
      "pvp": 12.50
    },
    "suggested": {
      "supplier_id": 3,
      "supplier_name": "Proveedor SA",
      "unit_cost": 7.95,
      "discount": 5.00,
      "pvp": 12.50
    }
  }

  Notas:
  - Toma PVP de products.(pvp | price | sale_price) si existe.
  - El proveedor sugerido es el marcado como is_primary en product_suppliers; si no existe,
    se toma el de MENOR coste. Devuelve además discount (si existe) y unit_cost.
*/

require_once __DIR__ . '/../auth.php'; require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function db_name(PDO $pdo): string { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_table(PDO $pdo,string $t): bool {
  $q=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
  $q->execute([db_name($pdo), $t]); return (int)$q->fetchColumn()>0;
}
function has_col(PDO $pdo,string $t,string $c): bool {
  $q=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $q->execute([db_name($pdo), $t, $c]); return (int)$q->fetchColumn()>0;
}
function exit_error(string $msg, int $code=404) {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$msg]);
  exit;
}

$pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$ref = isset($_GET['ref']) ? trim((string)$_GET['ref']) : null;

if (!$pid && !$ref) {
  exit_error("Se requiere 'product_id' o 'ref'", 400);
}

// --- MODIFICADO: Apuntar a 'inv_products' ---
$table = 'inv_products';
// --- FIN MODIFICADO ---

$col_vat = null; $col_pvp = null;
if (has_col($pdo,$table,'vat_percent')) $col_vat = 'vat_percent';
elseif (has_col($pdo,$table,'vat'))      $col_vat = 'vat';
if (has_col($pdo,$table,'pvp'))          $col_pvp = 'pvp';
elseif (has_col($pdo,$table,'price'))    $col_pvp = 'price';
elseif (has_col($pdo,$table,'sale_price')) $col_pvp = 'sale_price';

/* ===== 1. Buscar producto ===== */
$sql = "SELECT p.id, p.name AS pname, p.reference AS pref, p.description AS pdesc";
if ($col_vat) $sql .= ", $col_vat AS vat";
if ($col_pvp) $sql .= ", $col_pvp AS pvp";
// --- MODIFICADO: Apuntar a 'inv_products' ---
$sql .= " FROM inv_products p WHERE ";
// --- FIN MODIFICADO ---
$sqlParams = [];

if ($pid) {
  $sql .= "p.id = ?"; $sqlParams[] = $pid;
} else {
  $sql .= "p.reference = ?"; $sqlParams[] = $ref;
}
$st = $pdo->prepare($sql);
$st->execute($sqlParams);
$prod = $st->fetch(PDO::FETCH_ASSOC);

if (!$prod) {
  exit_error("Producto no encontrado", 404);
}
$pid = (int)$prod['id'];

/* ===== 2. Buscar stock (opcional) ===== */
$stock_total = null;
// --- MODIFICADO: Apuntar a 'inv_product_stock' ---
if (has_table($pdo, 'inv_product_stock')) {
  try {
    $qStock = $pdo->prepare("SELECT SUM(quantity) FROM inv_product_stock WHERE product_id=?");
    $qStock->execute([$pid]);
    $stock_total = (int)$qStock->fetchColumn();
  } catch (Throwable $e) {}
}
// --- FIN MODIFICADO ---

/* ===== 3. Buscar sugerencias (coste, proveedor) ===== */
$supplier_id   = null;
$supplier_name = null;
$unit_cost     = null;
$discount      = 0.0;
$pvp           = $prod['pvp'] ?? null;

// --- MODIFICADO: Apuntar a 'inv_product_suppliers' y 'inv_suppliers' ---
if (has_table($pdo, 'inv_product_suppliers')) {
  $costExpr = "unit_cost";
  $ps_has_disc = has_col($pdo,'inv_product_suppliers','discount');
  $ps_has_sup  = has_col($pdo,'inv_product_suppliers','supplier_id');
  $ps_has_prim = has_col($pdo,'inv_product_suppliers','is_primary');
  
  $discSel = $ps_has_disc ? ", discount" : "";
  $supSel  = $ps_has_sup  ? ", supplier_id" : "";
  $order   = $ps_has_prim ? "is_primary DESC, " : "";

  $sqlPS = "SELECT $costExpr AS unit_cost $discSel $supSel
            FROM inv_product_suppliers ps
            WHERE ps.product_id=?
            ORDER BY $order unit_cost IS NULL, unit_cost ASC
            LIMIT 1";
  $pst = $pdo->prepare($sqlPS);
  $pst->execute([$pid]);
  $psr = $pst->fetch(PDO::FETCH_ASSOC);

  if ($psr) {
    $unit_cost = $psr['unit_cost'] !== null ? (float)$psr['unit_cost'] : null;
    if (array_key_exists('discount',$psr) && $psr['discount'] !== null) {
      $discount = (float)$psr['discount'];
    }
    if (array_key_exists('supplier_id',$psr) && $psr['supplier_id'] !== null) {
      $supplier_id = (int)$psr['supplier_id'];
      if (has_table($pdo,'inv_suppliers')) {
        $n = $pdo->prepare("SELECT name FROM inv_suppliers WHERE id=?");
        $n->execute([$supplier_id]);
        $supplier_name = $n->fetchColumn() ?: null;
      }
    }
  }
}
// --- FIN MODIFICADO ---

/* ===== Respuesta ===== */
echo json_encode([
  'ok' => true,
  'product' => [
    'id'          => (int)$prod['id'],
    'name'        => $prod['pname'] ?? null,
    'reference'   => $prod['pref']  ?? null,
    'description' => $prod['pdesc'] ?? null,
    'vat_percent' => isset($prod['vat']) ? (float)$prod['vat'] : null,
    'pvp'         => isset($prod['pvp']) ? (float)$prod['pvp'] : null,
    'stock_total' => $stock_total,
  ],
  'suggested' => [
    'supplier_id'   => $supplier_id,
    'supplier_name' => $supplier_name,
    'unit_cost'     => $unit_cost,
    'discount'      => $discount,
    'pvp'           => $pvp,
  ]
]);