<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

// --- MODIFICADO: Leer compañías de 'inv_companies' ---
$companies = $pdo->query("SELECT id,name FROM inv_companies WHERE is_active=1 ORDER BY name")->fetchAll();
// --- FIN MODIFICADO ---

$company_id = (int)($_GET['company_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$deptRows=[]; // La gestión de departamentos ahora está en RRHH o en inv_departments

$params=[]; $where=['1=1'];
if($company_id){ 
    // --- MODIFICADO: Filtrar 'hr_empleados' por 'company_id' ---
    $where[]='e.company_id=?'; 
    $params[]=$company_id; 
    // --- FIN MODIFICADO ---
}
if($q!==''){ 
    // --- MODIFICADO: Buscar en 'hr_empleados' ---
    $where[]='(e.nombre LIKE ? OR e.apellidos LIKE ? OR e.email_personal LIKE ?)'; 
    array_push($params,"%$q%","%$q%","%$q%"); 
    // --- FIN MODIFICADO ---
}
$where='WHERE '.implode(' AND ',$where);

// --- MODIFICADO: Consulta principal a 'hr_empleados' uniendo 'inv_companies' ---
$rows=$pdo->prepare("
  SELECT 
    e.id, 
    e.nombre, 
    e.apellidos, 
    e.departamento, -- Tomamos el departamento de RRHH
    e.email_personal, 
    e.puesto,
    c.name AS company,
    u.is_active -- Usamos el estado del usuario global para 'activo'
  FROM hr_empleados e
  JOIN inv_companies c ON c.id=e.company_id
  LEFT JOIN global_users u ON u.id = e.user_id -- Unir con usuarios para saber si está activo
  $where
  ORDER BY c.name, e.apellidos, e.nombre
");
// --- FIN MODIFICADO ---
$rows->execute($params); $list=$rows->fetchAll();

$pageTitle = 'Empleados (Vista ERP)';
require __DIR__ . '/partials/header.php';
?>
<h4 class="mb-3">Empleados (Vista ERP)</h4>
<p class="text-muted">Este es un listado de consulta de los empleados registrados en RRHH, filtrados por compañía del ERP. La gestión (altas, bajas, ediciones) se realiza en el módulo de Recursos Humanos.</p>

<form class="card mb-3" method="get">
  <div class="card-body row gx-2 gy-2 align-items-center">
    <div class="col-md-3">
      <select name="company_id" class="form-select" onchange="this.form.submit()">
        <option value="">-- Filtrar por empresa --</option>
        <?php foreach($companies as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$company_id?' selected':'').'>'.htmlspecialchars($c['name']).'</option>' ?>
      </select>
    </div>
    <div class="col-md-6"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nombre, apellidos o email"></div>
    <div class="col-md-3 text-md-end">
      <button class="btn btn-outline-secondary">Filtrar</button>
      <a href="rrhh_empleado_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ir a RRHH</a>
    </div>
  </div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead>
      <tr>
          <th>Empresa (ERP)</th>
          <th>Nombre</th>
          <th>Apellidos</th>
          <th>Depto (RRHH)</th>
          <th>Email (Personal)</th>
          <th>Puesto (RRHH)</th>
          <th>Activo (Sistema)</th>
          <th class="text-end">Acciones</th>
      </tr>
  </thead>
  <tbody>
  <?php foreach($list as $e): ?>
    <tr>
      <td><?= htmlspecialchars($e['company']) ?></td>
      <td><?= htmlspecialchars($e['nombre']) ?></td>
      <td><?= htmlspecialchars($e['apellidos']) ?></td>
      <td><?= htmlspecialchars($e['departamento'] ?? '-') ?></td>
      <td><?= htmlspecialchars($e['email_personal'] ?? '-') ?></td>
      <td><?= htmlspecialchars($e['puesto'] ?? '-') ?></td>
      <td>
          <?php 
          // El estado 'activo' ahora viene de la tabla global_users si está vinculado
          $isActive = isset($e['is_active']) ? (bool)$e['is_active'] : false; 
          ?>
           <span class="badge bg-<?= $isActive ? 'success' : 'secondary' ?>"><?= $isActive ?'Sí':'No' ?></span>
      </td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-primary" href="rrhh_empleado_ver.php?id=<?= (int)$e['id'] ?>" title="Ver en RRHH"><i class="bi bi-eye"></i></a>
        <a class="btn btn-sm btn-outline-warning" href="rrhh_empleado_form.php?id=<?= (int)$e['id'] ?>" title="Editar en RRHH"><i class="bi bi-pencil"></i></a>
        </td>
    </tr>
  <?php endforeach; if(empty($list)): ?>
    <tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>