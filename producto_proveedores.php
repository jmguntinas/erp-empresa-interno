<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
// --- MODIFICADO: Incluir i18n y helpers ---
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/common_helpers.php'; // Para h(), set_flash(), get_flash_msg()
// --- FIN MODIFICADO ---

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$APP_LANG = i18n_get_lang();

$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    set_flash('danger', ti($pdo, 'error.producto_no_especificado', 'Producto no especificado.'));
    header('Location: productos.php');
    exit;
}

// Obtener detalles del producto (para mostrar nombre/sku y verificar tipo)
$stmtProduct = $pdo->prepare("SELECT id, sku, name, product_type FROM inv_products WHERE id = ?");
$stmtProduct->execute([$product_id]);
$product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    set_flash('danger', ti($pdo, 'error.producto_no_encontrado', 'Producto no encontrado.'));
    header('Location: productos.php');
    exit;
}

// Opcional: Redirigir si el producto no es 'external'
// if ($product['product_type'] !== 'external') {
//     set_flash('info', ti($pdo, 'info.producto_no_externo_proveedores', 'Los productos internos no tienen proveedores externos.'));
//     header('Location: productos.php?edit=' . $product_id);
//     exit;
// }


$msg = get_flash_msg(); // Obtener mensajes flash antes de procesar POST

