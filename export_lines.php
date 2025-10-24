<?php
// export_lines.php (Adaptado para inv_)
// Endpoint unificado de exportación para Pedidos (inv_purchase_orders) y Albaranes (inv_delivery_notes).
// Uso:
//   export_lines.php?type=po&id=123&format=pdf   (pdf|xls|csv)
//   export_lines.php?type=dn&id=456&format=xls
//
// Requisitos: db.php, auth.php, export_utils.php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login(); // asegura sesión
require_once __DIR__ . '/export_utils.php'; // Asume que export_utils está actualizado o no depende de tablas

// -------------- Helpers --------------

/** Devuelve el nombre de columna preferido entre alternativas existentes. */
// Nota: has_col debe funcionar con el $pdo global
if (!function_exists('has_col')) {
    function has_col(string $table, string $col): bool {
        global $pdo; // Asegura acceso a $pdo
        if (!$pdo) return false;
        try {
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col");
            $stmt->execute([':db' => $dbName, ':tbl' => $table, ':col' => $col]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
function pick_col(string $table, array $candidates, ?string $default = null) : ?string {
    foreach ($candidates as $c) {
        if (has_col($table, $c)) return $c;
    }
    return $default;
}

/** Lee un registro por ID de una tabla. */
function fetch_by_id(PDO $pdo, string $table, int $id) : ?array {
    $idCol = has_col($table, 'id') ? 'id' : null;
    if (!$idCol) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$idCol}` = :id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** Carga datos de empresa */
function load_company(PDO $pdo, ?int $companyId): array {
    $company = ['name'=>'N/A', 'tax_id'=>'', 'address'=>'', 'phone'=>'', 'email'=>'', 'logo_url'=>null];
    if ($companyId) {
        // --- MODIFICADO ---
        $compRow = fetch_by_id($pdo, 'inv_companies', $companyId);
        // --- FIN MODIFICADO ---
        if ($compRow) {
            $company['name'] = $compRow['name'];
            $company['tax_id'] = $compRow['tax_id'] ?? '';
            $company['address'] = $compRow['address'] ?? '';
            $company['phone'] = $compRow['phone'] ?? '';
            $company['email'] = $compRow['email'] ?? '';
            $company['logo_url'] = $compRow['logo_url'] ?? null; // Asumiendo que existe esta columna
        }
    }
    return $company;
}

/** Construye metadatos del documento */
function build_document_meta(PDO $pdo, string $docType, array $docRow): array {
    $meta = [
        'id' => $docRow['id'],
        'type' => $docType === 'po' ? 'Pedido Compra' : 'Albarán Entrega',
        'date' => $docRow['order_date'] ?? $docRow['delivery_date'] ?? date('Y-m-d'),
        'status' => $docRow['status'] ?? 'N/A',
        'warehouse' => 'N/A',
        'supplier' => 'N/A',
        'client' => 'N/A', // Añadido
        'user' => 'N/A',
        'notes' => $docRow['notes'] ?? '',
        'ref' => $docRow['delivery_ref'] ?? ('#' . $docRow['id']), // Referencia o ID
    ];

    // --- MODIFICADO: Usar tablas inv_ y global_users ---
    if (!empty($docRow['warehouse_id'])) {
        $wh = fetch_by_id($pdo, 'inv_warehouses', (int)$docRow['warehouse_id']);
        if ($wh) $meta['warehouse'] = $wh['name'];
    }
    if (!empty($docRow['supplier_id'])) {
        $sup = fetch_by_id($pdo, 'inv_suppliers', (int)$docRow['supplier_id']);
        if ($sup) $meta['supplier'] = $sup['name'];
    }
    if (!empty($docRow['client_id'])) { // Añadido para Albaranes
        $cli = fetch_by_id($pdo, 'inv_clients', (int)$docRow['client_id']);
        if ($cli) $meta['client'] = $cli['name'];
    }
    if (!empty($docRow['created_by_user_id'])) { // Campo user_id renombrado
        $usr = fetch_by_id($pdo, 'global_users', (int)$docRow['created_by_user_id']);
        if ($usr) $meta['user'] = $usr['username']; // Usar username
    }
    // --- FIN MODIFICADO ---

    return $meta;
}

/** Carga las líneas del documento */
function load_document_lines(PDO $pdo, string $docType, int $docId): array {
    $lines = [];
    if ($docType === 'po') {
        // --- MODIFICADO ---
        $linesTable = 'inv_purchase_order_lines';
        $docIdCol = 'order_id';
        $unitPriceCol = 'unit_price';
        $qtyCol = 'quantity';
        $refCol = 'reference';
        $descCol = 'description';
        // --- FIN MODIFICADO ---
    } else { // dn
        // --- MODIFICADO ---
        $linesTable = 'inv_delivery_note_lines';
        $docIdCol = 'note_id';
        $unitPriceCol = 'unit_price'; // Asumiendo que existe o es null
        $qtyCol = 'quantity';
        $refCol = 'reference';
        $descCol = 'description';
        // --- FIN MODIFICADO ---
    }

    // --- MODIFICADO: Usar tabla inv_products ---
    $sql = "SELECT l.*, p.sku AS product_sku, p.name AS product_name
            FROM `{$linesTable}` l
            LEFT JOIN `inv_products` p ON p.id = l.product_id
            WHERE l.`{$docIdCol}` = :docId
            ORDER BY l.id ASC";
    // --- FIN MODIFICADO ---

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':docId' => $docId]);
        $linesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($linesRaw as $l) {
            $lines[] = [
                'ref' => $l[$refCol] ?? $l['product_sku'] ?? '',
                'desc' => $l[$descCol] ?? $l['product_name'] ?? 'N/A',
                'qty' => $l[$qtyCol] ?? 0,
                'price' => $l[$unitPriceCol] ?? null, // Puede ser null
                'total' => ($l[$qtyCol] ?? 0) * ($l[$unitPriceCol] ?? 0),
            ];
        }
    } catch (Throwable $e) { /* Ignorar error */ }

    return $lines;
}

// -------------- Main --------------

$docType = isset($_GET['type']) ? strtolower((string)$_GET['type']) : '';
$docId   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;
$format  = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'pdf'; // Default PDF

if (!in_array($docType, ['po','dn'], true) || $docId <= 0) {
    http_response_code(400);
    echo 'Parámetros inválidos. Ejemplo: export_lines.php?type=po&id=123&format=pdf';
    exit;
}

// --- MODIFICADO: Usar $pdo global de db.php ---
// $pdo = $GLOBALS['pdo'] ?? null; // db.php provee $pdo (ya está global)
if (!isset($pdo) || !$pdo instanceof PDO) {
// --- FIN MODIFICADO ---
    http_response_code(500);
    echo 'Conexión de base de datos no disponible.';
    exit;
}

// -------------- Carga documento + empresa --------------

if ($docType === 'po') {
    // --- MODIFICADO ---
    $docTable = 'inv_purchase_orders';
    // --- FIN MODIFICADO ---
} else {
    // --- MODIFICADO ---
    $docTable = 'inv_delivery_notes';
    // --- FIN MODIFICADO ---
}
$docRow = fetch_by_id($pdo, $docTable, $docId);
if (!$docRow) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

// Empresa (Asociada al documento, si existe company_id)
// --- MODIFICADO: Asumiendo company_id no existe en PO/DN ---
// $companyId = $docRow['company_id'] ?? null;
// $company = load_company($pdo, $companyId);
// Cargar la empresa "propia" por defecto si no hay una asociada al documento
$company = load_company($pdo, 1); // Asumiendo que la empresa con ID=1 es la principal
// --- FIN MODIFICADO ---

// Meta del documento
$document = build_document_meta($pdo, $docType, $docRow);

// Líneas
$lines = load_document_lines($pdo, $docType, $docId);

// -------------- Exportar --------------

$filename = ($docType === 'po' ? 'Pedido' : 'Albaran') . '_' . $docId . '.' . $format;
$title = $document['type'] . ' #' . $document['id'];

$headers = [
    'ref'   => 'Referencia',
    'desc'  => 'Descripción',
    'qty'   => 'Cantidad',
    'price' => 'Precio Unit.',
    'total' => 'Total',
];

// Añadir metadatos antes de las líneas para PDF/XLS
$metaRows = [
    ['ref'=>'Fecha:', 'desc'=>$document['date'], 'qty'=>'', 'price'=>'', 'total'=>''],
    ['ref'=>'Estado:', 'desc'=>$document['status'], 'qty'=>'', 'price'=>'', 'total'=>''],
    ['ref'=>'Almacén:', 'desc'=>$document['warehouse'], 'qty'=>'', 'price'=>'', 'total'=>''],
];
if ($document['supplier'] !== 'N/A') {
    $metaRows[] = ['ref'=>'Proveedor:', 'desc'=>$document['supplier'], 'qty'=>'', 'price'=>'', 'total'=>''];
}
if ($document['client'] !== 'N/A') {
    $metaRows[] = ['ref'=>'Cliente:', 'desc'=>$document['client'], 'qty'=>'', 'price'=>'', 'total'=>''];
}
// Separador
$metaRows[] = ['ref'=>'---', 'desc'=>'---', 'qty'=>'---', 'price'=>'---', 'total'=>'---'];

$exportData = array_merge($metaRows, $lines);

try {
    if ($format === 'csv') {
        export_csv($exportData, $headers, $filename);
    } elseif ($format === 'xls' || $format === 'xlsx') {
        export_xlsx($exportData, $headers, $filename);
    } else { // pdf
        export_pdf($exportData, $headers, $filename, $title);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error durante la exportación: " . htmlspecialchars($e->getMessage());
}