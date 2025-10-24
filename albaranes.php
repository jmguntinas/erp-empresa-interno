<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/lib/table_detector.php';
require_once __DIR__ . '/partials/bootstrap_tables.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ==== Helpers (seguros ante redeclaración) ==== */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('current_db')) {
  function current_db(PDO $pdo): string { try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); } catch(Throwable $e){ return ''; } }
}
if (!function_exists('is_numeric_type')) {
  function is_numeric_type(string $t): bool {
    static $nums = ['int','integer','bigint','smallint','mediumint','tinyint','decimal','numeric','float','double','real'];
    return in_array($t, $nums, true);
  }
}
if (!function_exists('is_text_type')) {
  function is_text_type(string $t): bool {
    static $txt = ['varchar','char','text','mediumtext','longtext'];
    return in_array($t, $txt, true);
  }
}
if (!function_exists('is_long_text')) { function is_long_text(string $t): bool { return in_array($t, ['text','mediumtext','longtext'], true); } }
if (!function_exists('pretty_label')) {
  function pretty_label(string $col): string {
    $col = str_replace('_',' ', $col); $col = preg_replace('/\s+/', ' ', $col);
    return mb_convert_case($col, MB_CASE_TITLE, "UTF-8");
  }
}
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo, string $table): bool {
    try { $db = current_db($pdo);
      $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
      $st->execute([$db, $table]); return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
  }
}
if (!function_exists('get_table_columns')) {
  function get_table_columns(PDO $pdo, string $table): array {
    $db = current_db($pdo);
    $st = $pdo->prepare("
      SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, IS_NULLABLE, COLUMN_DEFAULT,
             CHARACTER_MAXIMUM_LENGTH, ORDINAL_POSITION
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
      ORDER BY ORDINAL_POSITION
    "); $st->execute([$db, $table]);
    $cols = [];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $name = (string)$r['COLUMN_NAME'];
      $cols[$name] = [
        'type'   => strtolower((string)$r['DATA_TYPE']),
        'key'    => (string)$r['COLUMN_KEY'],
        'null'   => ((string)$r['IS_NULLABLE'] === 'YES'),
        'def'    => $r['COLUMN_DEFAULT'],
        'maxlen' => $r['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int)$r['CHARACTER_MAXIMUM_LENGTH'] : null,
        'pos'    => (int)$r['ORDINAL_POSITION'],
      ];
    } return $cols;
  }
}
if (!function_exists('get_primary_key')) {
  function get_primary_key(array $colsMeta): string {
    foreach ($colsMeta as $c=>$m) if (($m['key'] ?? '') === 'PRI') return $c;
    return isset($colsMeta['id']) ? 'id' : array_key_first($colsMeta);
  }
}
if (!function_exists('get_foreign_keys')) {
  function get_foreign_keys(PDO $pdo, string $table): array {
    $db = current_db($pdo);
    $st = $pdo->prepare("
      SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
      WHERE k.TABLE_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL
    "); $st->execute([$db, $table]);
    $map = [];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $map[(string)$r['COLUMN_NAME']] = [
        'ref_table' => (string)$r['REFERENCED_TABLE_NAME'],
        'ref_col'   => (string)$r['REFERENCED_COLUMN_NAME'],
      ];
    } return $map;
  }
}
if (!function_exists('fetch_fk_options')) {
  function fetch_fk_options(PDO $pdo, string $table, string $idCol='id', ?string $labelCol=null, int $limit=1000): array {
    $labelCol = $labelCol ?: 'id';
    try {
      $cols = get_table_columns($pdo, $table);
      foreach (['nombre','name','descripcion','codigo','referencia'] as $cand) if (isset($cols[$cand])) { $labelCol = $cand; break; }
    } catch (Throwable $e) {}
    $sql = "SELECT `$idCol` AS id, `$labelCol` AS label FROM `$table` ORDER BY `$labelCol` ASC LIMIT $limit";
    try { $st = $pdo->query($sql); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { return []; }
  }
}
if (!function_exists('ensure_csrf_cookie')) {
  function ensure_csrf_cookie(): string {
    $token = $_COOKIE['XSRF-TOKEN'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
      $token = bin2hex(random_bytes(32));
      setcookie('XSRF-TOKEN', $token, [
        'expires'  => time()+60*60*24*7, 'path'=>'/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly' => false, 'samesite' => 'Lax',
      ]); $_COOKIE['XSRF-TOKEN'] = $token;
    }
    $_SESSION['csrf_token'] = $token; return $token;
  }
}
if (!function_exists('check_csrf_or_redirect')) {
  function check_csrf_or_redirect(string $back=''): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $cookie = trim((string)($_COOKIE['XSRF-TOKEN'] ?? '')); $post = trim((string)($_POST['csrf_token'] ?? ''));
    if (!($cookie && $post && hash_equals($cookie, $post))) {
      $_SESSION['flash']=['type'=>'danger','msg'=>'CSRF inválido']; header('Location: '.($back?:basename($_SERVER['PHP_SELF']))); exit;
    }
  }
}
ensure_csrf_cookie();
$APP_LANG = i18n_get_lang();

/* ====== Tabla objetivo (deliveries) con mapeo preestablecido ====== */
$TABLE = td_get_table($pdo, 'deliveries');
if (!$TABLE || !table_exists($pdo, $TABLE)) {
  include __DIR__ . '/partials/header.php'; ?>
  <h4 class="mb-3"><i class="bi bi-exclamation-triangle"></i> <?= ti($pdo,'ui.tabla_no_disponible','Tabla no disponible') ?></h4>
  <div class="alert alert-warning">
    <?= ti($pdo,'ui.seleccion_tabla_msg','No se ha podido determinar la tabla. Pásala por URL con ?table=nombre_tabla; quedará guardada.') ?>
  </div>
  <?php include __DIR__ . '/partials/footer.php'; exit;
}

/* ====== Metadatos ====== */
$colsMeta = get_table_columns($pdo, $TABLE); 
$pkCol    = get_primary_key($colsMeta); 
$fkMap    = get_foreign_keys($pdo, $TABLE);
$allCols  = array_keys($colsMeta);

// Semilla i18n
$seed=[]; foreach($colsMeta as $c=>$m){ $seed[$c]=pretty_label($c);} 
if(function_exists('i18n_seed_if_missing')){ i18n_seed_if_missing($pdo, $TABLE, $seed); }

/* ====== Config (columnas visibles, buscables, filas/pág) ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action']??'')==='save_columns')) {
  check_csrf_or_redirect();
  $vis=[]; foreach($allCols as $c) $vis[$c] = isset($_POST['col__'.$c])?1:0;
  if(!array_reduce($vis, fn($a,$b)=>$a||$b, false)) foreach(['id',$pkCol] as $d) if(isset($vis[$d])) $vis[$d]=1;
  $_SESSION['deliv_visible'][$TABLE]=$vis;

  $sea=[]; foreach($allCols as $c) $sea[$c] = isset($_POST['search__'.$c])?1:0;
  $_SESSION['deliv_searchable'][$TABLE]=$sea;

  $_SESSION['deliv_per'][$TABLE] = max(10, min(200, (int)($_POST['cfg_per'] ?? 25)));
  header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}
$visible = $_SESSION['deliv_visible'][$TABLE] ?? null;
$searchables = $_SESSION['deliv_searchable'][$TABLE] ?? null;
$cfgPerPage = (int)($_SESSION['deliv_per'][$TABLE] ?? 25);
if(!$visible){ $d=array_fill_keys($allCols,0); foreach(['id',$pkCol] as $df) if(isset($d[$df])) $d[$df]=1; $visible=$d; }
if(!$searchables){ $d=array_fill_keys($allCols,0); foreach(['id'] as $df) if(isset($d[$df])) $d[$df]=1; $searchables=$d; }

/* ====== Autocompletar ====== */
if (($_GET['action'] ?? '') === 'suggest') {
  header('Content-Type: application/json; charset=utf-8');
  $field = $_GET['field'] ?? ''; $term = trim((string)($_GET['term'] ?? '')); $out=[];
  if ($field && isset($colsMeta[$field]) && !empty($searchables[$field])) {
    if (isset($fkMap[$field])) {
      $refTable = $fkMap[$field]['ref_table']; $refColId=$fkMap[$field]['ref_col'] ?: 'id';
      $labelCol='id'; try{$c2=get_table_columns($pdo,$refTable); foreach(['nombre','name','descripcion','codigo','referencia'] as $cand) if(isset($c2[$cand])){$labelCol=$cand;break;}}catch(Throwable $e){}
      $st=$pdo->prepare("SELECT `$refColId` AS id, `$labelCol` AS label FROM `$refTable` WHERE `$labelCol` LIKE ? ORDER BY `$labelCol` ASC LIMIT 10");
      $st->execute([$term===''?'%':($term.'%')]); $out=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $colType=$colsMeta[$field]['type'];
      $sql=is_text_type($colType)?"SELECT DISTINCT `$field` AS v FROM `$TABLE` WHERE `$field` LIKE ? ORDER BY `$field` ASC LIMIT 10":"SELECT DISTINCT `$field` AS v FROM `$TABLE` WHERE CAST(`$field` AS CHAR) LIKE ? ORDER BY `$field` ASC LIMIT 10";
      $st=$pdo->prepare($sql); $st->execute([$term===''?'%':($term.'%')]); $out=array_map(fn($r)=>['value'=>$r['v']], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
  } echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

/* ====== Filtros & datos ====== */
$q=trim($_GET['q'] ?? ''); $page=max(1,(int)($_GET['page'] ?? 1)); $per=max(10,min(200,$cfgPerPage));
$where=[]; $params=[];
if($q!==''){ $textCols=[]; foreach($colsMeta as $c=>$m) if(is_text_type($m['type'])) $textCols[]="t.`$c` LIKE ?"; foreach($textCols as $_) $params[]="%$q%"; if($textCols) $where[]='('.implode(' OR ',$textCols).')'; }
$activeFieldFilters=[];
foreach($searchables as $col=>$enabled){ if(!$enabled) continue; $key='s_'.$col; if(!isset($_GET[$key])) continue; $val=trim((string)$_GET[$key]); if($val==='') continue; $activeFieldFilters[$col]=$val;
  $t=$colsMeta[$col]['type'] ?? '';
  if(isset($fkMap[$col])){ $where[]="t.`$col`=?"; $params[]=(int)$val; }
  elseif(is_text_type($t)){ $where[]="t.`$col` LIKE ?"; $params[]="%$val%"; }
  elseif(in_array($t,['date','datetime','timestamp','time'])){ if(preg_match('/^\d{4}-\d{2}-\d{2}/',$val)){ $where[]="DATE(t.`$col`)=?"; $params[]=substr($val,0,10);} else { $where[]="t.`$col` LIKE ?"; $params[]="$val%"; } }
  elseif(is_numeric_type($t)){ if(is_numeric($val)){ $where[]="t.`$col`=?"; $params[]=$val+0; } }
  else { $where[]="t.`$col` LIKE ?"; $params[]="%$val%"; }
}
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';
$stCnt=$pdo->prepare("SELECT COUNT(*) FROM `$TABLE` t $wsql"); $stCnt->execute($params); $total=(int)$stCnt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$per)); $page=min($page,$totalPages); $offset=($page-1)*$per;
$st=$pdo->prepare("SELECT * FROM `$TABLE` t $wsql ORDER BY t.`$pkCol` DESC LIMIT $per OFFSET $offset"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* ====== Guardar (INSERT/UPDATE) ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action']??'')==='save_row')) {
  check_csrf_or_redirect();
  $id = $_POST[$pkCol] ?? ''; $isUpdate = ($id!=='' && $id!==null);
  $fields=[];
  foreach ($colsMeta as $col=>$meta) {
    if ($col===$pkCol) continue;
    if (!array_key_exists($col, $_POST)) continue;
    $val = $_POST[$col]; $t = $meta['type'];
    if ($val==='') $val = null;
    if (is_numeric_type($t) && $val!==null) {
      $val = str_replace([' ','.',','], ['','', '.'], $val);
      $val = (strpos((string)$val,'.')!==false) ? (float)$val : (int)$val;
    } elseif (in_array($t,['date','datetime','timestamp','time']) && $val!==null) {
      $val = str_replace('T',' ', (string)$val);
    } else { if ($val!==null) $val = trim((string)$val); }
    $fields[$col] = $val;
  }
  try {
    if ($isUpdate) {
      $sets=[]; $vals=[]; foreach($fields as $k=>$v){ $sets[]="`$k`=?"; $vals[]=$v; }
      if ($sets) { $vals[]=$id; $pdo->prepare("UPDATE `$TABLE` SET ".implode(',',$sets)." WHERE `$pkCol`=?")->execute($vals); }
      $_SESSION['flash']=['type'=>'success','msg'=>ti($pdo,'msg.actualizado','Actualizado')];
      header('Location: '.basename($_SERVER['PHP_SELF']).'?edit='.urlencode((string)$id)); exit;
    } else {
      if (!$fields) { $_SESSION['flash']=['type'=>'danger','msg'=>ti($pdo,'msg.sin_campos','No hay campos válidos')]; header('Location: '.basename($_SERVER['PHP_SELF'])); exit; }
      $cols=array_keys($fields); $place=implode(',', array_fill(0,count($cols), '?'));
      $pdo->prepare("INSERT INTO `$TABLE` (".implode(',',$cols).") VALUES ($place)")->execute(array_values($fields));
      $newId = (string)$pdo->lastInsertId();
      $_SESSION['flash']=['type'=>'success','msg'=>ti($pdo,'msg.creado','Creado')];
      header('Location: '.basename($_SERVER['PHP_SELF']).'?edit='.urlencode($newId)); exit;
    }
  } catch (Throwable $e) {
    $_SESSION['flash']=['type'=>'danger','msg'=>ti($pdo,'msg.error_guardar','Error al guardar')];
    header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
  }
}

/* ====== Eliminar ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action']??'')==='delete_row')) {
  check_csrf_or_redirect();
  $id = $_POST[$pkCol] ?? null;
  if ($id!==null && $id!=='') {
    try {
      $stDel = $pdo->prepare("DELETE FROM `$TABLE` WHERE `$pkCol`=?");
      $stDel->execute([$id]);
      $_SESSION['flash']=['type'=>'success','msg'=>ti($pdo,'msg.eliminado','Eliminado')];
    } catch (Throwable $e) {
      $_SESSION['flash']=['type'=>'danger','msg'=>ti($pdo,'msg.error_eliminar','No se pudo eliminar')];
    }
  }
  header('Location: '.basename($_SERVER['PHP_SELF'])); exit;
}

/* ====== Vista ====== */
include __DIR__ . '/partials/header.php'; ?>
<h4 class="mb-3 d-flex align-items-center gap-2">
  <i class="bi bi-truck"></i> <?= ti($pdo, 'menu.albaranes', 'Albaranes') ?>
  <span class="ms-auto d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#configModal"
            data-bs-toggle2="tooltip" title="<?= h(ti($pdo,'btn.configuracion','Configuración')) ?>">
      <i class="bi bi-gear"></i>
    </button>
    <a class="btn btn-sm btn-success" href="<?= basename($_SERVER['PHP_SELF']) ?>?add=1"
       data-bs-toggle="tooltip" title="<?= h(ti($pdo,'btn.nuevo','Nuevo')) ?>">
      <i class="bi bi-plus-lg"></i>
    </a>
  </span>
</h4>
<?php if($flash): ?><div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
<div class="alert alert-info py-2">Tabla: <code><?= h($TABLE) ?></code></div>

<div class="card mb-3"><div class="card-body">
<form class="row g-2">
  <div class="col-12">
    <div class="row g-2">
      <div class="col-md-6">
        <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="<?= h(ti($pdo,'buscar.libre','Buscar libre')) ?>">
      </div>
      <div class="col-md-6 text-end small text-muted align-self-center">
        <?= number_format($total,0,',','.') ?> · <?= (int)$per ?> / pág
      </div>
    </div>
  </div>
  <?php foreach($searchables as $col=>$enabled):
    if(!$enabled) continue; $label = tl($pdo,$TABLE,$col,pretty_label($col)); $t=$colsMeta[$col]['type'] ?? ''; $name='s_'.$col; $val=$activeFieldFilters[$col] ?? ''; $isFk=isset($fkMap[$col]); $sId='sf_'.$col; ?>
    <div class="col-md-3 position-relative">
      <label class="form-label form-label-sm"><?= h($label) ?></label>
      <?php if ($isFk): ?>
        <input type="text" class="form-control form-control-sm smart-fk" data-field="<?= h($col) ?>" id="<?= h($sId) ?>_text" placeholder="..." autocomplete="off" value="">
        <input type="hidden" name="<?= h($name) ?>" id="<?= h($sId) ?>" value="<?= h($val) ?>">
        <div class="list-group position-absolute w-100 shadow d-none smart-suggest" data-target="<?= h($sId) ?>" style="z-index:1050; max-height:220px; overflow:auto;"></div>
      <?php elseif (in_array($t,['date','datetime','timestamp'])): ?>
        <input type="<?= $t==='date'?'date':'datetime-local' ?>" class="form-control form-control-sm smart-text" data-field="<?= h($col) ?>" name="<?= h($name) ?>" value="<?= h($val ? str_replace(' ', 'T', substr((string)$val,0,16)) : '') ?>">
      <?php else: ?>
        <input type="text" class="form-control form-control-sm smart-text" data-field="<?= h($col) ?>" name="<?= h($name) ?>" value="<?= h($val) ?>" placeholder="...">
        <div class="list-group position-absolute w-100 shadow d-none smart-suggest" data-target="<?= h($name) ?>" style="z-index:1050; max-height:220px; overflow:auto;"></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="col-12">
    <div class="d-flex justify-content-end gap-2 mt-2">
      <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> <?= ti($pdo, 'buscar.buscar', 'Buscar') ?></button>
      <a class="btn btn-sm btn-outline-secondary" href="<?= basename($_SERVER['PHP_SELF']) ?>"><i class="bi bi-eraser"></i> <?= ti($pdo, 'buscar.limpiar', 'Limpiar') ?></a>
    </div>
  </div>
</form>
</div></div>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr>
    <?php foreach ($colsMeta as $col=>$meta): if (!empty($visible[$col])): ?>
      <th class="text-center"><?= h(tl($pdo,$TABLE,$col,pretty_label($col))) ?></th>
    <?php endif; endforeach; ?>
    <th class="text-center" style="width:120px"><?= ti($pdo, 'ui.acciones', 'Acciones') ?></th>
  </tr></thead>
  <tbody>
  <?php if($rows): foreach($rows as $r): ?>
    <tr>
      <?php foreach ($colsMeta as $col=>$meta): if (!empty($visible[$col])):
        $val = $r[$col] ?? null; $t = $meta['type'];
        if ($val === null) { $out = ''; }
        elseif (is_numeric_type($t)) { $out = (stripos($t,'dec')!==false||stripos($t,'float')!==false||stripos($t,'double')!==false)?number_format((float)$val,2,',','.') : number_format((int)$val,0,',','.'); }
        else { $out = (string)$val; } ?>
        <td class="text-center"><?= h($out) ?></td>
      <?php endif; endforeach; ?>
      <td class="text-center">
        <a class="btn btn-sm btn-outline-primary me-1" href="<?= basename($_SERVER['PHP_SELF']) ?>?edit=<?= urlencode((string)$r[$pkCol]) ?>"
           data-bs-toggle="tooltip" title="<?= h(ti($pdo,'btn.editar','Editar')) ?>" aria-label="<?= h(ti($pdo,'btn.editar','Editar')) ?>">
          <i class="bi bi-pencil"></i>
        </a>
        <form method="post" class="d-inline" onsubmit="return confirm('<?= ti($pdo, 'ui.confirmar_eliminar', '¿Seguro que deseas eliminar este registro?') ?>');">
          <input type="hidden" name="csrf_token" value="<?= h($_COOKIE['XSRF-TOKEN'] ?? '') ?>">
          <input type="hidden" name="action" value="delete_row">
          <input type="hidden" name="<?= h($pkCol) ?>" value="<?= h((string)$r[$pkCol]) ?>">
          <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?= h(ti($pdo,'btn.eliminar','Eliminar')) ?>" aria-label="<?= h(ti($pdo,'btn.eliminar','Eliminar')) ?>">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </td>
    </tr>
  <?php endforeach; else: ?>
    <tr><td colspan="<?= (int)array_sum($visible) + 1 ?>" class="text-center text-muted">—</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php if($total>0):
  $qsBase = $_GET; unset($qsBase['page']); $totalPages = max(1,(int)ceil($total/$per));
  $isFirst = $page<=1; $isLast = $page>=$totalPages; $prev = max(1,$page-1); $next = min($totalPages,$page+1); ?>
<nav class="mt-3 d-flex justify-content-end">
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $isFirst?'disabled':'' ?>"><a class="page-link" href="<?= basename($_SERVER['PHP_SELF']) ?>?<?= http_build_query($qsBase + ['page'=>1]) ?>">«</a></li>
    <li class="page-item <?= $isFirst?'disabled':'' ?>"><a class="page-link" href="<?= basename($_SERVER['PHP_SELF']) ?>?<?= http_build_query($qsBase + ['page'=>$prev]) ?>">‹</a></li>
    <li class="page-item disabled"><span class="page-link"><?= $page ?>/<?= $totalPages ?></span></li>
    <li class="page-item <?= $isLast?'disabled':'' ?>"><a class="page-link" href="<?= basename($_SERVER['PHP_SELF']) ?>?<?= http_build_query($qsBase + ['page'=>$next]) ?>">›</a></li>
    <li class="page-item <?= $isLast?'disabled':'' ?>"><a class="page-link" href="<?= basename($_SERVER['PHP_SELF']) ?>?<?= http_build_query($qsBase + ['page'=>$totalPages]) ?>">»</a></li>
  </ul>
</nav>
<?php endif; ?>

<!-- MODAL Configuración -->
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-gear"></i> <?= ti($pdo, 'btn.configuracion', 'Configuración') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_COOKIE['XSRF-TOKEN'] ?? '') ?>">
        <input type="hidden" name="action" value="save_columns">
        <div class="row">
          <div class="col-md-5">
            <h6><?= ti($pdo,'ui.columnas_visibles','Columnas visibles') ?></h6>
            <?php foreach ($colsMeta as $col=>$meta): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cfg_vis_<?= h($col) ?>" name="col__<?= h($col) ?>" <?= !empty($visible[$col])?'checked':'' ?>>
                <label class="form-check-label" for="cfg_vis_<?= h($col) ?>"><?= h(tl($pdo, $TABLE, $col, pretty_label($col))) ?> <span class="text-muted small">(<?= h($meta['type']) ?>)</span></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="col-md-5">
            <h6><?= ti($pdo,'ui.campos_buscables','Campos buscables') ?></h6>
            <?php foreach ($colsMeta as $col=>$meta): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cfg_search_<?= h($col) ?>" name="search__<?= h($col) ?>" <?= !empty($searchables[$col])?'checked':'' ?>>
                <label class="form-check-label" for="cfg_search_<?= h($col) ?>"><?= h(tl($pdo, $TABLE, $col, pretty_label($col))) ?> <span class="text-muted small">(<?= h($meta['type']) ?>)</span></label>
              </div>
            <?php endforeach; ?>
            <p class="text-muted small mt-2"><?= ti($pdo, 'ui.filters_and', 'Los filtros se aplican de forma acumulativa (AND).') ?></p>
          </div>
          <div class="col-md-2">
            <h6><?= ti($pdo,'ui.filas_por_pagina','Filas / pág.') ?></h6>
            <input type="number" min="10" max="200" class="form-control form-control-sm" name="cfg_per" value="<?= (int)$cfgPerPage ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= ti($pdo, 'btn.cancelar', 'Cancelar') ?></button>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> <?= ti($pdo, 'btn.guardar', 'Guardar') ?></button>
      </div>
    </form>
  </div></div>
</div>

<?php
$editId  = $_GET['edit'] ?? null; $addMode = isset($_GET['add']);
$editRow = null; if ($editId!==null && $editId!=='') { $st2=$pdo->prepare("SELECT * FROM `$TABLE` WHERE `$pkCol`=?"); $st2->execute([$editId]); $editRow=$st2->fetch(PDO::FETCH_ASSOC) ?: null; }
?>
<!-- MODAL: Nuevo / Editar -->
<div class="modal fade" id="rowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title">
          <?php if($editRow): ?><?= ti($pdo,'ui.editar_registro','Editar registro') ?> #<?= h($editRow[$pkCol]) ?>
          <?php else: ?><?= ti($pdo,'ui.nuevo_registro','Nuevo registro') ?><?php endif; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_COOKIE['XSRF-TOKEN'] ?? '') ?>">
        <input type="hidden" name="action" value="save_row">
        <input type="hidden" name="<?= h($pkCol) ?>" value="<?= $editRow ? h($editRow[$pkCol]) : '' ?>">
        <div class="row g-2">
          <?php foreach ($colsMeta as $col=>$meta):
            if ($col===$pkCol) continue;
            $type=$meta['type']; $label=tl($pdo,$TABLE,$col,pretty_label($col)); $val=$editRow[$col] ?? ''; $hasFk=isset($fkMap[$col]); $inputName=h($col);
          ?>
            <?php if ($hasFk):
              $fk=$fkMap[$col]; $options=fetch_fk_options($pdo,$fk['ref_table'],$fk['ref_col'] ?: 'id'); ?>
              <div class="col-md-4">
                <label class="form-label form-label-sm"><?= h($label) ?></label>
                <select class="form-select form-select-sm" name="<?= $inputName ?>">
                  <option value="">—</option>
                  <?php foreach($options as $opt): ?>
                    <option value="<?= (int)$opt['id'] ?>" <?= ((string)($val ?? '')===(string)$opt['id'])?'selected':'' ?>><?= h((string)$opt['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php elseif (in_array($type,['date','datetime','timestamp','time'])): ?>
              <div class="col-md-4">
                <label class="form-label form-label-sm"><?= h($label) ?></label>
                <input type="<?= $type==='datetime' || $type==='timestamp' ? 'datetime-local' : $type ?>" class="form-control form-control-sm" name="<?= $inputName ?>" value="<?= h($val ? (str_replace(' ', 'T', substr((string)$val,0,19))) : '') ?>">
              </div>
            <?php elseif (is_numeric_type($type)): ?>
              <div class="col-md-3">
                <label class="form-label form-label-sm"><?= h($label) ?></label>
                <input type="number" step="<?= (stripos($type,'dec')!==false||stripos($type,'float')!==false||stripos($type,'double')!==false)?'0.01':'1' ?>" class="form-control form-control-sm" name="<?= $inputName ?>" value="<?= h($val) ?>">
              </div>
            <?php elseif (is_long_text($type)): ?>
              <div class="col-12">
                <label class="form-label form-label-sm"><?= h($label) ?></label>
                <textarea class="form-control form-control-sm" name="<?= $inputName ?>" rows="3"><?= h($val) ?></textarea>
              </div>
            <?php else: ?>
              <div class="col-md-6">
                <label class="form-label form-label-sm"><?= h($label) ?></label>
                <input class="form-control form-control-sm" name="<?= $inputName ?>" value="<?= h($val) ?>" maxlength="<?= (int)($meta['maxlen'] ?? 255) ?>">
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= ti($pdo, 'btn.cancelar', 'Cancelar') ?></button>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> <?= ti($pdo, 'btn.guardar', 'Guardar') ?></button>
      </div>
    </form>
  </div></div>
</div>

<script>
(function(){
  // Tooltips
  const initTips=()=>{ const t=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); t.forEach(el=>{ try{ new bootstrap.Tooltip(el); }catch(e){} }); };
  if (window.bootstrap) initTips(); else document.addEventListener('DOMContentLoaded', initTips);

  const debounce=(fn,ms=250)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms);};};
  document.querySelectorAll('.smart-text').forEach(inp=>{
    const field=inp.getAttribute('data-field');
    const listBox=inp.parentElement.querySelector('.smart-suggest[data-target="'+inp.name+'"]');
    if(!listBox) return;
    const render=items=>{ if(!items||!items.length){listBox.classList.add('d-none');listBox.innerHTML='';return;}
      listBox.innerHTML = items.map(it=>`<button type="button" class="list-group-item list-group-item-action">${(it.value??'').toString().replace(/</g,'&lt;')}</button>`).join('');
      listBox.classList.remove('d-none'); };
    const search=debounce(async()=>{
      const term=inp.value.trim();
      try{ const url=new URL(window.location.href); url.searchParams.set('action','suggest'); url.searchParams.set('field',field); url.searchParams.set('term',term);
        const r=await fetch(url.toString(),{credentials:'same-origin'}); const data=await r.json(); render(data);
      }catch(e){} },250);
    inp.addEventListener('input',search); inp.addEventListener('focus',search);
    document.addEventListener('click',ev=>{ if(!listBox.contains(ev.target)&&ev.target!==inp) listBox.classList.add('d-none'); });
    listBox.addEventListener('click',ev=>{ const btn=ev.target.closest('button.list-group-item'); if(!btn) return; inp.value=btn.textContent.trim(); listBox.classList.add('d-none'); });
  });
  document.querySelectorAll('.smart-fk').forEach(inp=>{
    const field=inp.getAttribute('data-field'); const hiddenId=document.getElementById(inp.id.replace('_text',''));
    const listBox=inp.parentElement.querySelector('.smart-suggest[data-target="'+hiddenId.id+'"]'); if(!listBox) return;
    const render=items=>{ if(!items||!items.length){listBox.classList.add('d-none');listBox.innerHTML='';return;}
      listBox.innerHTML = items.map(it=>`<button type="button" class="list-group-item list-group-item-action" data-id="${it.id}">${(it.label??'').toString().replace(/</g,'&lt;')}</button>`).join('');
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

  // Auto-abrir modal si ?add=1 o ?edit=ID
  const params=new URLSearchParams(window.location.search);
  if(params.has('add')||params.has('edit')){
    const open=()=>{ const el=document.getElementById('rowModal'); if(el && window.bootstrap && bootstrap.Modal){ bootstrap.Modal.getOrCreateInstance(el).show(); return true; } return false; };
    if(!open()){ let tries=20; const iv=setInterval(()=>{ if(open()||(--tries<=0)) clearInterval(iv); },50); }
  }
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
