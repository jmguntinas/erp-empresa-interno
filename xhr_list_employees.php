<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

$company_id = (int)($_GET['company_id'] ?? 0);
$rows=[];

if($company_id > 0){
  // --- MODIFICADO: Leer de hr_empleados, filtrar por company_id ---
  $st=$pdo->prepare("
      SELECT id, CONCAT(nombre, ' ', apellidos) as name
      FROM hr_empleados
      WHERE company_id = ?
      -- Puedes añadir un filtro por usuario activo si hr_empleados tuviera is_active
      -- AND is_active = 1
      ORDER BY apellidos, nombre
  ");
  $st->execute([$company_id]);
  $rows=$st->fetchAll();
  // --- FIN MODIFICADO ---
} else {
    // Opcional: Si no se especifica compañía, devolver todos los empleados activos?
    // $st = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellidos) as name FROM hr_empleados ORDER BY apellidos, nombre");
    // $rows = $st->fetchAll();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
?>