/* ====== Lógica POST (Añadir/Editar/Eliminar/Marcar Principal) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('danger', ti($pdo, 'error.csrf', 'Error de validación (CSRF). Intente recargar la página.'));
        // Redirigir inmediatamente en caso de fallo CSRF
        header('Location: producto_proveedores.php?id=' . $product_id);
        exit;
    } else {
        $action = $_POST['action'] ?? '';
        $supplier_assoc_id = (int)($_POST['supplier_assoc_id'] ?? 0); // ID de la fila en inv_product_suppliers (para editar)
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);

        try {
            // --- Acción: Guardar (Añadir o Editar) ---
            if ($action === 'save_supplier') {
                $supplier_ref = trim($_POST['supplier_ref'] ?? '');
                $unit_cost = (float)str_replace(',', '.', $_POST['unit_cost'] ?? '0.0'); // Permitir coma decimal
                $discount = (float)str_replace(',', '.', $_POST['discount'] ?? '0.0');

                if (!$supplier_id) throw new Exception(ti($pdo, 'error.selecciona_proveedor', 'Debe seleccionar un proveedor.'));
                if ($unit_cost < 0) throw new Exception(ti($pdo, 'error.coste_negativo', 'El coste no puede ser negativo.'));
                if ($discount < 0 || $discount > 100) throw new Exception(ti($pdo, 'error.descuento_invalido', 'El descuento debe estar entre 0 y 100.'));

                // Comprobar si ya existe la combinación producto-proveedor (excepto si estamos editando esa misma entrada)
                $stmtExists = $pdo->prepare("SELECT id FROM inv_product_suppliers WHERE product_id = ? AND supplier_id = ? AND id != ?");
                $stmtExists->execute([$product_id, $supplier_id, $supplier_assoc_id]);
                if ($stmtExists->fetch()) {
                    throw new Exception(ti($pdo, 'error.proveedor_ya_vinculado', 'Este proveedor ya está vinculado a este producto.'));
                }

                if ($supplier_assoc_id > 0) {
                    // --- Editar Vinculación Existente ---
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE inv_product_suppliers SET supplier_id = ?, unit_cost = ?, discount = ?, supplier_ref = ? WHERE id = ? AND product_id = ?"
                    );
                    $stmtUpdate->execute([$supplier_id, $unit_cost, $discount, $supplier_ref, $supplier_assoc_id, $product_id]);
                    set_flash('success', ti($pdo, 'ok.proveedor_actualizado', 'Proveedor actualizado correctamente.'));
                } else {
                    // --- Añadir Nueva Vinculación ---
                    $stmtInsert = $pdo->prepare(
                        "INSERT INTO inv_product_suppliers (product_id, supplier_id, unit_cost, discount, supplier_ref, is_primary) VALUES (?, ?, ?, ?, ?, 0)" // is_primary a 0 por defecto
                    );
                    $stmtInsert->execute([$product_id, $supplier_id, $unit_cost, $discount, $supplier_ref]);
                    set_flash('success', ti($pdo, 'ok.proveedor_anadido', 'Proveedor vinculado correctamente.'));
                }
            }

            // --- Acción: Eliminar Vinculación ---
            elseif ($action === 'delete_supplier') {
                 if ($supplier_assoc_id > 0) {
                    $stmtDelete = $pdo->prepare("DELETE FROM inv_product_suppliers WHERE id = ? AND product_id = ?");
                    $stmtDelete->execute([$supplier_assoc_id, $product_id]);
                    set_flash('success', ti($pdo, 'ok.proveedor_desvinculado', 'Proveedor desvinculado correctamente.'));
                 }
            }

            // --- Acción: Marcar como Principal ---
            elseif ($action === 'set_primary') {
                if ($supplier_assoc_id > 0) {
                    $pdo->beginTransaction();
                    // 1. Quitar la marca de principal a todos los demás para este producto
                    $stmtUnset = $pdo->prepare("UPDATE inv_product_suppliers SET is_primary = 0 WHERE product_id = ? AND id != ?");
                    $stmtUnset->execute([$product_id, $supplier_assoc_id]);
                    // 2. Marcar el seleccionado como principal
                    $stmtSet = $pdo->prepare("UPDATE inv_product_suppliers SET is_primary = 1 WHERE id = ? AND product_id = ?");
                    $stmtSet->execute([$supplier_assoc_id, $product_id]);
                    $pdo->commit();
                    set_flash('success', ti($pdo, 'ok.proveedor_principal_marcado', 'Proveedor marcado como principal.'));
                }
            }

            // Redirigir siempre después de una acción POST exitosa para evitar reenvío
            header('Location: producto_proveedores.php?id=' . $product_id);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack(); // Deshacer si hubo error en transacción
            set_flash('danger', ti($pdo, 'error.generico_guardar', 'Error: ') . h($e->getMessage()));
            // No redirigir para mantener los datos del formulario (si aplica) y mostrar el error
            $msg = get_flash_msg(); // Actualizar $msg para mostrarlo inmediatamente
        }
    }
}


/* ====== Obtener Lista Actual de Proveedores Vinculados ====== */
$stmtLinked = $pdo->prepare(
    "SELECT ps.id, ps.supplier_id, ps.unit_cost, ps.discount, ps.supplier_ref, ps.is_primary, s.name as supplier_name
     FROM inv_product_suppliers ps
     JOIN inv_suppliers s ON ps.supplier_id = s.id
     WHERE ps.product_id = ?
     ORDER BY ps.is_primary DESC, s.name ASC" // Mostrar principal primero
);
$stmtLinked->execute([$product_id]);
$linkedSuppliers = $stmtLinked->fetchAll(PDO::FETCH_ASSOC);

