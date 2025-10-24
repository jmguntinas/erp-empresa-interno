<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

// --- MODIFICADO: Leer de inv_companies ---
$companies=$pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active
// --- FIN MODIFICADO ---

$company_id=(int)($_GET['company_id'] ?? 0);
$clientRows=[];
if($company_id){
  // --- MODIFICADO: Leer de inv_clients (asumiendo que tiene company_id) ---
  // $st=$pdo->prepare("SELECT id,name FROM inv_clients WHERE company_id=? ORDER BY name");
  // $st->execute([$company_id]); $clientRows=$st->fetchAll();
  // Si inv_clients no tiene company_id, cargamos todos:
  $clientRows = $pdo->query("SELECT id, name FROM inv_clients ORDER BY name")->fetchAll();
  // --- FIN MODIFICADO ---
}
$q=trim($_GET['q'] ?? '');
$params=[]; $where=['1=1'];
if($company_id){ $where[]='p.company_id=?'; $params[]=$company_id; }
if($q!==''){ $where[]='(p.name LIKE ? OR p.internal_ref LIKE ? OR cl.name LIKE ?)'; array_push($params,"%$q%","%$q%","%$q%"); }
$where='WHERE '.implode(' AND ',$where);

// --- MODIFICADO: Leer de inv_projects, inv_companies, inv_clients ---
$rows=$pdo->prepare("
  SELECT p.*, co.name AS company, cl.name AS client
  FROM inv_projects p
  JOIN inv_companies co ON co.id=p.company_id
  JOIN inv_clients cl ON cl.id=p.client_id
  $where
  ORDER BY co.name, cl.name, p.name
");
// --- FIN MODIFICADO ---
$rows->execute($params); $list=$rows->fetchAll();

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $cid=(int)($_POST['company_id'] ?? 0);
    $clid=(int)($_POST['client_id'] ?? 0);
    $name=trim($_POST['name'] ?? '');
    $ref=trim($_POST['internal_ref'] ?? '');
    if(!$cid || !$clid || $name==='') throw new Exception('Empresa, Cliente y Nombre requeridos');
    // --- MODIFICADO: Insertar en inv_projects ---
    $st=$pdo->prepare("INSERT INTO inv_projects(company_id,client_id,name,internal_ref) VALUES(?,?,?,?)");
    // --- FIN MODIFICADO ---
    $st->execute([$cid,$clid,$name,$ref]);
    header('Location: proyectos.php?company_id='.$cid); exit; // Redirigir con filtro
  }catch(Throwable $e){ $msg='<div class="alert alert-danger">'.$e->getMessage().'</div>'; }
}

$pageTitle = 'Proyectos';
require __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3">Proyectos</h4>
<?= $msg ?>
<div class="card mb-3"><div class="card-body">
  <form class="row gx-2 gy-2" method="get">
    <div class="col-md-3"><select name="company_id" class="form-select" onchange="this.form.submit()">
      <option value="">-- Filtrar por empresa --</option>
      <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$company_id?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
    </select></div>
    <div class="col-md-3"><input name="q" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($q) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary">Buscar</button></div>
  </form>
</div></div>

<div class="card mb-3"><div class="card-body">
  <h5 class="card-title">Añadir proyecto</h5>
  <form class="row gx-2 gy-2" method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="col-md-3"><label class="form-label">Empresa</label>
      <select name="company_id" class="form-select" required>
        <option value="">-- Selecciona --</option>
        <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$company_id?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Cliente</label>
      <select name="client_id" class="form-select" required>
        <option value="">-- Selecciona Empresa primero --</option>
        <?php foreach($clientRows as $cl): ?><option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" name="name" required></div>
    <div class="col-md-3"><label class="form-label">Ref. interna</label><input class="form-control" name="internal_ref"></div>
    <div class="col-12 text-end">
      <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir</button>
    </div>
  </form>
</div></div>

<div class="table-responsive">
<table class="table table-sm">
  <thead><tr><th>Empresa</th><th>Cliente</th><th>Proyecto</th><th>Ref.</th><th class="text-end">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($list as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['company']) ?></td>
      <td><?= htmlspecialchars($r['client']) ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['internal_ref'] ?? '-') ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-danger" href="proyectos_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('¿Eliminar?')">
          <i class="bi bi-trash"></i>
        </a>
      </td>
    </tr>
  <?php endforeach; if(!$list): ?>
    <tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>