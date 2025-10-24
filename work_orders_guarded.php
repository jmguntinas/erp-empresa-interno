<?php
require_once __DIR__ . '/db.php';
// --- MODIFICADO: Usar auth.php unificado ---
require_once __DIR__ . '/auth.php'; require_login(); // Asegurar login
// --- FIN MODIFICADO ---

// Helpers mínimos (adaptados)
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
// --- MODIFICADO: Usar $pdo global en lugar de db() ---
// $pdo = db(); // Si tu db.php define la función db()
// Si db.php define $pdo globalmente:
if (!isset($pdo) || !$pdo instanceof PDO) { die('Error: $pdo no disponible.'); }
// --- FIN MODIFICADO ---

// --- MODIFICADO: Consulta adaptada a inv_*, hr_empleados ---
$rows=$pdo->query("
    SELECT po.*,
           req.nombre AS requester_name, req.apellidos as requester_surname,
           cl.name AS client_name,
           pr.name AS project_name,
           w.name AS wh_name
    FROM inv_purchase_orders po
    LEFT JOIN hr_empleados req ON req.id=po.requester_employee_id -- Unir con hr_empleados
    LEFT JOIN inv_clients cl ON cl.id=po.client_id
    LEFT JOIN inv_projects pr ON pr.id=po.project_id
    LEFT JOIN inv_warehouses w ON w.id=po.warehouse_id
    WHERE po.supplier_id IS NULL -- Condición para que sea OT
    ORDER BY po.id DESC
")->fetchAll();
// --- FIN MODIFICADO ---

$pageTitle = 'Órdenes de Trabajo (Vista)'; // Añadido título
include __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3"><i class="bi bi-tools"></i> Órdenes de trabajo internas</h4>
<div class="table-responsive">
<table class="table table-sm table-striped align-middle">
<thead class="table-light">
<tr>
    <th>ID</th>
    <th>Solicitante</th>
    <th>Cliente</th>
    <th>Proyecto</th>
    <th>Almacén</th>
    <th>Estado</th>
    <th>Fecha Orden</th>
    <th>Fecha Límite</th>
    <th class="text-end">Acciones</th>
</tr>
</thead><tbody>
<?php foreach($rows as $po): ?>
<tr>
<td><?= h($po['id']) ?></td>
<td><?= h(($po['requester_name'] ?? '').' '.($po['requester_surname'] ?? '-')) ?></td>
<td><?= h($po['client_name'] ?? '-') ?></td>
<td><?= h($po['project_name'] ?? '-') ?></td>
<td><?= h($po['wh_name'] ?? '-') ?></td>
<td><?= h($po['status']) ?></td>
<td><?= h($po['order_date']) ?></td>
<td><?= h($po['expected_date'] ?? '-') // Usar expected_date ?></td>
<td class="text-end">
    <a class="btn btn-sm btn-outline-primary" href="work_order_view.php?id=<?= h($po['id']) ?>" title="Ver Detalles"><i class="bi bi-eye"></i></a>
</td>
</tr>
<?php endforeach; if (empty($rows)): ?>
    <tr><td colspan="9" class="text-center text-muted">No hay órdenes de trabajo.</td></tr>
<?php endif; ?>
</tbody></table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>