<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

// --- MODIFICADO ---
$companies=$pdo->query("SELECT id,name FROM inv_companies WHERE is_active=1 ORDER BY name")->fetchAll();
// --- FIN MODIFICADO ---
$company_id=(int)($_GET['company_id'] ?? 0);
$q=trim($_GET['q'] ?? '');
$params=[]; $where=['1=1'];
if($company_id){ $where[]='c.company_id=?'; $params[]=$company_id; }
if($q!==''){ $where[]='(c.name LIKE ? OR c.internal_ref LIKE ?)'; array_push($params,"%$q%\",\"%$q%\"); }
$where='WHERE '.implode(' AND ',$where);

// --- MODIFICADO ---
$rows=$pdo->prepare("SELECT c.*, co.name AS company FROM inv_clients c JOIN inv_companies co ON co.id=c.company_id $where ORDER BY co.name, c.name");
// --- FIN MODIFICADO ---
$rows->execute($params); $list=$rows->fetchAll();

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $cid=(int)($_POST['company_id'] ?? 0);
    $name=trim($_POST['name'] ?? '');
    $ref=trim($_POST['internal_ref'] ?? '');
    $email=trim($_POST['email'] ?? '');
    $phone=trim($_POST['phone'] ?? '');
    if(!$cid || $name==='') throw new Exception('Empresa y nombre requeridos');
    // --- MODIFICADO ---
    $st=$pdo->prepare("INSERT INTO inv_clients(company_id,name,internal_ref,email,phone) VALUES(?,?,?,?,?)");
    // --- FIN MODIFICADO ---
    $st->execute([$cid,$name,$ref,$email,$phone]);
    header('Location: clientes.php'); exit;
  }catch(Throwable $e){ $msg='<div class="alert alert-danger">'.$e->getMessage().'</div>'; }
}

$pageTitle = 'Clientes';
require __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3">Clientes</h4>
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
  <h5 class="card-title">Añadir cliente</h5>
  <form class="row gx-2 gy-2" method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="col-md-3"><label class="form-label">Empresa</label><select name="company_id" class="form-select">
      <option value="">-- Selecciona --</option>
      <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$company_id?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
    </select></div>
    <div class="col-md-3"><label class="form-label">Nombre cliente</label><input class="form-control" name="name"></div>
    <div class="col-md-2"><label class="form-label">Ref. interna</label><input class="form-control" name="internal_ref"></div>
    <div class="col-md-2"><label class="form-label">Email</label><input class="form-control" name="email"></div>
    <div class="col-md-2"><label class="form-label">Tel.</label><input class="form-control" name="phone"></div>
    <div class="col-12 text-end">
      <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir</button>
    </div>
  </form>
</div></div>

<div class="table-responsive">
<table class="table table-sm">
  <thead><tr><th>Empresa</th><th>Cliente</th><th>Ref.</th><th>Email</th><th>Tel.</th><th class="text-end">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($list as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['company']) ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['internal_ref'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['email'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['phone'] ?? '-') ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-danger" href="clientes_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('¿Eliminar cliente <?= htmlspecialchars($r['name']) ?>?')">
          <i class="bi bi-trash"></i>
        </a>
      </td>
    </tr>
  <?php endforeach; if(!$list): ?>
    <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>