<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';

$company_id = (int)($_GET['company_id'] ?? 0);
$rows=[];

// --- MODIFICADO: Leer de inv_clients ---
// La consulta original filtraba por company_id, pero nuestra tabla inv_clients
// no tiene esa columna por defecto. Devolveremos todos los clientes.
// Si añades company_id a inv_clients, descomenta el WHERE.
$sql = "SELECT id, name, internal_ref FROM inv_clients ";
$params = [];
// if($company_id > 0){
//   $sql .= " WHERE company_id = ? ";
//   $params[] = $company_id;
// }
$sql .= " ORDER BY name";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
// --- FIN MODIFICADO ---

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
?>