/* ====== Obtener Lista de Todos los Proveedores (para el select de añadir) ====== */
$allSuppliers = $pdo->query("SELECT id, name FROM inv_suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = ti($pdo, 'ui.proveedores.titulo_pagina', 'Proveedores del Producto');
include __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        <i class="bi bi-truck me-2"></i>
        <?= $pageTitle ?>: <?= h($product['name']) ?> (<?= h($product['sku']) ?>)
    </h4>
    <a href="productos.php?edit=<?= $product_id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= ti($pdo, 'btn.volver_producto', 'Volver al Producto') ?>
    </a>
</div>

<?= $msg ?>

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><?= ti($pdo, 'ui.proveedores.vinculados', 'Proveedores Vinculados') ?></div>
            <div class="card-body">
                <?php if (empty($linkedSuppliers)): ?>
                    <p class="text-muted"><?= ti($pdo, 'ui.proveedores.no_vinculados', 'Este producto aún no tiene proveedores vinculados.') ?></p>
                <?php else: ?>
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th> <?php // Columna para estrella de principal ?>
                                <th><?= ti($pdo, 'ui.proveedores.tabla.nombre', 'Proveedor') ?></th>
                                <th><?= ti($pdo, 'ui.proveedores.tabla.ref', 'Ref. Proveedor') ?></th>
                                <th class="text-end"><?= ti($pdo, 'ui.proveedores.tabla.coste', 'Coste Unit.') ?></th>
                                <th class="text-end"><?= ti($pdo, 'ui.proveedores.tabla.dto', 'Dto. %') ?></th>
                                <th class="text-end"><?= ti($pdo, 'ui.acciones', 'Acciones') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linkedSuppliers as $sup): ?>
                            <tr id="supplier-row-<?= (int)$sup['id'] ?>" data-supplier-assoc-id="<?= (int)$sup['id'] ?>" data-supplier-id="<?= (int)$sup['supplier_id'] ?>" data-ref="<?= h($sup['supplier_ref']) ?>" data-cost="<?= h(number_format((float)$sup['unit_cost'], 4, '.', '')) ?>" data-discount="<?= h(number_format((float)$sup['discount'], 2, '.', '')) ?>">
                                <td class="text-center">
                                    <?php if ($sup['is_primary']): ?>
                                        <i class="bi bi-star-fill text-warning" title="<?= ti($pdo, 'tooltip.proveedor_principal', 'Proveedor principal') ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($sup['supplier_name']) ?></td>
                                <td><?= h($sup['supplier_ref'] ?: '-') ?></td>
                                <td class="text-end"><?= number_format((float)$sup['unit_cost'], 4, ',', '.') ?> €</td>
                                <td class="text-end"><?= number_format((float)$sup['discount'], 2, ',', '.') ?> %</td>
                                <td class="text-end text-nowrap">
                                    <?php if (!$sup['is_primary']): // Botón para marcar como principal solo si no lo es ya ?>
                                    <form method="post" class="d-inline" title="<?= ti($pdo, 'tooltip.marcar_principal', 'Marcar como principal') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                                        <input type="hidden" name="action" value="set_primary">
                                        <input type="hidden" name="supplier_assoc_id" value="<?= (int)$sup['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-star"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary btn-edit-supplier" title="<?= ti($pdo, 'btn.editar', 'Editar') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('<?= ti($pdo, 'ui.confirmar_desvincular', '¿Desvincular este proveedor del producto?') ?>')">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                                        <input type="hidden" name="action" value="delete_supplier">
                                        <input type="hidden" name="supplier_assoc_id" value="<?= (int)$sup['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= ti($pdo, 'btn.eliminar', 'Eliminar') ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header" id="form-card-header"><?= ti($pdo, 'ui.proveedores.anadir_vincular', 'Añadir / Vincular Proveedor') ?></div>
            <div class="card-body">
                <form id="supplier-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                    <input type="hidden" name="action" value="save_supplier">
                    <input type="hidden" name="supplier_assoc_id" id="supplier_assoc_id" value="0"> <?php // ID para editar ?>

                    <div class="mb-3">
                        <label for="supplier_id" class="form-label"><?= ti($pdo, 'ui.proveedores.seleccionar', 'Seleccionar Proveedor') ?></label>
                        <select id="supplier_id" name="supplier_id" class="form-select" required>
                           <option value=""><?= ti($pdo, 'ui.select.seleccionar', '-- Seleccionar --') ?></option>
                           <?php foreach ($allSuppliers as $sup): ?>
                               <option value="<?= (int)$sup['id'] ?>"><?= h($sup['name']) ?></option>
                           <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="unit_cost" class="form-label"><?= ti($pdo, 'ui.proveedores.coste_compra', 'Coste Compra Unit.') ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" inputmode="decimal" id="unit_cost" name="unit_cost" class="form-control" value="0.0000">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="discount" class="form-label"><?= ti($pdo, 'ui.proveedores.descuento', 'Descuento %') ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" inputmode="decimal" id="discount" name="discount" class="form-control" value="0.00">
                                 <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="supplier_ref" class="form-label"><?= ti($pdo, 'ui.proveedores.ref_proveedor', 'Ref. Proveedor (Opcional)') ?></label>
                        <input type="text" id="supplier_ref" name="supplier_ref" class="form-control">
                    </div>


                    <button type="submit" class="btn btn-primary" id="save-button">
                        <i class="bi bi-save"></i> <?= ti($pdo, 'btn.guardar_proveedor', 'Guardar Proveedor') ?>
                    </button>
                    <button type="button" class="btn btn-secondary" id="cancel-edit-button" style="display: none;">
                        <?= ti($pdo, 'btn.cancelar', 'Cancelar Edición') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('supplier-form');
    const assocIdInput = document.getElementById('supplier_assoc_id');
    const supplierSelect = document.getElementById('supplier_id');
    const costInput = document.getElementById('unit_cost');
    const discountInput = document.getElementById('discount');
    const refInput = document.getElementById('supplier_ref');
    const saveButton = document.getElementById('save-button');
    const cancelButton = document.getElementById('cancel-edit-button');
    const formHeader = document.getElementById('form-card-header');
    const editButtons = document.querySelectorAll('.btn-edit-supplier');

    // Función para resetear el formulario al modo "Añadir"
    function resetForm() {
        form.reset(); // Limpia los campos
        assocIdInput.value = '0'; // Pone el ID de edición a 0
        supplierSelect.disabled = false; // Habilita el select de proveedor
        formHeader.textContent = "<?= ti($pdo, 'ui.proveedores.anadir_vincular', 'Añadir / Vincular Proveedor') ?>";
        saveButton.innerHTML = '<i class="bi bi-save"></i> <?= ti($pdo, 'btn.guardar_proveedor', 'Guardar Proveedor') ?>';
        cancelButton.style.display = 'none'; // Oculta el botón de cancelar edición
        // Quitar posible resaltado de fila
        document.querySelectorAll('.table-info').forEach(row => row.classList.remove('table-info'));
    }

    // Añadir listener a los botones de editar
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const assocId = row.dataset.supplierAssocId;
            const supplierId = row.dataset.supplierId;
            const ref = row.dataset.ref;
            const cost = row.dataset.cost;
            const discount = row.dataset.discount;

            // Rellenar el formulario
            assocIdInput.value = assocId;
            supplierSelect.value = supplierId;
            refInput.value = ref;
            // Usar comas para mostrar decimales
            costInput.value = cost.replace('.', ','); 
            discountInput.value = discount.replace('.', ',');

            // Cambiar estado del formulario a "Editar"
            supplierSelect.disabled = true; // No permitir cambiar el proveedor al editar, solo sus datos
            formHeader.textContent = "<?= ti($pdo, 'ui.proveedores.editar_vinculo', 'Editar Vínculo Proveedor') ?>";
            saveButton.innerHTML = '<i class="bi bi-save"></i> <?= ti($pdo, 'btn.actualizar_proveedor', 'Actualizar Proveedor') ?>';
            cancelButton.style.display = 'inline-block'; // Mostrar botón cancelar

            // Resaltar la fila que se está editando (opcional)
            document.querySelectorAll('.table-info').forEach(r => r.classList.remove('table-info'));
            row.classList.add('table-info');

            // Scroll al formulario (opcional)
            form.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Acción del botón Cancelar Edición
    cancelButton.addEventListener('click', resetForm);

});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>