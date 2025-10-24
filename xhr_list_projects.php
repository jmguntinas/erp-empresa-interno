<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

$company_id = (int)($_GET['company_id'] ?? 0);
$rows=[];

if($company_id > 0){
  // --- MODIFICADO: Leer de inv_projects ---
  $st=$pdo->prepare("SELECT id, name, internal_ref FROM inv_projects WHERE company_id=? ORDER BY name");
  // --- FIN MODIFICADO ---
  $st->execute([$company_id]);
  $rows=$st->fetchAll();
} else {
    // Opcional: Devolver todos si no hay compañía?
    // $rows = $pdo->query("SELECT id, name, internal_ref FROM inv_projects ORDER BY name")->fetchAll();
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
?>