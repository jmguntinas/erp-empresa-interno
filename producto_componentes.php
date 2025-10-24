<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/common_helpers.php'; // Incluye h(), set_flash(), get_flash_msg()
require_once __DIR__ . '/partials/bootstrap_tables.php'; // Para render_pagination si fuera necesario

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$APP_LANG = i18n_get_lang();

// Obtener ID del producto padre (compuesto)
$parent_product_id = (int)($_GET['id'] ?? 0);
if (!$parent_product_id) {
    set_flash('danger', ti($pdo, 'error.producto_no_especificado', 'Producto no especificado.'));
    header('Location: productos.php');
    exit;
}

// Obtener detalles del producto padre
$stmtParent = $pdo->prepare("SELECT id, sku, name, composition_type FROM inv_products WHERE id = ?");
$stmtParent->execute([$parent_product_id]);
$parentProduct = $stmtParent->fetch(PDO::FETCH_ASSOC);

if (!$parentProduct || $parentProduct['composition_type'] !== 'composite') {
    set_flash('danger', ti($pdo, 'error.producto_no_compuesto', 'El producto especificado no existe o no es un producto compuesto.'));
    header('Location: productos.php');
    exit;
}

$msg = get_flash_msg(); // Obtener mensajes flash

/* ====== Lógica POST (Añadir/Eliminar Componente) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = set_flash('danger', ti($pdo, 'error.csrf', 'Error de validación (CSRF). Intente recargar la página.'));
        // No redirigir aquí para no perder datos del formulario si falla
    } else {
        $action = $_POST['action'] ?? '';

        try {
            // --- Acción: Añadir Componente ---
            if ($action === 'add_component') {
                $child_product_id = (int)($_POST['child_product_id'] ?? 0);
                $quantity = (float)($_POST['quantity'] ?? 0);
                $unit = trim($_POST['unit'] ?? ''); // Unidad opcional

                if ($child_product_id > 0 && $quantity > 0) {
                    // Validar que el componente es un producto simple y existe
                    $stmtChildCheck = $pdo->prepare("SELECT composition_type FROM inv_products WHERE id = ?");
                    $stmtChildCheck->execute([$child_product_id]);
                    $childType = $stmtChildCheck->fetchColumn();

                    if ($childType === 'simple') {
                        // Comprobar si ya existe para evitar duplicados (podrías querer actualizar en lugar de fallar)
                        $stmtExists = $pdo->prepare("SELECT id FROM inv_product_components WHERE parent_product_id = ? AND child_product_id = ?");
                        $stmtExists->execute([$parent_product_id, $child_product_id]);
                        if ($stmtExists->fetch()) {
                             set_flash('warning', ti($pdo, 'error.componente_ya_existe', 'Este componente ya está añadido. Edite la cantidad si es necesario.'));
                        } else {
                            $stmtInsert = $pdo->prepare(
                                "INSERT INTO inv_product_components (parent_product_id, child_product_id, quantity, unit) VALUES (?, ?, ?, ?)"
                            );
                            $stmtInsert->execute([$parent_product_id, $child_product_id, $quantity, $unit ?: null]);
                            set_flash('success', ti($pdo, 'ok.componente_anadido', 'Componente añadido correctamente.'));
                        }
                    } else {
                        set_flash('danger', ti($pdo, 'error.componente_no_simple', 'El producto seleccionado no es un producto simple y no puede ser añadido como componente.'));
                    }
                } else {
                    set_flash('danger', ti($pdo, 'error.datos_componente_invalidos', 'Debe seleccionar un producto componente y especificar una cantidad mayor que cero.'));
                }
                header('Location: producto_componentes.php?id=' . $parent_product_id); // Redirigir para limpiar POST
                exit;
            }

            // --- Acción: Eliminar Componente ---
            elseif ($action === 'delete_component') {
                $component_id = (int)($_POST['component_id'] ?? 0); // ID de la fila en inv_product_components
                if ($component_id > 0) {
                    $stmtDelete = $pdo->prepare("DELETE FROM inv_product_components WHERE id = ? AND parent_product_id = ?");
                    $stmtDelete->execute([$component_id, $parent_product_id]);
                    if ($stmtDelete->rowCount() > 0) {
                         set_flash('success', ti($pdo, 'ok.componente_eliminado', 'Componente eliminado correctamente.'));
                    } else {
                         set_flash('warning', ti($pdo, 'error.componente_no_encontrado', 'No se encontró el componente a eliminar.'));
                    }
                }
                 header('Location: producto_componentes.php?id=' . $parent_product_id); // Redirigir
                 exit;
            }

             // --- Acción: Actualizar Cantidad (Ejemplo básico) ---
             elseif ($action === 'update_quantity') {
                $component_id = (int)($_POST['component_id'] ?? 0);
                $new_quantity = (float)($_POST['quantity'] ?? 0);
                 if ($component_id > 0 && $new_quantity > 0) {
                     $stmtUpdate = $pdo->prepare("UPDATE inv_product_components SET quantity = ? WHERE id = ? AND parent_product_id = ?");
                     $stmtUpdate->execute([$new_quantity, $component_id, $parent_product_id]);
                     set_flash('success', ti($pdo, 'ok.cantidad_actualizada', 'Cantidad actualizada.'));
                 } else {
                     set_flash('warning', ti($pdo, 'error.cantidad_invalida', 'Cantidad inválida.'));
                 }
                 header('Location: producto_componentes.php?id=' . $parent_product_id); // Redirigir
                 exit;
             }

        } catch (Throwable $e) {
            set_flash('danger', ti($pdo, 'error.generico_guardar', 'Error al guardar los cambios: ') . $e->getMessage());
            // No redirigir para poder ver el error
            $msg = get_flash_msg(); // Actualizar mensaje para mostrarlo inmediatamente
        }
    }
}

/* ====== Obtener Lista Actual de Componentes ====== */
$stmtComponents = $pdo->prepare(
    "SELECT pc.id, pc.quantity, pc.unit, p.id as child_id, p.sku, p.name 
     FROM inv_product_components pc
     JOIN inv_products p ON pc.child_product_id = p.id
     WHERE pc.parent_product_id = ?
     ORDER BY p.name ASC"
);
$stmtComponents->execute([$parent_product_id]);
$components = $stmtComponents->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = ti($pdo, 'ui.producto.componentes.titulo_pagina', 'Gestionar Componentes');
include __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        <i class="bi bi-diagram-3 me-2"></i> 
        <?= $pageTitle ?>: <?= h($parentProduct['name']) ?> (<?= h($parentProduct['sku']) ?>)
    </h4>
    <a href="productos.php?edit=<?= $parent_product_id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= ti($pdo, 'btn.volver_producto', 'Volver al Producto') ?>
    </a>
