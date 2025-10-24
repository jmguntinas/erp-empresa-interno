<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();
$id=(int)($_GET['id'] ?? 0);
if($id){
  $pdo->prepare("DELETE FROM employee_supervisors WHERE employee_id=? OR supervisor_id=?")->execute([$id,$id]);
  $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
}
header('Location: empleados.php');
