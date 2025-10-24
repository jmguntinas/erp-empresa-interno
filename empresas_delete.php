<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

$id=(int)($_GET['id'] ?? 0);
if($id){
  $pdo->prepare("DELETE FROM companies WHERE id=?")->execute([$id]);
}
header('Location: empresas.php');
