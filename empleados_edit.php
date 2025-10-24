<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; 
// --- MODIFICADO: Eliminado check_csrf() ---

$id=(int)($_GET['id'] ?? 0);
$companies=$pdo->query("SELECT id,name FROM companies WHERE is_active=1 ORDER BY name")->fetchAll();
$employee=null; $superIDs=[];
$depts=[];
if($id){
  $st=$pdo->prepare("SELECT * FROM employees WHERE id=?");
  $st->execute([$id]); $employee=$st->fetch();
  if(!$employee){ header('Location: empleados.php'); exit; }
  $d=$pdo->prepare("SELECT id,name FROM departments WHERE company_id=? ORDER BY name");
  $d->execute([$employee['company_id']]); $depts=$d->fetchAll();
  $ss=$pdo->prepare("SELECT supervisor_id FROM employee_supervisors WHERE employee_id=?");
  $ss->execute([$id]); $superIDs=array_map('intval', array_column($ss->fetchAll(), 'supervisor_id'));
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  // --- MODIFICADO: Validación CSRF ---
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
      die('Error de validación (CSRF). Intente recargar la página.');
  }
  // --- FIN MODIFICADO ---
  try{
    $cid=(int)($_POST['company_id'] ?? 0);
    $did=(int)($_POST['department_id'] ?? 0) ?: null;
    $name=trim($_POST['name'] ?? '');
    $email=trim($_POST['email'] ?? '');
    $is_active= isset($_POST['is_active'])?1:0;
    if(!$cid || $name==='') throw new Exception('Empresa y nombre requeridos');

    if($id){
      $pdo->prepare("UPDATE employees SET company_id=?, department_id=?, name=?, email=?, is_active=? WHERE id=?")
          ->execute([$cid,$did,$name,$email,$is_active,$id]);
      // supervisors
      $pdo->prepare("DELETE FROM employee_supervisors WHERE employee_id=?")->execute([$id]);
      foreach(($_POST['supervisors'] ?? []) as $sup){
        $sid=(int)$sup; if($sid>0 && $sid!=$id){
          $pdo->prepare("INSERT INTO employee_supervisors(employee_id,supervisor_id) VALUES (?,?)")->execute([$id,$sid]);
        }
      }
      $msg='Guardado';
    } else {
      $pdo->prepare("INSERT INTO employees(company_id,department_id,name,email,is_active) VALUES (?,?,?,?,?)")
          ->execute([$cid,$did,$name,$email,$is_active]);
      $id=(int)$pdo->lastInsertId();
      header('Location: empleados_edit.php?id='.$id); exit;
    }
  }catch(Throwable $e){ $msg='Error: '.$e->getMessage(); }
}

include __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3"><i class="bi bi-person-badge"></i> <?= $id?'Editar':'Nuevo' ?> empleado</h4>
<?php if($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Empresa</label>
      <select class="form-select" name="company_id" required onchange="this.form.submit()">
        <option value="">-- Elegir --</option>
        <?php foreach($companies as $co): ?><option value="<?= $co['id'] ?>" <?= (int)($employee['company_id']??0)==$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option><?php endforeach; ?>
      </select>
      <div class="form-text">Al cambiar empresa, se recargará para mostrar departamentos/supervisores de esa empresa.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">Departamento</label>
      <select class="form-select" name="department_id">
        <option value="">(sin asignar)</option>
        <?php
          if(!$depts && ($employee['company_id']??0)){
            $d=$pdo->prepare("SELECT id,name FROM departments WHERE company_id=? ORDER BY name");
            $d->execute([$employee['company_id']]); $depts=$d->fetchAll();
          }
          foreach($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (int)($employee['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" id="ia" <?= ($employee['is_active']??1)?'checked':'' ?>>
      <label class="form-check-label" for="ia">Activo</label>
    </div>
    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="<?= htmlspecialchars($employee['name'] ?? '') ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($employee['email'] ?? '') ?>"></div>

    <?php if($id): ?>
    <div class="col-12">
      <label class="form-label">Supervisores</label>
      <select class="form-select" name="supervisors[]" multiple size="6">
        <?php
          $emps = $pdo->prepare("SELECT id,name FROM employees WHERE company_id=? AND is_active=1 ORDER BY name");
          $emps->execute([$employee['company_id']]); $opts=$emps->fetchAll();
          foreach($opts as $o): if($o['id']==$id) continue; ?>
            <option value="<?= $o['id'] ?>" <?= in_array($o['id'],$superIDs,true)?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Ctrl/Cmd para seleccionar múltiples.</div>
    </div>
    <?php endif; ?>

    <div class="col-12">
      <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="empleados.php">Volver</a>
    </div>
  </div>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>