</div>

<?= $msg ?>

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><?= ti($pdo, 'ui.producto.componentes.lista_actual', 'Componentes Actuales') ?></div>
            <div class="card-body">
                <?php if (empty($components)): ?>
                    <p class="text-muted"><?= ti($pdo, 'ui.producto.componentes.no_hay', 'Este producto aún no tiene componentes definidos.') ?></p>
                <?php else: ?>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th><?= ti($pdo, 'ui.producto.componentes.tabla.sku', 'SKU') ?></th>
                                <th><?= ti($pdo, 'ui.producto.componentes.tabla.nombre', 'Nombre Componente') ?></th>
                                <th class="text-end"><?= ti($pdo, 'ui.producto.componentes.tabla.cantidad', 'Cantidad') ?></th>
                                <th><?= ti($pdo, 'ui.producto.componentes.tabla.unidad', 'Unidad') ?></th>
                                <th class="text-end"><?= ti($pdo, 'ui.acciones', 'Acciones') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($components as $comp): ?>
                            <tr>
                                <td><?= h($comp['sku']) ?></td>
                                <td><?= h($comp['name']) ?></td>
                                <td class="text-end">
                                    <?php /* Formulario inline para actualizar cantidad (ejemplo básico) */ ?>
                                    <form method="post" class="d-inline-flex align-items-center update-qty-form">
                                         <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                                         <input type="hidden" name="action" value="update_quantity">
                                         <input type="hidden" name="component_id" value="<?= (int)$comp['id'] ?>">
                                         <input type="number" step="any" name="quantity" value="<?= h($comp['quantity']) ?>" class="form-control form-control-sm text-end me-1" style="width: 80px;" required>
                                         <button type="submit" class="btn btn-sm btn-outline-success p-1" title="<?= ti($pdo, 'btn.actualizar', 'Actualizar') ?>"><i class="bi bi-check2"></i></button>
                                    </form>
                                </td>
                                <td><?= h($comp['unit'] ?: '-') ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('<?= ti($pdo, 'ui.confirmar_eliminar_componente', '¿Eliminar este componente?') ?>')">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                                        <input type="hidden" name="action" value="delete_component">
                                        <input type="hidden" name="component_id" value="<?= (int)$comp['id'] ?>">
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
            <div class="card-header"><?= ti($pdo, 'ui.producto.componentes.anadir', 'Añadir Componente') ?></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                    <input type="hidden" name="action" value="add_component">

                    <div class="mb-3">
                        <label for="component_product_search" class="form-label"><?= ti($pdo, 'ui.producto.componentes.buscar_simple', 'Buscar Producto Simple') ?></label>
                        <select id="component_product_search" name="child_product_id" class="form-select" data-placeholder="<?= ti($pdo, 'ui.buscar.placeholder.producto', 'Escribe SKU o nombre...') ?>" required>
                           <option></option> <?php // Necesario para Select2 placeholder ?>
                        </select>
                         <div class="form-text"><?= ti($pdo, 'ui.producto.componentes.buscar_info', 'Solo aparecerán productos marcados como "Simples".') ?></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="component_quantity" class="form-label"><?= ti($pdo, 'ui.producto.componentes.cantidad_necesaria', 'Cantidad Necesaria') ?></label>
                            <input type="number" step="any" id="component_quantity" name="quantity" class="form-control" value="1.0" required>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="component_unit" class="form-label"><?= ti($pdo, 'ui.producto.componentes.unidad_medida', 'Unidad (Opcional)') ?></label>
                            <input type="text" id="component_unit" name="unit" class="form-control" placeholder="<?= ti($pdo, 'ui.ejemplo.unidad', 'ej. kg, m, uds') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> <?= ti($pdo, 'btn.anadir_componente', 'Añadir Componente') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php // Incluir CSS y JS para Select2 (igual que en albaran_edit.php) ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#component_product_search').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#component_product_search').parent(), // Necesario si está dentro de un modal o contenedor complejo
        ajax: {
            // Usaremos una API PHP separada para buscar SOLO productos simples
            url: 'api/products_search.php?composition_type=simple', 
            dataType: 'json',
            delay: 250,
            data: function (params) {
              return { 
                  q: params.term, // Término de búsqueda
                  page: params.page || 1 
              };
            },
            processResults: function (data, params) {
              params.page = params.page || 1;
              return { 
                  results: data.results, // Select2 espera 'results'
                  pagination: {
                      more: (params.page * 10) < data.total_count // Asumiendo que la API devuelve 'total_count'
                  }
               };
            },
            cache: true
        },
        minimumInputLength: 2, // Empezar a buscar después de 2 caracteres
        templateResult: formatRepo, // Función para mostrar resultados
        templateSelection: formatRepoSelection // Función para mostrar selección
    });

    function formatRepo (repo) {
      if (repo.loading) { return repo.text; }
      // Mostrar SKU y Nombre en los resultados
      var $container = $(
        "<div class='select2-result-repository clearfix'>" +
          "<div class='select2-result-repository__meta'>" +
            "<div class='select2-result-repository__title'></div>" +
            "<div class='select2-result-repository__description'></div>" +
          "</div>" +
        "</div>"
      );
      $container.find(".select2-result-repository__title").text(repo.name || repo.text); // 'name' de nuestra API
      $container.find(".select2-result-repository__description").text(repo.sku); // 'sku' de nuestra API
      return $container;
    }

    function formatRepoSelection (repo) {
      // Mostrar solo el nombre una vez seleccionado
      return repo.name || repo.text;
    }

    // Opcional: Evitar envío de formulario al presionar Enter en el input de cantidad
    $('.update-qty-form input[name="quantity"]').on('keypress', function(e) {
        if (e.which === 13) { // 13 es el código de Enter
            e.preventDefault();
            $(this).closest('form').submit(); // Enviar solo este mini-formulario
        }
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>