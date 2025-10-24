<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php'; check_csrf();

$id=(int)($_POST['id'] ?? 0);
if($id>0){
  // Podrías añadir comprobación de roles aquí si solo Admins pueden borrar
  // if (!has_role(['Admin General', 'Admin Inventario'])) { die('No autorizado'); }

  // --- MODIFICADO: Eliminar de inv_movements ---
  // IMPORTANTE: Esto NO revierte el cambio de stock. Solo borra el registro.
  $pdo->prepare("DELETE FROM inv_movements WHERE id=?")->execute([$id]);
  // --- FIN MODIFICADO ---
  set_flash('info', 'Movimiento #' . $id . ' eliminado (el stock no se revierte).');
}
$ref = $_SERVER['HTTP_REFERER'] ?? 'movimientos.php';
header('Location: ' . $ref);
exit; // Asegurar que el script termina
?>