<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica de Gestionar Solicitudes ---
try {
    // Cargar todas las solicitudes, uniendo con empleados
    $stmt = $pdo->prepare("
        SELECT 
            v.*, 
            CONCAT(e.nombre, ' ', e.apellidos) as empleado_nombre
        FROM hr_vacaciones v
        JOIN hr_empleados e ON v.empleado_id = e.id
        ORDER BY v.estado ASC, v.fecha_solicitud DESC
    ");
    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar solicitudes: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestionar Solicitudes de Vacaciones</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item"><a href="rrhh_vacaciones.php">Calendario</a></li>
        <li class="breadcrumb-item active">Gestionar Solicitudes</li>
    </ol>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-tasks me-1"></i>
            Listado de Solicitudes
        </div>
        <div class="card-body">
            <table id="datatablesSimple"> 
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Fechas</th>
                        <th>Días</th>
                        <th>Fecha Solicitud</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $vac): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vac['empleado_nombre']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($vac['fecha_inicio'])); ?> - <?php echo date("d/m/Y", strtotime($vac['fecha_fin'])); ?></td>
                            <td><?php echo $vac['dias_solicitados']; ?></td>
                            <td><?php echo date("d/m/Y H:i", strtotime($vac['fecha_solicitud'])); ?></td>
                            <td>
                                <?php
                                $badge_class = 'bg-secondary';
                                if ($vac['estado'] == 'aprobada') $badge_class = 'bg-success';
                                if ($vac['estado'] == 'pendiente') $badge_class = 'bg-warning text-dark';
                                if ($vac['estado'] == 'rechazada') $badge_class = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($vac['estado']); ?></span>
                            </td>
                            <td>
                                <?php if ($vac['estado'] == 'pendiente'): ?>
                                    <a href="rrhh_aprobar_vacaciones.php?id=<?php echo $vac['id']; ?>&accion=aprobar" class="btn btn-sm btn-success" title="Aprobar"><i class="fas fa-check"></i></a>
                                    <a href="rrhh_aprobar_vacaciones.php?id=<?php echo $vac['id']; ?>&accion=rechazar" class="btn btn-sm btn-danger" title="Rechazar"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> <?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>