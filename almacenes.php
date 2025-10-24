<?php
// almacenes.php — CRUD de almacenes (warehouses)
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
if (function_exists('check_csrf')) { check_csrf(); }

/* Helpers */
function db_name(PDO $pdo){ return $pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_col(PDO $pdo,$t,$c){ $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?"); $st->execute([db_name($pdo),$t,$c]); return (int)$st->fetchColumn()>0; }

/* CSRF & flash */
$csrfToken = function_exists('csrf_token') ? csrf_token() : null;
function csrf_ok(){ if(!function_exists('check_csrf')) return true; try{ check_csrf(); return true; }catch(Throwable $e){ return false; } }
$flash=null; function set_flash($t,$m){ global $flash; $flash=['type'=>$t,'msg'=>$m]; }

/* Columnas */
// --- MODIFICADO ---
$table = 'inv_warehouses';
$t_companies = 'inv_companies';
// --- FIN MODIFICADO ---
$hasCode   = has_col($pdo,$table,'code');
$hasAddr   = has_col($pdo,$table,'address');
$hasCity   = has_col($pdo,$table,'city');
$hasCountry= has_col($pdo,$table,'country');
$hasContact= has_col($pdo,$table,'contact_name');
$hasPhone  = has_col($pdo,$table,'phone');
$hasEmail  = has_col($pdo,$table,'email');
$hasActive = has_col($pdo,$table,'is_active');
$hasCompany= has_col($pdo,$table,'company_id');

/* Acciones */
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()){
  $action=$_POST['action'] ?? ''; $id=(int)($_POST['id'] ?? 0);
  $name=trim($_POST['name'] ?? '');
  
  if($action==='save' && $name!==''){
    $params=['name'=>$name]; $cols=['name=:name'];
    if($hasCode)   { $params['code']   = $_POST['code'] ?? null;         $cols[]='code=:code'; }
    if($hasAddr)   { $params['address']= $_POST['address'] ?? null;      $cols[]='address=:address'; }
    if($hasCity)   { $params['city']   = $_POST['city'] ?? null;         $cols[]='city=:city'; }
    if($hasCountry){ $params['country']= $_POST['country'] ?? null;      $cols[]='country=:country'; }
    if($hasContact){ $params['contact']= $_POST['contact_name'] ?? null; $cols[]='contact_name=:contact'; }
    if($hasPhone)  { $params['phone']  = $_POST['phone'] ?? null;        $cols[]='phone=:phone'; }
    if($hasEmail)  { $params['email']  = $_POST['email'] ?? null;        $cols[]='email=:email'; }
    if($hasActive) { $params['active'] = isset($_POST['is_active'])?1:0; $cols[]='is_active=:active'; }
    if($hasCompany){ $params['company']= $_POST['company_id'] ?: null;   $cols[]='company_id=:company'; }
    
    try {
      if($id>0){ // Update
        $params['id']=$id;
        // --- MODIFICADO ---
        $pdo->prepare("UPDATE inv_warehouses SET ".implode(', ',$cols)." WHERE id=:id")->execute($params);
        // --- FIN MODIFICADO ---
        set_flash('success','Almacén #'.$id.' actualizado');
      }else{ // Create
        // --- MODIFICADO ---
        $pdo->prepare("INSERT INTO inv_warehouses SET ".implode(', ',$cols))->execute($params);
        // --- FIN MODIFICADO ---
        set_flash('success','Almacén creado');
      }
    }catch(Throwable $e){ set_flash('danger','Error guardando: '.$e->getMessage()); }
    header('Location: almacenes.php'); exit;
  
  }elseif($action==='delete' && $id>0){
    try{
      // --- MODIFICADO ---
      $pdo->prepare("DELETE FROM inv_warehouses WHERE id=?")->execute([$id]);
      // --- FIN MODIFICADO ---
      set_flash('success','Almacén #'.$id.' eliminado');
    }catch(Throwable $e){ set_flash('danger','Error eliminando: '.$e->getMessage()); }
    header('Location: almacenes.php'); exit;
  }
}

/* Listas (selects) */
// --- MODIFICADO ---
$companies=$hasCompany ? $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll() : [];
// --- FIN MODIFICADO ---

/* Filtros y Paginación */
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 25;
$q = trim($_GET['q'] ?? '');
$f_company = $hasCompany ? (int)($_GET['company_id'] ?? 0) : 0;
$f_active = $hasActive ? (int)($_GET['is_active'] ?? -1) : -1;
$params=[]; $where=['1=1'];
if($q!==''){ $where[]='(w.name LIKE ? OR w.code LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; }
if($f_company>0){ $where[]='w.company_id=?'; $params[]=$f_company; }
if($f_active>=0) { $where[]='w.is_active=?'; $params[]=$f_active; }
$whereStr='WHERE '.implode(' AND ',$where);
$count=$pdo->prepare("SELECT COUNT(*) FROM $table w $whereStr"); $count->execute($params);
$totalRows=(int)$count->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$offset=($page-1)*$perPage;

/* Listado */
$cols = "w.*";
if($hasCompany) $cols .= ", c.name AS company_name";
// --- MODIFICADO ---
$sql = "SELECT $cols FROM inv_warehouses w ";
if($hasCompany) $sql .= " LEFT JOIN inv_companies c ON c.id=w.company_id ";
// --- FIN MODIFICADO ---
$sql .= "$whereStr ORDER BY w.id DESC LIMIT $perPage OFFSET $offset";
$rows=$pdo->prepare($sql); $rows->execute($params); $list=$rows->fetchAll();

/* Fila para editar (si ?edit=ID) */
$editRow=null; $editID=(int)($_GET['edit'] ?? 0);
if($editID>0){ 
    // --- MODIFICADO ---
    $st=$pdo->prepare("SELECT * FROM inv_warehouses WHERE id=?"); 
    // --- FIN MODIFICADO ---
    $st->execute([$editID]); $editRow=$st->fetch(); 
}

$pageTitle = 'Almacenes';
require __DIR__ . '/partials/header.php';
?>
<?php if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div><?php endif; ?>

<?php if($editRow): ?>
<div class="card mb-3" id="editForm"><div class="card-body">
  <h5 class="card-title">Editando Almacén #<?= (int)$editRow['id'] ?></h5>
  <?php include __DIR__ . '/almacenes_form.php'; ?>
</div></div>
<?php else: ?>
<p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rowModal"><i class="bi bi-plus-lg"></i> Nuevo Almacén</button></p>
<div class="modal fade" id="rowModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Nuevo Almacén</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <?php include __DIR__ . '/almacenes_form.php'; ?>
    </div>
  </div></div>
</div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body">
  <form method="get" class="row gx-2 gy-2 align-items-center">
    <div class="col-md-3"><input name="q" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($q) ?>"></div>
    <?php if($hasCompany): ?>
    <div class="col-md-3"><select name="company_id" class="form-select">
      <option value="">-- Empresa --</option>
      <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$f_company?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
    </select></div>
    <?php endif; ?>
    <?php if($hasActive): ?>
    <div class="col-md-2"><select name="is_active" class="form-select">
      <option value="-1">-- Estado --</option>
      <option value="1" <?= $f_active===1?'selected':'' ?>>Activo</option>
      <option value="0" <?= $f_active===0?'selected':'' ?>>Inactivo</option>
    </select></div>
    <?php endif; ?>
    <div class="col-md-2"><button class="btn btn-primary">Filtrar</button></div>
  </form>
</div></div>

<div class="table-responsive">
  <table class="table table-sm table-striped table-hover align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <?php if($hasCode): ?><th>Código</th><?php endif; ?>
        <th>Nombre</th>
        <?php if($hasCompany): ?><th>Empresa</th><?php endif; ?>
        <?php if($hasAddr): ?><th>Dirección</th><?php endif; ?>
        <?php if($hasCity): ?><th>Ciudad</th><?php endif; ?>
        <?php if($hasCountry): ?><th>País</th><?php endif; ?>
        <?php if($hasContact): ?><th>Contacto</th><?php endif; ?>
        <?php if($hasPhone): ?><th>Teléfono</th><?php endif; ?>
        <?php if($hasEmail): ?><th>Email</th><?php endif; ?>
        <?php if($hasActive): ?><th>Activo</th><?php endif; ?>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if($list): foreach($list as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <?php if($hasCode): ?><td><?= htmlspecialchars($r['code'] ?? '') ?></td><?php endif; ?>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <?php if($hasCompany): ?><td><?= htmlspecialchars($r['company_name'] ?? '-') ?></td><?php endif; ?>
        <?php if($hasAddr): ?><td><?= htmlspecialchars($r['address'] ?? '') ?></td><?php endif; ?>
        <?php if($hasCity): ?><td><?= htmlspecialchars($r['city'] ?? '') ?></td><?php endif; ?>
        <?php if($hasCountry): ?><td><?= htmlspecialchars($r['country'] ?? '') ?></td><?php endif; ?>
        <?php if($hasContact): ?><td><?= htmlspecialchars($r['contact_name'] ?? '') ?></td><?php endif; ?>
        <?php if($hasPhone): ?><td><?= htmlspecialchars($r['phone'] ?? '') ?></td><?php endif; ?>
        <?php if($hasEmail): ?><td><?= htmlspecialchars($r['email'] ?? '') ?></td><?php endif; ?>
        <?php if($hasActive): ?><td><span class="badge bg-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Sí':'No' ?></span></td><?php endif; ?>
        
<td class="text-end text-nowrap action-gap">
  <a class="btn btn-sm btn-outline-primary btn-icon"
     href="almacenes.php?<?= http_build_query(array_merge($_GET,['edit'=>(int)$r['id']])) ?>"
     data-bs-toggle="tooltip" data-bs-placement="top" title="Editar almacén" aria-label="Editar">
    <i class="bi bi-pencil"></i><span class="visually-hidden">Editar</span>
  </a>

  <form method="post" class="d-inline"
        onsubmit="return confirm('¿Eliminar almacén #<?= (int)$r['id'] ?>?');">
    <?php if($csrfToken): ?><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><?php endif; ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <button class="btn btn-sm btn-outline-danger btn-icon"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar almacén" aria-label="Eliminar">
      <i class="bi bi-trash"></i><span class="visually-hidden">Eliminar</span>
    </button>
  </form>
</td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="12" class="text-center text-muted">Sin resultados</td></tr>
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

<?php
// Auto-abrir modal si ?edit=ID y no se encontró
if($editID>0 && !$editRow) set_flash('danger','Almacén #'.$editID.' no encontrado.');
require __DIR__ . '/partials/footer.php';
?>