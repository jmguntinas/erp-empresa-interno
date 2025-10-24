<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH', 'Empleado RRHH', 'Empleado']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica de Vacaciones ---
// (Adaptada al nuevo esquema 'hr_vacaciones', 'hr_empleados', 'hr_festivos')
try {
    // Cargar festivos
    $stmt_fest = $pdo->prepare("SELECT fecha FROM hr_festivos");
    $stmt_fest->execute();
    $festivos_raw = $stmt_fest->fetchAll(PDO::FETCH_COLUMN);
    $festivos_json = json_encode($festivos_raw);

    // Cargar vacaciones (aprobadas y pendientes)
    $stmt_vac = $pdo->prepare("SELECT 
                                v.id, 
                                v.fecha_inicio, 
                                v.fecha_fin, 
                                v.estado, 
                                CONCAT(e.nombre, ' ', e.apellidos) as empleado_nombre
                           FROM hr_vacaciones v
                           JOIN hr_empleados e ON v.empleado_id = e.id
                           WHERE v.estado IN ('aprobada', 'pendiente')");
    $stmt_vac->execute();
    $vacaciones = $stmt_vac->fetchAll();

    $eventos_calendario = [];
    foreach ($vacaciones as $vac) {
        $color = $vac['estado'] == 'aprobada' ? '#28a745' : '#ffc107'; // Verde o Amarillo
        $eventos_calendario[] = [
            'title' => $vac['empleado_nombre'],
            'start' => $vac['fecha_inicio'],
            'end'   => date('Y-m-d', strtotime($vac['fecha_fin'] . ' +1 day')), // FullCalendar 'end' es exclusivo
            'color' => $color,
            'url'   => has_role(['Admin General', 'Admin RRHH']) ? 'rrhh_gestionar_solicitudes.php?id=' . $vac['id'] : null // Enlace adaptado
        ];
    }
    $eventos_json = json_encode($eventos_calendario);

} catch (PDOException $e) {
    $error = "Error al cargar datos del calendario: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5/locales/es.js'></script>

<div class="container-fluid px-4">
    <h1 class="mt-4">Calendario de Vacaciones y Ausencias</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item active">RRHH - Calendario</li>
    </ol>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-alt me-1"></i>
            Calendario
            <div class="float-end">
                <a href="rrhh_solicitar_vacaciones.php" class="btn btn-success btn-sm ms-2">Solicitar Vacaciones</a>
                <?php if (has_role(['Admin General', 'Admin RRHH'])): ?>
                    <a href="rrhh_gestionar_solicitudes.php" class="btn btn-warning btn-sm ms-2">Gestionar Solicitudes</a>
                    <a href="rrhh_gestionar_festivos.php" class="btn btn-secondary btn-sm ms-2">Gestionar Festivos</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="leyenda" class="mb-3">
                <span class="badge bg-success">Aprobada</span>
                <span class="badge bg-warning text-dark">Pendiente</span>
                <span class="badge bg-danger">Festivo</span>
            </div>
            <div id="calendario-rrhh" style="max-height: 700px;"></div>
        </div>
    </div>
</div> <script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendario-rrhh');
    var festivos = <?php echo $festivos_json; ?>;
    var eventos = <?php echo $eventos_json; ?>;
    
    // Añadir festivos a los eventos
    festivos.forEach(function(fecha) {
        eventos.push({
            title: 'Festivo',
            start: fecha,
            allDay: true,
            display: 'background',
            color: '#dc3545' // Rojo
        });
    });

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: eventos,
        businessHours: { // Marcar fines de semana
            daysOfWeek: [ 1, 2, 3, 4, 5 ], // Lunes a Viernes
        },
        eventClick: function(info) {
            if (info.event.url) {
                // Abrir el enlace de detalle si existe
                window.location = info.event.url;
                info.jsEvent.preventDefault(); // Evitar la acción por defecto
            }
        }
    });
    calendar.render();
});
</script>

<?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>