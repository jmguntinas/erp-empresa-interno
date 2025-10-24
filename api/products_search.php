<?php
// api/products_search.php
declare(strict_types=1);

/*
 Formato Select2:
 {
   "results":[
     {"id":1,"text":"Producto — REF","ref":"REF","vat_percent":21,"pvp":10.5,
      "suggested":{"unit_cost":7.5,"discount":5,"supplier_id":2,"supplier_name":"Acme SA"}
     }
   ],
   "pagination":{"more":true|false}
 }
*/

require_once __DIR__ . '/../auth.php'; require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common_helpers.php'; // Incluir helpers para has_table/has_col

header('Content-Type: application/json; charset=utf-8');

// --- Funciones auxiliares (movidas de common_helpers para asegurar que existen) ---
// (Si ya las tienes en common_helpers.php cargado globalmente, puedes quitar estas)
if (!function_exists('has_table')) {
    function has_table(PDO $pdo,string $t): bool {
      try {
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $q=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
        $q->execute([$db, $t]); return (int)$q->fetchColumn()>0;
      } catch (Throwable $e) { return false; }
    }
}
if (!function_exists('has_col')) {
    function has_col(PDO $pdo,string $t,string $c): bool {
       try {
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $q=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->execute([$db, $t, $c]); return (int)$q->fetchColumn()>0;
      } catch (Throwable $e) { return false; }
    }
}
// --- Fin funciones auxiliares ---


$term = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; // Aumentado ligeramente para Select2
$offset = ($page - 1) * $limit;

$where = ["(sku LIKE :term OR name LIKE :term)"];
$params = [':term' => "%$term%"];

// --- INICIO: MODIFICACIÓN PARA FILTRAR POR COMPOSITION_TYPE ---
if (isset($_GET['composition_type']) && $_GET['composition_type'] === 'simple') {
    // Solo añadir si la columna existe en la tabla inv_products
    if (has_col($pdo, 'inv_products', 'composition_type')) {
        $where[] = "composition_type = 'simple'";
        // No necesitamos añadir a $params porque el valor es fijo 'simple'
    }
}
// --- FIN: MODIFICACIÓN ---

$whereStr = implode(' AND ', $where);

// Contar total para paginación de Select2
$countSql = "SELECT COUNT(*) FROM inv_products WHERE $whereStr";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalCount = (int)$stmtCount->fetchColumn();

// Obtener resultados paginados
// Añadimos las columnas necesarias para Select2 y para la lógica de sugerencias
$sql = "SELECT id, sku, name, description, category_id, purchase_price, sale_price, vat_percent
        FROM inv_products 
        WHERE $whereStr 
        ORDER BY name ASC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatear para Select2 y añadir sugerencias
$select2_results = [];
$ps_table_exists = has_table($pdo, 'inv_product_suppliers');
$ps_has_cost = $ps_table_exists && has_col($pdo, 'inv_product_suppliers', 'cost');
$ps_has_disc = $ps_table_exists && has_col($pdo, 'inv_product_suppliers', 'discount_percent');
$ps_has_sup = $ps_table_exists && has_col($pdo, 'inv_product_suppliers', 'supplier_id');

foreach ($results as $row) {
  $id = (int)$row['id'];
  $txt = $row['name'] . ($row['sku'] ? ' (' . $row['sku'] . ')' : '');
  $ref = $row['sku'] ?: null; // Usamos SKU como referencia si existe

  // Información adicional útil
  $vat = $row['vat_percent'] !== null ? (float)$row['vat_percent'] : null;
  $pvp = $row['sale_price'] !== null ? (float)$row['sale_price'] : null;

  // Sugerencias de compra (coste, dto, proveedor) del último proveedor o el más barato
  $suggested = [];
  if ($ps_table_exists) {
    $costExpr = $ps_has_cost ? "ps.cost" : "p.purchase_price"; // Fallback al coste del producto si no hay en proveedor
    $discSel = $ps_has_disc ? ", ps.discount_percent AS discount" : "";
    $supSel = $ps_has_sup ? ", ps.supplier_id" : "";
    $order = $ps_has_cost ? "ps.cost ASC, " : ""; // Priorizar el más barato si hay coste
    
    $q = $pdo->prepare("SELECT $costExpr AS unit_cost $discSel $supSel
                        FROM inv_product_suppliers ps
                        LEFT JOIN inv_products p ON p.id = ps.product_id -- Necesario para fallback de coste
                        WHERE ps.product_id=?
                        ORDER BY $order ps.id DESC -- Fallback al último añadido si no hay coste
                        LIMIT 1");
    $q->execute([$id]);
    if ($ps = $q->fetch(PDO::FETCH_ASSOC)) {
      $suggested['unit_cost'] = $ps['unit_cost']!==null?(float)$ps['unit_cost']:null;
      if (array_key_exists('discount',$ps) && $ps['discount']!==null) {
        $suggested['discount'] = (float)$ps['discount'];
      }
      if ($ps_has_sup && array_key_exists('supplier_id',$ps) && $ps['supplier_id']!==null) {
        $suggested['supplier_id'] = (int)$ps['supplier_id'];
        // Nombre proveedor
        if (has_table($pdo,'inv_suppliers')) {
          $n = $pdo->prepare("SELECT name FROM inv_suppliers WHERE id=?");
          $n->execute([$suggested['supplier_id']]);
          $suggested['supplier_name'] = $n->fetchColumn() ?: null;
        }
      }
    }
  }
  // Si no hay sugerencias de proveedor, usar los precios base del producto
  if (empty($suggested) || !isset($suggested['unit_cost'])) {
      $suggested['unit_cost'] = $row['purchase_price'] !== null ? (float)$row['purchase_price'] : null;
  }
  
  $select2_results[] = [
    'id' => $id,
    'text' => $txt, // Para Select2
    'ref' => $ref,
    'vat_percent' => $vat,
    'pvp' => $pvp,
    // Datos extra que usamos en el JS de las páginas de edición
    'sku' => $row['sku'],
    'name' => $row['name'],
    'suggested' => $suggested ?: null // Enviar null si está vacío
  ];
}

// Devolver JSON
echo json_encode([
    'results' => $select2_results,
    // Paginación para Select2 (importante 'more')
    'pagination' => ['more' => ($page * $limit) < $totalCount] 
], JSON_UNESCAPED_UNICODE);

exit;
?>