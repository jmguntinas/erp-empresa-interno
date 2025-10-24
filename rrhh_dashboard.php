<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH', 'Empleado RRHH']); // Proteger página
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica del Dashboard de RRHH ---
// (Adaptada al nuevo esquema 'hr_')
try {
    // KPI 1: Número total de empleados
    $stmt_total = $pdo->prepare("SELECT COUNT(*) as total FROM hr_empleados");
    $stmt_total->execute();
    $total_empleados = $stmt_total->fetchColumn();

    // KPI 2: Próximas vacaciones (aprobadas)
    $stmt_vac = $pdo->prepare("SELECT e.nombre, e.apellidos, v.fecha_inicio 
                               FROM hr_vacaciones v 
                               JOIN hr_empleados e ON v.empleado_id = e.id 
                               WHERE v.estado = 'aprobada' AND v.fecha_inicio >= CURDATE() 
                               ORDER BY v.fecha_inicio ASC 
                               LIMIT 5");
    $stmt_vac->execute();
    $proximas_vacaciones = $stmt_vac->fetchAll();

    // KPI 3: Peticiones pendientes
    $stmt_pend = $pdo->prepare("SELECT COUNT(*) FROM hr_vacaciones WHERE estado = 'pendiente'");
    $stmt_pend->execute();
    $vacaciones_pendientes = $stmt_pend->fetchColumn();

} catch (PDOException $e) {
    $error = "Error al cargar KPIs: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard de Recursos Humanos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item active">RRHH - Dashboard</li>
    </ol>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="fs-3"><?php echo $total_empleados ?? 0; ?></div>
                    <div>Empleados Activos</div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="rrhh_empleados.php">Ver Detalles</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="fs-3"><?php echo $vacaciones_pendientes ?? 0; ?></div>
                    <div>Solicitudes Pendientes</div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="rrhh_gestionar_solicitudes.php">Ver Detalles</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-calendar-check me-1"></i>Próximas Vacaciones Aprobadas</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>Empleado</th><th>Fecha Inicio</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($proximas_vacaciones)): ?>
                                <?php foreach ($proximas_vacaciones as $vac): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vac['nombre'] . ' ' . $vac['apellidos']); ?></td>
                                        <td><?php echo date("d/m/Y", strtotime($vac['fecha_inicio'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">No hay vacaciones próximas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>

</div> <?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>