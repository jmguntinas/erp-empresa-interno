<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; 
// --- MODIFICADO: Eliminado check_csrf() ---

$id = (int)($_GET['id'] ?? 0);
$company = null;
if($id){
  $st=$pdo->prepare("SELECT * FROM companies WHERE id=?");
  $st->execute([$id]); $company=$st->fetch();
  if(!$company){ header('Location: empresas.php'); exit; }
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  // --- MODIFICADO: Validación CSRF ---
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
      die('Error de validación (CSRF). Intente recargar la página.');
  }
  // --- FIN MODIFICADO ---
  try{
    $name = trim($_POST['name'] ?? '');
    $nif  = trim($_POST['nif'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Logo
    $logo_url = $company['logo_url'] ?? null;
    if(!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])){
      @mkdir(__DIR__ . '/uploads/', 0777, true);
      $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
      $fname = 'logo_'.time().'_'.mt_rand(1000,9999).'.'.strtolower($ext);
      $dest = __DIR__ . '/uploads/' . $fname;
      move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
      $logo_url = 'uploads/' . $fname;
    }

    if($id){
      $pdo->prepare("UPDATE companies SET name=?, nif=?, address=?, phone=?, email=?, is_active=?, logo_url=? WHERE id=?")
          ->execute([$name,$nif,$address,$phone,$email,$is_active,$logo_url,$id]);
      $msg='Guardado';
    } else {
      $pdo->prepare("INSERT INTO companies(name,nif,address,phone,email,is_active,logo_url) VALUES (?,?,?,?,?,?,?)")
          ->execute([$name,$nif,$address,$phone,$email,$is_active,$logo_url]);
      $id = (int)$pdo->lastInsertId();
      header('Location: empresas_edit.php?id='.$id); exit;
    }
  }catch(Throwable $e){ $msg='Error: '.$e->getMessage(); }
}

include __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3"><i class="bi bi-building"></i> <?= $id?'Editar':'Nueva' ?> empresa</h4>
<?php if($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required></div>
    <div class="col-md-3"><label class="form-label">NIF</label><input class="form-control" name="nif" value="<?= htmlspecialchars($company['nif'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Teléfono</label><input class="form-control" name="phone" value="<?= htmlspecialchars($company['phone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($company['email'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Dirección</label><input class="form-control" name="address" value="<?= htmlspecialchars($company['address'] ?? '') ?>"></div>
    <div class="col-md-6">
      <label class="form-label">Logo</label>
      <input class="form-control" type="file" name="logo" accept="image/*">
      <?php if(!empty($company['logo_url'])): ?>
        <div class="mt-2"><img src="<?= htmlspecialchars($company['logo_url']) ?>" style="max-height:64px"></div>
      <?php endif; ?>
    </div>
    <div class="col-md-6 form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" id="ia" <?= ($company['is_active']??1)?'checked':'' ?>>
      <label class="form-check-label" for="ia">Empresa activa</label>
    </div>
    <div class="col-12">
      <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="empresas.php">Volver</a>
    </div>
  </div>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>