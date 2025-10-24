<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();
$id=(int)($_GET['id'] ?? 0);
if($id){
    // --- MODIFICADO: Eliminar de inv_projects ---
    $pdo->prepare("DELETE FROM inv_projects WHERE id=?")->execute([$id]);
    // --- FIN MODIFICADO ---
}
header('Location: proyectos.php');
?>