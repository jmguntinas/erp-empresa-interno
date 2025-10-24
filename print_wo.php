<?php
// --- MODIFICADO: Usar auth.php y db.php ---
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
// --- FIN MODIFICADO ---

$id=(int)($_GET['id']??0);

// --- MODIFICADO: Usar $pdo global ---
// $pdo=db(); // Si usas la función db()
// Si db.php define $pdo globalmente, ya está disponible
if (!isset($pdo) || !$pdo instanceof PDO) { die('Error: $pdo no disponible.'); }
// --- FIN MODIFICADO ---

// --- MODIFICADO: Usar tablas inv_ ---
$po=$pdo->prepare("SELECT * FROM inv_purchase_orders WHERE id=? AND supplier_id IS NULL"); // Sin supplier = Orden Trabajo
$po->execute([$id]); $po=$po->fetch();
if(!$po){ http_response_code(404); echo 'Orden de Trabajo (PO sin proveedor) no encontrada'; exit; }

$items=$pdo->prepare("
  SELECT i.*, p.name AS product_name, p.sku
  FROM inv_purchase_order_lines i
  LEFT JOIN inv_products p ON p.id=i.product_id
  WHERE i.order_id=? -- Columna renombrada
  ORDER BY i.id
");
// --- FIN MODIFICADO ---
$items->execute([$id]); $items=$items->fetchAll();

// Cargar la plantilla HTML
include __DIR__ . '/partials/header.php'; // Incluir cabecera para estilos
echo '<div class="container mt-4">'; // Añadir contenedor
include __DIR__ . '/formats/work_order.html.php'; // Cargar el HTML de la OT
echo '</div>'; // Cerrar contenedor
include __DIR__ . '/partials/footer.php'; // Incluir pie para scripts
?>
<script>
    // Opcional: añadir un botón de imprimir o llamar a window.print()
    // window.print();
</script>