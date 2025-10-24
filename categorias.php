<?php
// categorias.php — CRUD de categorías (con subcategoría y empresa si existen columnas)
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
if (function_exists('check_csrf')) { check_csrf(); }

/* Helpers esquema */
function db_name(PDO $pdo){ return $pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_col(PDO $pdo,$t,$c){ $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?"); $st->execute([db_name($pdo),$t,$c]); return (int)$st->fetchColumn()>0; }

/* CSRF & flash */
$csrfToken = function_exists('csrf_token') ? csrf_token() : null;
function csrf_ok(){ if(!function_exists('check_csrf')) return true; try{ check_csrf(); return true; }catch(Throwable $e){ return false; } }
$flash=null; function set_flash($t,$m){ global $flash; $flash=['type'=>$t,'msg'=>$m]; }

/* Columnas presentes */
// --- MODIFICADO ---
$table = 'inv_categories';
$t_companies = 'inv_companies';
// --- FIN MODIFICADO ---
$hasParent   = has_col($pdo,$table,'parent_id');
$hasDesc     = has_col($pdo,$table,'description');
$hasActive   = has_col($pdo,$table,'is_active');
$hasCompany  = has_col($pdo,$table,'company_id');

/* Acciones */
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()){
  $action=$_POST['action'] ?? ''; $id=(int)($_POST['id'] ?? 0);
  $name=trim($_POST['name'] ?? '');
  
  if($action==='save' && $name!==''){
    $params=['name'=>$name]; $cols=['name=:name'];
    if($hasParent) { $params['parent'] = $_POST['parent_id'] ?: null;   $cols[]='parent_id=:parent'; }
    if($hasDesc)   { $params['desc']   = $_POST['description'] ?? null;  $cols[]='description=:desc'; }
    if($hasActive) { $params['active'] = isset($_POST['is_active'])?1:0; $cols[]='is_active=:active'; }
    if($hasCompany){ $params['company']= $_POST['company_id'] ?: null;   $cols[]='company_id=:company'; }
    
    try {
      if($id>0){ // Update
        $params['id']=$id;
        // --- MODIFICADO ---
        $pdo->prepare("UPDATE inv_categories SET ".implode(', ',$cols)." WHERE id=:id")->execute($params);
        // --- FIN MODIFICADO ---
        set_flash('success','Categoría #'.$id.' actualizada');
      }else{ // Create
        // --- MODIFICADO ---
        $pdo->prepare("INSERT INTO inv_categories SET ".implode(', ',$cols))->execute($params);
        // --- FIN MODIFICADO ---
        set_flash('success','Categoría creada');
      }
    }catch(Throwable $e){ set_flash('danger','Error guardando: '.$e->getMessage()); }
    header('Location: categorias.php'); exit;
  
  }elseif($action==='delete' && $id>0){
    try{
      // --- MODIFICADO ---
      $pdo->prepare("DELETE FROM inv_categories WHERE id=?")->execute([$id]);
      // --- FIN MODIFICADO ---
      set_flash('success','Categoría #'.$id.' eliminada');
    }catch(Throwable $e){ set_flash('danger','Error eliminando: '.$e->getMessage()); }
    header('Location: categorias.php'); exit;
  }
}

/* Listas (selects) */
// --- MODIFICADO ---
$companies=$hasCompany ? $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll() : [];
$categories=$hasParent ? $pdo->query("SELECT id,name FROM inv_categories ORDER BY name")->fetchAll() : [];
// --- FIN MODIFICADO ---

/* Filtros y Paginación */
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 25;
$q = trim($_GET['q'] ?? '');
$f_company = $hasCompany ? (int)($_GET['company_id'] ?? 0) : 0;
$f_parent = $hasParent ? (int)($_GET['parent_id'] ?? -1) : -1;
$params=[]; $where=['1=1'];
if($q!==''){ $where[]='(c.name LIKE ? OR c.description LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; }
if($f_company>0){ $where[]='c.company_id=?'; $params[]=$f_company; }
if($f_parent>=0) { $where[]=$f_parent==0 ? 'c.parent_id IS NULL' : 'c.parent_id=?'; if($f_parent>0) $params[]=$f_parent; }
$whereStr='WHERE '.implode(' AND ',$where);
$count=$pdo->prepare("SELECT COUNT(*) FROM $table c $whereStr"); $count->execute($params);
$totalRows=(int)$count->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$offset=($page-1)*$perPage;

/* Listado */
$cols = "c.*";
if($hasParent) $cols .= ", p.name AS parent_name";
if($hasCompany) $cols .= ", co.name AS company_name";
// --- MODIFICADO ---
$sql = "SELECT $cols FROM inv_categories c ";
if($hasParent) $sql .= " LEFT JOIN inv_categories p ON p.id=c.parent_id ";
if($hasCompany) $sql .= " LEFT JOIN inv_companies co ON co.id=c.company_id ";
// --- FIN MODIFICADO ---
$sql .= "$whereStr ORDER BY c.id DESC LIMIT $perPage OFFSET $offset";
$rows=$pdo->prepare($sql); $rows->execute($params); $list=$rows->fetchAll();

/* Fila para editar (si ?edit=ID) */
$editRow=null; $editID=(int)($_GET['edit'] ?? 0);
if($editID>0){ 
    // --- MODIFICADO ---
    $st=$pdo->prepare("SELECT * FROM inv_categories WHERE id=?"); 
    // --- FIN MODIFICADO ---
    $st->execute([$editID]); $editRow=$st->fetch(); 
}

$pageTitle = 'Categorías';
require __DIR__ . '/partials/header.php';
?>
<?php if($flash): ?><div class="alert alert-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div><?php endif; ?>

<?php if($editRow): ?>
<div class="card mb-3" id="editForm"><div class="card-body">
  <h5 class="card-title">Editando Categoría #<?= (int)$editRow['id'] ?></h5>
  <?php include __DIR__ . '/categorias_form.php'; ?>
</div></div>
<?php else: ?>
<p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rowModal"><i class="bi bi-plus-lg"></i> Nueva Categoría</button></p>
<div class="modal fade" id="rowModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Nueva Categoría</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <?php include __DIR__ . '/categorias_form.php'; ?>
    </div>
  </div></div>
</div>
<?php endif; ?>


<div class="card mb-3"><div class="card-body">
  <form method="get" class="row gx-2 gy-2 align-items-center">
    <div class="col-md-3"><input name="q" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($q) ?>"></div>
    <?php if($hasParent): ?>
    <div class="col-md-3"><select name="parent_id" class="form-select">
      <option value="-1">-- Categoría Padre --</option>
      <option value="0" <?= $f_parent===0?'selected':'' ?>>(Ninguna)</option>
      <?php foreach($categories as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$f_parent?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
    </select></div>
    <?php endif; ?>
    <?php if($hasCompany): ?>
    <div class="col-md-3"><select name="company_id" class="form-select">
      <option value="">-- Empresa --</option>
      <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$f_company?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
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
        <th>Nombre</th>
        <?php if($hasDesc): ?><th>Descripción</th><?php endif; ?>
        <?php if($hasParent): ?><th>Padre</th><?php endif; ?>
        <?php if($hasCompany): ?><th>Empresa</th><?php endif; ?>
        <?php if($hasActive): ?><th>Activa</th><?php endif; ?>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if($list): foreach($list as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <?php if($hasDesc): ?><td><?= htmlspecialchars($r['description'] ?? '') ?></td><?php endif; ?>
        <?php if($hasParent): ?><td><?= htmlspecialchars($r['parent_name'] ?? '-') ?></td><?php endif; ?>
        <?php if($hasCompany): ?><td><?= htmlspecialchars($r['company_name'] ?? '-') ?></td><?php endif; ?>
        <?php if($hasActive): ?><td><span class="badge bg-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Sí':'No' ?></span></td><?php endif; ?>
        
<td class="text-end text-nowrap action-gap">
  <a class="btn btn-sm btn-outline-primary btn-icon"
     href="categorias.php?<?= http_build_query(array_merge($_GET,['edit'=>(int)$r['id']])) ?>"
     data-bs-toggle="tooltip" data-bs-placement="top" title="Editar categoría" aria-label="Editar">
    <i class="bi bi-pencil"></i><span class="visually-hidden">Editar</span>
  </a>

  <form method="post" class="d-inline"
        onsubmit="return confirm('¿Eliminar categoría #<?= (int)$r['id'] ?>?');">
    <?php if($csrfToken): ?><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><?php endif; ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <button class="btn btn-sm btn-outline-danger btn-icon"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar categoría" aria-label="Eliminar">
      <i class="bi bi-trash"></i><span class="visually-hidden">Eliminar</span>
    </button>
  </form>
</td>

      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>
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
if($editID>0 && !$editRow) set_flash('danger','Categoría #'.$editID.' no encontrada.');
require __DIR__ . '/partials/footer.php';
?>