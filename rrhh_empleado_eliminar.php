<?php
// Lógica pura: Usamos auth y db del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: rrhh_empleados.php");
    exit;
}

try {
    // Opcional: Eliminar la foto del empleado, excepto si es 'default.png'
    $stmt_foto = $pdo->prepare("SELECT foto FROM hr_empleados WHERE id = ?");
    $stmt_foto->execute([$id]);
    $foto = $stmt_foto->fetchColumn();

    if ($foto && $foto != 'default.png') {
        $file_path = __DIR__ . '/uploads/fotos_empleados/' . $foto;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Eliminar el registro de la tabla 'hr_empleados'
    $stmt = $pdo->prepare("DELETE FROM hr_empleados WHERE id = ?");
    $stmt->execute([$id]);

    // Redirigir de vuelta al listado
    header("Location: rrhh_empleados.php");
    exit;

} catch (PDOException $e) {
    // Manejo de errores (ej. restricción de clave foránea si tiene vacaciones)
    die("Error al eliminar el empleado. Es posible que tenga registros asociados (vacaciones, etc.): " . $e->getMessage());
}
?>