<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

$q = trim($_GET['q'] ?? '');
$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventario.csv"');

$out = fopen('php://output', 'w');
// --- MODIFICADO: Añadido BOM UTF-8 para Excel ---
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
// --- FIN MODIFICADO ---

fputcsv($out, ['SKU','Producto','Categoría','Coste ref.','PVP','Margen%','Stock','Mínimo','Estado'], ';'); // Quitado proveedor, añadido estado

// --- MODIFICADO: Consulta adaptada a inv_* y product_stock ---
$sql = "
SELECT
    p.id, p.sku, p.name, p.min_stock_level AS min_stock, -- Columna renombrada
    c.name AS category_name, -- Simplificado, sin ruta padre
    p.purchase_price AS cost, -- Columna renombrada
    p.sale_price AS pvp, -- Columna renombrada
    COALESCE(ps.quantity, 0) AS stock -- Leer de inv_product_stock
FROM
    inv_products p
LEFT JOIN inv_categories c ON c.id=p.category_id
LEFT JOIN inv_product_stock ps ON ps.product_id = p.id AND (? = 0 OR ps.warehouse_id = ?) -- Filtrar por almacén en JOIN
";
$params = [$warehouse_id, $warehouse_id]; // Parámetros para el filtro de almacén
$conds = [];

if ($q !== '') {
    $conds[]="(p.name LIKE ? OR p.sku LIKE ?)";
    $params[]="%$q%";
    $params[]="%$q%";
}
// Añadir filtro de almacén directamente en el LEFT JOIN de inv_product_stock

if ($conds) { $sql .= " WHERE ".implode(" AND ", $conds); }
$sql .= " ORDER BY p.name ASC";
// --- FIN MODIFICADO ---


$stmt = $pdo->prepare($sql); $stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cost = (float)($row['cost'] ?? 0);
    $pvp  = (float)($row['pvp'] ?? 0);
    $margin = ($pvp > 0) ? (($pvp - $cost) / $pvp * 100) : 0;
    $stock = (float)$row['stock']; // Stock ya viene calculado
    $min_stock = (int)($row['min_stock'] ?? 0);
    $status = ($stock < $min_stock) ? 'BAJO' : 'OK';

    fputcsv($out, [
        $row['sku'],
        $row['name'],
        $row['category_name'] ?? '-',
        number_format($cost, 2, ',', '.'),
        number_format($pvp, 2, ',', '.'),
        number_format($margin, 1, ',', '.').'%',
        $stock, // Mostrar stock directamente
        $min_stock,
        $status
    ], ';');
}
fclose($out);
exit; // Asegura que no se imprime nada más
?>