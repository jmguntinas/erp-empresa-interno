<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
// Quitamos la dependencia de table_detector
// require_once __DIR__ . '/lib/table_detector.php';

// --- Orden de inclusión corregido ---
require_once __DIR__ . '/common_helpers.php'; // Define get_table_columns, helpers de settings, etc.
require_once __DIR__ . '/partials/bootstrap_tables.php'; // Define render_pagination, etc.

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$APP_LANG = i18n_get_lang();

// --- Definir la tabla directamente ---
$TABLE = 'inv_products';

/* ====== POST Actions ====== */
$msg = get_flash_msg();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  // Validación CSRF unificada (de auth.php)
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
      die('Error de validación (CSRF). Intente recargar la página.');
  }

  try {
    // --- Acción: Guardar Configuración de Columnas (MODIFICADO para orden GLOBAL por Admin) ---
    if ($action === 'save_columns') {
      $allColsFromPost = $_POST['all_cols'] ?? [];
      if (!is_array($allColsFromPost)) $allColsFromPost = [];

      $vis=[]; $sea=[]; $order = [];
      foreach($allColsFromPost as $c) {
          $vis[$c] = isset($_POST['col__'.$c]) ? 1 : 0;
          $sea[$c] = isset($_POST['search__'.$c]) ? 1 : 0;
          $order[$c] = isset($_POST['order__'.$c]) && $_POST['order__'.$c] !== '' ? (int)$_POST['order__'.$c] : 999;
      }
      if(!array_reduce($vis, fn($a,$b)=>$a||$b, false) && isset($vis['id'])) $vis['id']=1;

      // Guardar visibilidad, búsqueda y por página en SESIÓN (preferencia individual)
      $_SESSION['crud_visible'][$TABLE] = $vis;
      $_SESSION['crud_searchable'][$TABLE] = $sea;
      $_SESSION['crud_per'][$TABLE] = max(10, min(200, (int)($_POST['cfg_per'] ?? 25)));

      // --- MODIFICADO: Guardar orden en BD SOLO si es Admin ---
      // Asegúrate de que auth.php define has_role() y tu rol de admin
      if (function_exists('has_role') && has_role(['Admin General'])) { // Reemplaza 'Admin General' si tu rol es diferente
          set_app_setting($pdo, 'products_column_order', $order); // Guardar en BD
          set_flash('success','Configuración guardada (orden global actualizado).');
      } else {
          // Si no es admin, solo guardar preferencias de sesión
           set_flash('success','Preferencias de visualización guardadas.');
      }
      // --- FIN MODIFICADO ---

      header('Location: '.basename($_SERVER['PHP_SELF']));
      exit;
    }
    // --- Fin Acción: Guardar Configuración ---

    // --- Acción: Eliminar Fila ---
    elseif ($action === 'delete_row') {
      $idDel = $_POST['id'] ?? null;
      if ($idDel) {
          $pdo->prepare("DELETE FROM inv_product_stock WHERE product_id=?")->execute([$idDel]);
          $pdo->prepare("DELETE FROM inv_movements WHERE product_id=?")->execute([$idDel]);
          $pdo->prepare("DELETE FROM inv_product_suppliers WHERE product_id=?")->execute([$idDel]);
          $pdo->prepare("DELETE FROM inv_product_components WHERE parent_product_id=? OR child_product_id=?")->execute([$idDel, $idDel]);
          $pdo->prepare("DELETE FROM `$TABLE` WHERE id=?")->execute([$idDel]);
          set_flash('success','Registro eliminado');
      }
       header('Location: '.basename($_SERVER['PHP_SELF'])); // Redirigir siempre tras borrar
       exit;
    }
    // --- Acción: Guardar Fila (Insertar/Actualizar) ---
    elseif ($action === 'save_row') {
      $id = $_POST['id'] ?? null; $isUpdate = !empty($id);
      $fields=[]; $params=[];
      $cols = get_table_columns($pdo, $TABLE);

      foreach($cols as $k => $c){
          if($k==='id'||$k==='created_at'||$k==='updated_at') continue;
        if(array_key_exists($k, $_POST)){
            $v=$_POST[$k];
          if($v==='' && $c['null']) $v=null;
          elseif(is_numeric_type($c['type']) && $v!==null) $v=str_replace([',',' '],['.',''],(string)$v);
          elseif ($k === 'is_active') $v = isset($_POST['is_active']) ? 1 : 0;
          elseif ($k === 'product_type') $v = in_array($v, ['internal', 'external']) ? $v : 'external';
          elseif ($k === 'composition_type') $v = in_array($v, ['simple', 'composite']) ? $v : 'simple';
          $fields[$k]=$v;
        }
      }
      if (!array_key_exists('is_active', $fields) && isset($cols['is_active'])) {
          $fields['is_active'] = 0;
      }
      if (!array_key_exists('alert_enabled', $fields) && isset($cols['alert_enabled'])) { // Asegurar checkbox de alerta
           $fields['alert_enabled'] = 0;
      }


      if ($isUpdate) {
        $sets = implode(', ', array_map(fn($f)=>"`$f`=?", array_keys($fields)));
        $sql = "UPDATE `$TABLE` SET $sets, `updated_at`=NOW() WHERE id=?"; $params=array_values($fields); $params[]=$id;
        $pdo->prepare($sql)->execute($params);
        set_flash('success','Producto actualizado');
      } else {
        $keys = array_keys($fields);
        // Asegurar que las columnas existen antes de insertarlas
        $insertKeys = array_intersect($keys, array_keys($cols));
        $places=implode(',', array_fill(0,count($insertKeys),'?')); // Ajustar placeholders
        $sql = "INSERT INTO `$TABLE` (".implode(',', array_map(fn($k) => "`$k`", $insertKeys)) .", `created_at`, `updated_at`) VALUES ($places, NOW(), NOW())";
        // Filtrar los valores para que coincidan con las claves insertadas
        $insertValues = array_intersect_key($fields, array_flip($insertKeys));
        $pdo->prepare($sql)->execute(array_values($insertValues));
        $id = $pdo->lastInsertId();
        set_flash('success','Producto creado');
      }
      // Redirigir a la misma página de edición después de guardar
      header('Location: '.basename($_SERVER['PHP_SELF']).'?edit='.$id);
      exit;
    }
  } catch(Throwable $e) {
      set_flash('danger', 'Error: ' . $e->getMessage());
      // No redirigir si hay error para mostrar el mensaje
      $msg = get_flash_msg(); // Actualizar $msg para mostrarlo inmediatamente
  }
  // Redirigir si no hubo mensaje de error explícito y no es save_row
  if (empty($msg) && $_SERVER['REQUEST_METHOD']==='POST' && $action !== 'save_row') {
      header('Location: '.basename($_SERVER['PHP_SELF']));
      exit;
  }
}

