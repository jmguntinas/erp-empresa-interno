<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/lib/table_detector.php';
require_once __DIR__ . '/partials/bootstrap_tables.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ==== Helpers ==== */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('current_db')) { /* ... helper ... */ } // Asumiendo que ya existen
if (!function_exists('is_numeric_type')) { /* ... helper ... */ }
if (!function_exists('is_text_type')) { /* ... helper ... */ }
if (!function_exists('is_long_text')) { /* ... helper ... */ }
if (!function_exists('pick_label_column')) { /* ... helper ... */ }
if (!function_exists('list_for_select')) { /* ... helper ... */ }
if (!function_exists('render_crud_form_field')) { /* ... helper ... */ }
if (!function_exists('render_crud_filters')) { /* ... helper ... */ }
if (!function_exists('get_flash_msg')) { function get_flash_msg($clear=true){ /* ... helper ... */ $m=$_SESSION['flash']??null; if($clear)unset($_SESSION['flash']); return $m?('<div class="alert alert-'.$m['type'].'">'.$m['msg'].'</div>'):''; } }
if (!function_exists('set_flash')) { function set_flash($t,$m){ $_SESSION['flash']=['type'=>$t,'msg'=>$m]; } }
if (!function_exists('csrf_token')) { function csrf_token(){ if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; } }
if (!function_exists('check_csrf_or_redirect')) { function check_csrf_or_redirect(){ $ok=isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']); if(!$ok){ set_flash('danger','CSRF inválido'); header('Location: '.basename($_SERVER['PHP_SELF'])); exit; } } }
if (!function_exists('list_tables')) { function list_tables(PDO $pdo): array { /* ... helper ... */ return []; } }
if (!function_exists('list_columns')) { function list_columns(PDO $pdo, string $table): array { /* ... helper ... */ return []; } }
if (!function_exists('get_foreign_keys')) { function get_foreign_keys(PDO $pdo, string $table): array { /* ... helper ... */ return []; } }
if (!function_exists('debounce')) { function debounce($fn,$ms){return function(...$args)use($fn,$ms){static $t;$t=microtime(true);$fn(...$args);};}} // Placeholder

/* ==== Configuración de la tabla ==== */
// --- MODIFICADO: Definir tabla principal y relacionadas ---
$table = 'inv_purchase_orders';
$cols = list_columns($pdo, $table);
$fks = get_foreign_keys($pdo, $table);
$related = [ // Mapeo FK => [tabla, label_col]
  'supplier_id' => ['inv_suppliers', 'name'],
  'warehouse_id' => ['inv_warehouses', 'name'],
  'created_by_user_id' => ['global_users', 'username'],
  // Añadir otros si existen en tu tabla inv_purchase_orders
];
$colLabels = []; foreach ($cols as $c) { $colLabels[$c['name']] = ucfirst(str_replace('_',' ',$c['name'])); }
$colLabels['supplier_id'] = 'Proveedor';
$colLabels['warehouse_id'] = 'Almacén';
$colLabels['created_by_user_id'] = 'Creado por';
$colLabels['status'] = 'Estado';
$colLabels['order_date'] = 'Fecha Pedido';
$colLabels['expected_date'] = 'Fecha Esperada';
$colLabels['notes'] = 'Notas';
$colLabels['created_at'] = 'Creado';
$colLabels['updated_at'] = 'Modificado';
// --- FIN MODIFICADO ---

/* ==== Acciones (POST/AJAX) ==== */
$msg = get_flash_msg(); $editRow = null; $editID = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf_or_redirect();
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  $vals = []; foreach (array_keys($colLabels) as $k) { if(isset($_POST[$k])) $vals[$k] = $_POST[$k] ?: null; }
  unset($vals['id'], $vals['created_at'], $vals['updated_at']); // Ignorar calculados/keys

  try {
    if ($action === 'save') {
      $sqlCols = []; $sqlParams = [];
      foreach ($vals as $k=>$v) { $sqlCols[] = "`$k`=?"; $sqlParams[] = $v; }
      if ($id > 0) { // Update
        $sqlParams[] = $id;
        $pdo->prepare("UPDATE `$table` SET " . implode(', ',$sqlCols) . " WHERE id=?")->execute($sqlParams);
        set_flash('success',"Pedido #{$id} actualizado.");
      } else { // Insert
        $sql = "INSERT INTO `$table` (" . implode(', ', array_map(fn($k)=>" `$k` ", array_keys($vals))) . ") VALUES (" . implode(',', array_fill(0,count($vals),'?')) . ")";
        $pdo->prepare($sql)->execute(array_values($vals));
        set_flash('success','Pedido creado.');
      }
      header('Location: '.basename($_SERVER['PHP_SELF'])); exit; // Redirigir siempre
    } elseif ($action === 'delete' && $id > 0) {
      // --- MODIFICADO: Eliminar líneas primero ---
      $pdo->prepare("DELETE FROM inv_purchase_order_lines WHERE order_id=?")->execute([$id]);
      // --- FIN MODIFICADO ---
      $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
      set_flash('info',"Pedido #{$id} eliminado.");
      header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
    }
  } catch (Throwable $e) { $msg = '<div class="alert alert-danger">Error: '.h($e->getMessage()).'</div>'; }
} elseif (isset($_GET['action']) && $_GET['action']==='suggest' && isset($_GET['field']) && isset($_GET['term'])) {
  // AJAX para selects relacionados
  header('Content-Type: application/json');
  $field = $_GET['field']; $term = trim($_GET['term']); $res = ['results'=>[]];
  if ($term !== '' && isset($related[$field])) {
    [$relTable, $relLabel] = $related[$field];
    // --- MODIFICADO: Usar tablas relacionadas correctas ---
    $sql = "SELECT id, `$relLabel` AS label FROM `$relTable` WHERE `$relLabel` LIKE ? ORDER BY `$relLabel` LIMIT 10";
    // --- FIN MODIFICADO ---
    $st = $pdo->prepare($sql); $st->execute(["%$term%"]);
    $res['results'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
  echo json_encode($res); exit;
} elseif ($editID > 0) {
  $editRow = $pdo->prepare("SELECT * FROM `$table` WHERE id=?"); $editRow->execute([$editID]); $editRow = $editRow->fetch();
  if (!$editRow) { set_flash('danger',"Pedido #{$editID} no encontrado."); header('Location: '.basename($_SERVER['PHP_SELF'])); exit; }
}

/* ==== Filtros y Paginación ==== */
$filters = []; $where = ['1=1']; $params = [];
foreach ($cols as $c) {
  $k = $c['name']; $v = trim($_GET[$k] ?? '');
  if ($v === '') continue;
  $filters[$k] = $v;
  if (isset($related[$k])) { $where[] = "t.`$k`=?"; $params[]=(int)$v; }
  elseif (is_numeric_type($c['type'])) { $where[] = "t.`$k`=?"; $params[]=(float)$v; }
  elseif (is_text_type($c['type'])) { $where[] = "t.`$k` LIKE ?"; $params[]="%$v%"; }
  else { $where[] = "t.`$k` = ?"; $params[]=$v; }
}
$whereStr = 'WHERE '.implode(' AND ',$where);
$count = $pdo->prepare("SELECT COUNT(*) FROM `$table` t $whereStr"); $count->execute($params);
$totalRows = (int)$count->fetchColumn(); $page=max(1,(int)($_GET['page']??1)); $perPage=20; $totalPages=ceil($totalRows/$perPage); $offset=($page-1)*$perPage;

/* ==== Listado ==== */
$select = "t.*";
foreach ($related as $fk => [$relTable, $relLabel]) { $alias = $fk.'_label'; $select .= ", (SELECT `$relLabel` FROM `$relTable` WHERE id=t.`$fk`) AS `$alias` "; }
$sql = "SELECT $select FROM `$table` t $whereStr ORDER BY t.id DESC LIMIT $perPage OFFSET $offset";
$rows = $pdo->prepare($sql); $rows->execute($params); $list = $rows->fetchAll();

/* ==== Vista ==== */
$pageTitle = 'Pedidos';
require __DIR__ . '/partials/header.php';
?>
<?= $msg ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4><i class="bi bi-clipboard-check me-2"></i> Pedidos de Compra</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rowModal"><i class="bi bi-plus-lg"></i> Nuevo Pedido</button>
</div>

<div class="card mb-3"><div class="card-body">
  <?= render_crud_filters($pdo, $cols, $filters, $related) ?>
</div></div>

<div class="table-responsive">
  <table class="table table-sm table-striped table-hover align-middle">
    <thead>
      <tr>
        <?php foreach($colLabels as $k=>$l): if($k=='notes') continue; // Ocultar notas largas ?>
          <th><?= h($l) ?></th>
        <?php endforeach; ?>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <?php foreach ($colLabels as $k=>$l): if($k=='notes') continue; ?>
          <td><?php
            $val = $r[$k] ?? null;
            if (isset($related[$k])) { echo h($r[$k.'_label'] ?? $val); }
            elseif ($k=='status') { echo '<span class="badge bg-secondary">'.h($val).'</span>'; } // Ejemplo badge
            elseif (is_numeric($val)) { echo number_format((float)$val, ($k=='total'?2:0), ',', '.'); } // Formato número
            else { echo h($val); }
          ?></td>
        <?php endforeach; ?>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-info" href="pedido_edit.php?id=<?= (int)$r['id'] ?>" title="Ver/Editar Líneas"><i class="bi bi-list-ul"></i></a>
          <a class="btn btn-sm btn-outline-primary" href="?<?= http_build_query(array_merge($_GET,['edit'=>(int)$r['id']])) ?>#rowModal" title="Editar Cabecera"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar Pedido #<?= (int)$r['id'] ?>? Se eliminarán sus líneas.');">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; if (!$list): ?>
      <tr><td colspan="<?= count($colLabels) ?>" class="text-center text-muted">Sin resultados</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if($totalPages>1): ?>
<nav><ul class="pagination pagination-sm">
  <?php for($i=1;$i<=$totalPages;$i++): ?>
  <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="rowModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editID ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= $editID ? 'Editar Pedido #'.$editID : 'Nuevo Pedido' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
        <?php foreach ($cols as $c): $k = $c['name']; if ($k==='id'||$k==='created_at'||$k==='updated_at') continue; ?>
          <div class="col-md-6 mb-2"><?= render_crud_form_field($pdo, $c, $editRow[$k] ?? null, $related, $colLabels[$k] ?? $k) ?></div>
        <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div></div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
<script>
  // Script para select2 AJAX y auto-abrir modal
  document.querySelectorAll('input[data-suggest-field]').forEach(inp=>{
    const field=inp.getAttribute('data-suggest-field'); if(!field) return; const hiddenId=document.getElementById(inp.id+'_id'); if(!hiddenId) return;
    const listBox=document.createElement('div'); listBox.className='list-group position-absolute w-100 start-0 top-100 z-3 shadow-sm d-none'; listBox.style.maxHeight='200px'; listBox.style.overflowY='auto'; inp.parentElement.style.position='relative'; inp.parentElement.appendChild(listBox);
    const debounce=(fn,ms=250)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)}};
    const render=(items)=>{ if(!Array.isArray(items?.results)) return;
      listBox.innerHTML=items.results.map(it=>`<button type=\"button\" class=\"list-group-item list-group-item-action\" data-id=\"${it.id}\">${(it.label??'').toString().replace(/</g,'&lt;')}</button>`).join('');
      listBox.classList.remove('d-none'); };
    const search=debounce(async()=>{
      const term=inp.value.trim();
      try{ const url=new URL(window.location.href); url.searchParams.set('action','suggest'); url.searchParams.set('field',field); url.searchParams.set('term',term);
        const r=await fetch(url.toString(),{credentials:'same-origin'}); const data=await r.json(); render(data);
      }catch(e){} },250);
    inp.addEventListener('input',()=>{ hiddenId.value=''; search(); }); inp.addEventListener('focus',search);
    document.addEventListener('click',ev=>{ if(!listBox.contains(ev.target)&&ev.target!==inp) listBox.classList.add('d-none'); });
    listBox.addEventListener('click',ev=>{ const btn=ev.target.closest('button.list-group-item'); if(!btn) return;
      hiddenId.value=btn.getAttribute('data-id')||''; inp.value=btn.textContent.trim(); listBox.classList.add('d-none'); });
  });
  const params=new URLSearchParams(window.location.search);
  if(params.has('add')||params.has('edit')){
    const open=()=>{ const el=document.getElementById('rowModal'); if(el && window.bootstrap && bootstrap.Modal){ bootstrap.Modal.getOrCreateInstance(el).show(); return true; } return false; };
    if(!open()) window.addEventListener('DOMContentLoaded', open);
  }
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>