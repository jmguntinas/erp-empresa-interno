<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();
$id=(int)($_GET['id'] ?? 0);
// --- MODIFICADO ---
if($id){ $pdo->prepare("DELETE FROM inv_clients WHERE id=?")->execute([$id]); }
// --- FIN MODIFICADO ---
header('Location: clientes.php');