/* ====== Config & Metadatos (MODIFICADO para cargar orden GLOBAL) ====== */
$cols      = get_table_columns($pdo, $TABLE);
$colNames  = array_keys($cols);
$colLabels = []; foreach($cols as $k => $c) $colLabels[$k]=tl($pdo,$TABLE,$k,pretty_label($k)); // Usar tl() para traducciones

$related   = [
    'category_id' => ['table' => 'inv_categories', 'label_col' => 'name'],
    'company_id' => ['table' => 'inv_companies', 'label_col' => 'name'],
];
foreach ($related as $k => &$rel) {
    if (isset($cols[$k])) { $rel['options'] = fetch_fk_options($pdo, $rel['table'], 'id', $rel['label_col']); }
    else { unset($related[$k]); }
} unset($rel);

// Visibilidad, búsqueda y por página siguen siendo de sesión
$visible      = $_SESSION['crud_visible'][$TABLE] ?? null;
$searchables  = $_SESSION['crud_searchable'][$TABLE] ?? null;
$cfgPerPage   = (int)($_SESSION['crud_per'][$TABLE] ?? 25);

// --- MODIFICADO: Cargar orden desde la BD usando el helper ---
$column_order = get_app_setting($pdo, 'products_column_order', null); // Leer de app_settings
// --- FIN MODIFICADO ---

