<?php
// Lógica pura: Usamos auth y db del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';

// Validar parámetros GET
$id = $_GET['id'] ?? null;
$accion = $_GET['accion'] ?? null;
$user_id_gestor = $_SESSION['user_id']; 

if (!$id || !in_array($accion, ['aprobar', 'rechazar'])) {
    header("Location: rrhh_gestionar_solicitudes.php");
    exit;
}

$nuevo_estado = ($accion == 'aprobar') ? 'aprobada' : 'rechazada';

try {
    // Usar la tabla 'hr_vacaciones'
    $stmt = $pdo->prepare("UPDATE hr_vacaciones SET estado = ?, gestionado_por_id = ? WHERE id = ? AND estado = 'pendiente'");
    $stmt->execute([$nuevo_estado, $user_id_gestor, $id]);

    // Redirigir de vuelta al listado de gestión
    header("Location: rrhh_gestionar_solicitudes.php");
    exit;

} catch (PDOException $e) {
    die("Error al procesar la solicitud: " . $e->getMessage());
}
?><?php
// Lógica pura: Usamos auth y db del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';

// Validar parámetros GET
$id = $_GET['id'] ?? null;
$accion = $_GET['accion'] ?? null;
$user_id_gestor = $_SESSION['user_id']; 

if (!$id || !in_array($accion, ['aprobar', 'rechazar'])) {
    header("Location: rrhh_gestionar_solicitudes.php");
    exit;
}

$nuevo_estado = ($accion == 'aprobar') ? 'aprobada' : 'rechazada';

try {
    // Usar la tabla 'hr_vacaciones'
    $stmt = $pdo->prepare("UPDATE hr_vacaciones SET estado = ?, gestionado_por_id = ? WHERE id = ? AND estado = 'pendiente'");
    $stmt->execute([$nuevo_estado, $user_id_gestor, $id]);

    // Redirigir de vuelta al listado de gestión
    header("Location: rrhh_gestionar_solicitudes.php");
    exit;

} catch (PDOException $e) {
    die("Error al procesar la solicitud: " . $e->getMessage());
}
?>