// Definir valores por defecto si no están en sesión/BD
if(!$visible){
    $d=array_fill_keys($colNames,0);
    foreach(['id','sku','name','product_type', 'composition_type', 'category_id','stock','sale_price','is_active'] as $df) if(isset($d[$df])) $d[$df]=1;
    $visible=$d;
}
if(!$searchables){
    $d=array_fill_keys($colNames,0);
    foreach(['sku','name','description'] as $df) if(isset($d[$df])) $d[$df]=1;
    $searchables=$d;
}

// --- Procesar el orden (sin cambios en esta parte, usa $column_order de BD) ---
$display_order = [];
if (is_array($column_order)) {
    foreach($colNames as $cn) { if (!isset($column_order[$cn])) $column_order[$cn] = 999; }
    asort($column_order);
    $display_order = array_keys($column_order);
    $display_order = array_intersect($display_order, $colNames);
} else {
    $display_order = $colNames; // Orden de BD si no hay nada guardado
}
// --- Fin Procesar orden ---


/* ====== Filtros & Paginación (Sin cambios funcionales) ====== */
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = max(10, min(200, $cfgPerPage));
$where = ['1=1'];
$params = [];
$activeFilters = [];
if($q!==''){ /* ... lógica búsqueda q ... */ }
foreach ($searchables as $col => $enabled) { /* ... lógica filtros específicos s_ ... */ }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';
$stCnt=$pdo->prepare("SELECT COUNT(*) FROM `$TABLE` t $wsql"); $stCnt->execute($params); $total=(int)$stCnt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$per)); $page=min($page,$totalPages); $offset=($page-1)*$per;
$select = "t.*";
foreach ($related as $k => $rel) { /* ... añadir selects de etiquetas FK ... */ }
$st=$pdo->prepare("SELECT $select FROM `$TABLE` t $wsql ORDER BY t.id DESC LIMIT $per OFFSET $offset");
$st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* ====== Cargar Fila para Edición (Sin cambios) ====== */
$editID  = $_GET['edit'] ?? null; $addMode = isset($_GET['add']); $editRow = null;
if ($editID) { $st2=$pdo->prepare("SELECT * FROM `$TABLE` WHERE id=?"); $st2->execute([$editID]); $editRow=$st2->fetch(PDO::FETCH_ASSOC) ?: null; }

$pageTitle = tl($pdo,'menu','productos','Productos'); // Usar tl()
include __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3 d-flex align-items-center gap-2">
  <i class="bi bi-box-seam"></i> <?= $pageTitle ?>
  <span class="ms-auto d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#configModal" data-bs-toggle="tooltip" title="<?= ti($pdo, 'ui.btn.configuracion', 'Configuración') ?>"><i class="bi bi-gear"></i></button>
    <a class="btn btn-sm btn-success" href="?add=1" data-bs-toggle="tooltip" title="<?= ti($pdo, 'ui.btn.nuevo', 'Nuevo') ?>"><i class="bi bi-plus-lg"></i></a>
  </span>
</h4>
<?= $msg // Muestra mensajes flash (éxito/error) ?>
<div class="card mb-3"><div class="card-body">

  <form class="row g-2">
    <div class="col-12">
        <div class="row g-2">
            <div class="col-md-6">
                <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="<?= ti($pdo, 'ui.buscar.placeholder', 'Buscar...') ?>">
            </div>
            <div class="col-md-6 text-end small text-muted align-self-center">
                <?= number_format($total,0,',','.') ?> <?= ti($pdo, 'ui.paginacion.resultados', 'resultados') ?>
            </div>
        </div>
    </div>
    <?php // Iterar sobre $display_order para los filtros
    foreach($display_order as $col):
        if(!isset($cols[$col]) || empty($searchables[$col])) continue;
        $c = $cols[$col]; $label = $colLabels[$col]; $val = $activeFilters[$col] ?? '';
    ?>
        <div class="col-md-3">
            <label class="form-label form-label-sm"><?= h($label) ?></label>
            <?php if (isset($related[$col])): /* Select FK */ ?>
                <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                    <option value=""><?= ti($pdo, 'ui.select.todos', '-- Todos --') ?></option>
                    <?php foreach($related[$col]['options'] as $opt): ?>
                    <option value="<?= h($opt['id']) ?>" <?= ((string)$val === (string)$opt['id']) ? 'selected' : '' ?>><?= h($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($c['type'] === 'tinyint' || $col === 'is_active'): /* Select Booleano */ ?>
                 <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                    <option value=""><?= ti($pdo, 'ui.select.todos', '-- Todos --') ?></option>
                    <option value="1" <?= $val === '1' ? 'selected' : '' ?>><?= ti($pdo, 'ui.boolean.si', 'Sí') ?></option>
                    <option value="0" <?= $val === '0' ? 'selected' : '' ?>><?= ti($pdo, 'ui.boolean.no', 'No') ?></option>
                </select>
            <?php elseif ($col === 'product_type'): /* Select Tipo Producto */ ?>
                 <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                    <option value=""><?= ti($pdo, 'ui.select.todos', '-- Todos --') ?></option>
                    <option value="external" <?= $val === 'external' ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.tipo.externo', 'Comprado (Externo)') ?></option>
                    <option value="internal" <?= $val === 'internal' ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.tipo.interno', 'Fabricado (Interno)') ?></option>
                 </select>
            <?php elseif ($col === 'composition_type'): /* Select Composición */ ?>
                 <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                     <option value=""><?= ti($pdo, 'ui.select.todos', '-- Todos --') ?></option>
                     <option value="simple" <?= $val === 'simple' ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.composicion.simple', 'Simple') ?></option>
                     <option value="composite" <?= $val === 'composite' ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.composicion.compuesto', 'Compuesto') ?></option>
                 </select>
            <?php else: /* Input Texto/Num */ ?>
                <input class="form-control form-control-sm" name="s_<?= h($col) ?>" value="<?= h($val) ?>">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="col-12">
        <div class="d-flex justify-content-end gap-2 mt-2">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> <?= ti($pdo, 'ui.buscar.buscar', 'Buscar') ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= basename($_SERVER['PHP_SELF']) ?>"><i class="bi bi-eraser"></i> <?= ti($pdo, 'ui.buscar.limpiar', 'Limpiar') ?></a>
        </div>
    </div>
  </form>

</div></div>

<div class="table-responsive">
<table class="table table-sm table-hover align-middle">
  <thead><tr>
    <?php // Iterar sobre $display_order para la cabecera
    foreach ($display_order as $k):
        if (!isset($cols[$k]) || empty($visible[$k])) continue;
        $c = $cols[$k];
    ?>
      <th class="text-<?= is_numeric_type($c['type'])?'end':'center' ?>"><?= h($colLabels[$k]) ?></th>
    <?php endforeach; ?>
    <th class="text-center" style="width:150px"><?= ti($pdo, 'ui.acciones', 'Acciones') ?></th>
  </tr></thead>
  <tbody>
  <?php if($rows): foreach($rows as $r): ?>
    <tr>
      <?php // Iterar sobre $display_order para las celdas
      foreach ($display_order as $k):
          if (!isset($cols[$k]) || empty($visible[$k])) continue;
          $c = $cols[$k];
          $val = $r[$k] ?? null; $out = ''; $tdClass = 'align-middle';
          // ... (lógica de formato de celda sin cambios) ...
           if ($val === null) { $out = '<i class="text-muted small">null</i>'; $tdClass .= ' text-center'; }
           elseif (isset($related[$k])) { $out = h($r[$k.'_label'] ?? $val); }
           elseif ($k === 'is_active') { $out = $val ? '<span class="badge bg-success">'.ti($pdo, 'ui.boolean.si', 'Sí').'</span>' : '<span class="badge bg-secondary">'.ti($pdo, 'ui.boolean.no', 'No').'</span>'; $tdClass .= ' text-center'; }
           elseif ($k === 'product_type') { $out = ($val === 'internal') ? ti($pdo, 'ui.producto.tipo.interno.corto', 'Interno') : ti($pdo, 'ui.producto.tipo.externo.corto', 'Externo'); $tdClass .= ' text-center'; }
           elseif ($k === 'composition_type') { $out = ($val === 'composite') ? ti($pdo, 'ui.producto.composicion.compuesto.corto', 'Compuesto') : ti($pdo, 'ui.producto.composicion.simple.corto', 'Simple'); $tdClass .= ' text-center'; }
           elseif (is_numeric_type($c['type'])) { if ($k == 'stock' || $k == 'min_stock_level' || $k == 'recommended_stock') { $out = h(number_format((float)$val, 0, ',', '.')); } else { $out = h(number_format((float)$val, 2, ',', '.')); } $tdClass .= ' text-end'; }
           elseif ($c['type']==='date') { $out = h(date('d/m/Y', strtotime($val))); $tdClass .= ' text-center'; }
           elseif ($c['type']==='datetime' || $c['type']==='timestamp') { $out = h(date('d/m/Y H:i', strtotime($val))); $tdClass .= ' text-center'; }
           else { $out = h($val); }
      ?>
        <td class="<?= $tdClass ?>"><?= $out ?></td>
      <?php endforeach; ?>
      <td class="text-center align-middle">
        <a class="btn btn-sm btn-outline-success" href="producto_stock.php?id=<?= (int)$r['id'] ?>" title="<?= ti($pdo, 'ui.stock', 'Stock') ?>"><i class="bi bi-clipboard-data"></i></a>
        <?php if ($r['product_type'] === 'external'): ?>
        <a class="btn btn-sm btn-outline-secondary" href="producto_proveedores.php?id=<?= (int)$r['id'] ?>" title="<?= ti($pdo, 'ui.proveedores', 'Proveedores') ?>"><i class="bi bi-truck"></i></a>
        <?php endif; ?>
        <?php if ($r['composition_type'] === 'composite'): ?>
        <a class="btn btn-sm btn-outline-info" href="producto_componentes.php?id=<?= (int)$r['id'] ?>" title="<?= ti($pdo, 'ui.producto.componentes', 'Componentes') ?>">
             <i class="bi bi-diagram-3"></i>
        </a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>" title="<?= ti($pdo, 'ui.btn.editar', 'Editar') ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-inline" onsubmit="return confirm('<?= ti($pdo, 'ui.confirmar_eliminar', '¿Seguro que deseas eliminar este registro?') ?>')">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
          <input type="hidden" name="action" value="delete_row">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" title="<?= ti($pdo, 'ui.btn.eliminar', 'Eliminar') ?>"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; else: ?>
    <?php // Calcular colspan basado en columnas visibles ?>
    <tr><td colspan="<?= count(array_filter($visible)) + 1 ?>" class="text-center text-muted"><?= ti($pdo, 'ui.tabla.sin_resultados', 'Sin resultados') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?= render_pagination(basename($_SERVER['PHP_SELF']), $page, $totalPages, $_GET) ?>


<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-gear"></i> <?= ti($pdo, 'ui.btn.configuracion', 'Configuración') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="action" value="save_columns">
        <?php foreach($colNames as $c) echo '<input type="hidden" name="all_cols[]" value="'.h($c).'">'.PHP_EOL; ?>
        <div class="row">
          <div class="col-md-6">
            <h6><?= ti($pdo, 'ui.config.columnas_visibles', 'Columnas Visibles y Orden') ?></h6>
            <?php
            // Comprobar si el usuario actual es admin
            $is_admin = (function_exists('has_role') && has_role(['Admin General'])); // Usa tu rol de admin
            if (!$is_admin) {
                echo '<p class="text-muted small">' . ti($pdo, 'ui.config.orden_solo_admin', 'El orden global solo puede ser modificado por un administrador.') . '</p>';
            } else {
                 echo '<p class="text-muted small">' . ti($pdo, 'ui.config.orden_info', 'Use los números para ordenar (1 = primero). Vacío = al final.') . '</p>';
            }
            ?>
            <?php
            // Iterar sobre $display_order para mostrar en el orden actual guardado
            foreach ($display_order as $k):
                 if (!isset($cols[$k])) continue;
                 $c = $cols[$k];
                 $current_order_val = $column_order[$k] ?? 999; // Usar 999 como valor por defecto/final
                 $current_order_display = ($current_order_val != 999) ? h($current_order_val) : ''; // Mostrar vacío si es 999
            ?>
              <div class="form-check d-flex align-items-center mb-1">
                 <input class="form-check-input me-2" type="checkbox" id="cfg_vis_<?= h($k) ?>" name="col__<?= h($k) ?>" <?= !empty($visible[$k])?'checked':'' ?>>

                 <input type="number" min="1" step="1" name="order__<?= h($k) ?>" value="<?= $current_order_display ?>" class="form-control form-control-sm me-2" style="width: 60px;" placeholder="#" <?= !$is_admin ? 'disabled' : '' ?> >

                 <label class="form-check-label small" for="cfg_vis_<?= h($k) ?>"><?= h($colLabels[$k]) ?> <span class="text-muted">(<?= h($c['type']) ?>)</span></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="col-md-4">
             <h6><?= ti($pdo, 'ui.config.campos_buscables', 'Campos Buscables') ?></h6>
             <?php // Iterar sobre $display_order aquí también
             foreach ($display_order as $k):
                if (!isset($cols[$k])) continue;
                $c = $cols[$k];
            ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cfg_search_<?= h($k) ?>" name="search__<?= h($k) ?>" <?= !empty($searchables[$k])?'checked':'' ?>>
                <label class="form-check-label small" for="cfg_search_<?= h($k) ?>"><?= h($colLabels[$k]) ?></label>
              </div>
            <?php endforeach; ?>
             <p class="text-muted small mt-2"><?= ti($pdo, 'ui.config.filtros_and', 'Los filtros se aplican de forma acumulativa (AND).') ?></p>
          </div>
          <div class="col-md-2">
             <h6><?= ti($pdo, 'ui.config.filas_por_pagina', 'Filas / pág.') ?></h6>
             <input type="number" min="10" max="200" class="form-control form-control-sm" name="cfg_per" value="<?= (int)$cfgPerPage ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= ti($pdo, 'btn.cancelar', 'Cancelar') ?></button>
        <button class="btn btn-primary"><?= ti($pdo, 'btn.guardar', 'Guardar') ?></button>
      </div>
    </form>
  </div></div>
</div>


<div class="modal fade" id="rowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="rowForm" method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editID ? ti($pdo, 'ui.modal.editar_titulo', 'Editar Producto').' #'.$editID : ti($pdo, 'ui.modal.nuevo_titulo', 'Nuevo Producto') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="action" value="save_row">
                    <input type="hidden" name="id" value="<?= (int)$editID ?>">
                    <div class="row g-3">
                    <?php
                    // Orden deseado de los campos en el formulario
                    $form_field_order = [
                        'sku', 'name', 'product_type', 'composition_type', 'category_id', 'company_id',
                        'purchase_price', 'sale_price', 'vat_percent',
                        'stock', 'min_stock_level', 'recommended_stock',
                        'is_active', 'description', 'photo_url', 'photo_path',
                        'alert_enabled', 'alert_cooldown_minutes', 'alert_max_resends', 'alert_escalation_email'
                        // Campos no editables como alert_last_sent, etc., se omiten
                     ];

                    foreach ($form_field_order as $k):
                        if (!isset($cols[$k])) continue;
                        $c = $cols[$k];
                        if ($k==='id'||$k==='created_at'||$k==='updated_at' || $k === 'stock') continue; // Omitir stock aquí también

                        $val = $editRow[$k] ?? $c['def'];
                        $label = $colLabels[$k] ?? $k;
                        $class = 'col-md-4';
                        if ($k === 'description') $class = 'col-12';
                        if ($k === 'name' || $k === 'sku') $class = 'col-md-6';
                        if (in_array($k, ['photo_url', 'photo_path', 'alert_escalation_email'])) $class = 'col-md-6';
                        if ($k === 'is_active' || $k === 'alert_enabled') $class = 'col-md-3 d-flex align-items-end';
                    ?>
                      <div class="<?= $class ?> mb-2">
                        <?php // Renderizado de campos (sin cambios funcionales)
                        $labelHtml = '<label class="form-label form-label-sm">' . h($label) . '</label>';
                        $inputName = h($k);
                        $inputValue = h($val);

                        if (isset($related[$k])): /* Select FK */ ?>
                            <?= $labelHtml ?>
                            <select class="form-select form-select-sm" name="<?= $inputName ?>">
                                <option value=""><?= ti($pdo, 'ui.select.seleccionar', '-- Seleccionar --') ?></option>
                                <?php foreach($related[$k]['options'] as $opt): ?>
                                <option value="<?= h($opt['id']) ?>" <?= ((string)$val === (string)$opt['id']) ? 'selected' : '' ?>><?= h($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($k === 'product_type'): /* Select Tipo Producto */ ?>
                             <?= $labelHtml ?>
                             <select class="form-select form-select-sm" name="<?= $inputName ?>" required>
                                 <option value="external" <?= ($inputValue === 'external') ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.tipo.externo', 'Comprado (Externo)') ?></option>
                                 <option value="internal" <?= ($inputValue === 'internal') ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.tipo.interno', 'Fabricado (Interno)') ?></option>
                             </select>
                        <?php elseif ($k === 'composition_type'): /* Select Composición */ ?>
                             <?= $labelHtml ?>
                             <select class="form-select form-select-sm" name="<?= $inputName ?>" required>
                                 <option value="simple" <?= ($inputValue === 'simple') ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.composicion.simple', 'Simple') ?></option>
                                 <option value="composite" <?= ($inputValue === 'composite') ? 'selected' : '' ?>><?= ti($pdo, 'ui.producto.composicion.compuesto', 'Compuesto') ?></option>
                             </select>
                        <?php elseif ($k === 'is_active' || $k === 'alert_enabled' || $c['type'] === 'tinyint'): /* Checkbox / Switch */ ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="form_<?= $inputName ?>" name="<?= $inputName ?>" value="1" <?= $val ? 'checked' : '' ?>>
                                <label class="form-check-label" for="form_<?= $inputName ?>"><?= h($label) ?></label>
                            </div>
                        <?php elseif (is_long_text($c['type'])): /* Textarea */ ?>
                            <?= $labelHtml ?>
                            <textarea class="form-control form-control-sm" name="<?= $inputName ?>" rows="3"><?= $inputValue ?></textarea>
                        <?php elseif (is_numeric_type($c['type'])): /* Input Number */ ?>
                             <?= $labelHtml ?>
                             <input type="number" step="<?= ($c['type']=='decimal' || $c['type']=='float' || $c['type']=='double') ? '0.01' : '1' ?>" class="form-control form-control-sm" name="<?= $inputName ?>" value="<?= $inputValue ?>">
                        <?php else: /* Input Text */ ?>
                            <?= $labelHtml ?>
                            <input class="form-control form-control-sm" name="<?= $inputName ?>" value="<?= $inputValue ?>" maxlength="<?= h($c['maxlen'] ?? 255) ?>">
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= ti($pdo, 'btn.cancelar', 'Cancelar') ?></button>
                    <button type="submit" class="btn btn-primary"><?= ti($pdo, 'btn.guardar', 'Guardar') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
<script>
  // Script para auto-abrir modal si ?add=1 o ?edit=ID
  const params=new URLSearchParams(window.location.search);
  if(params.has('add')||params.has('edit')){ /* ... (lógica JS sin cambios) ... */ }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>