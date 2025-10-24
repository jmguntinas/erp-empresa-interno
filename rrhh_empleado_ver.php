<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica de Ver Empleado ---
$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: rrhh_empleados.php');
    exit;
}

try {
    // Usar la tabla 'hr_empleados' y unir con 'global_users' e 'inv_companies'
    $stmt = $pdo->prepare("
        SELECT 
            e.*, 
            u.username as nombre_usuario,
            c.name as nombre_compania
        FROM hr_empleados e
        LEFT JOIN global_users u ON e.user_id = u.id
        LEFT JOIN inv_companies c ON e.company_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empleado) {
        die("Empleado no encontrado.");
    }
} catch (PDOException $e) {
    die("Error al cargar empleado: " . $e->getMessage());
}
// --- Fin Lógica ---

function display_field($label, $value) {
    echo '<div class="row mb-2">';
    echo '<dt class="col-sm-4">' . htmlspecialchars($label) . ':</dt>';
    echo '<dd class="col-sm-8">' . ($value ? htmlspecialchars($value) : '<em class="text-muted">No especificado</em>') . '</dd>';
    echo '</div>';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Ficha del Empleado</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item"><a href="rrhh_empleados.php">Empleados</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            Datos de <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
            <a href="rrhh_empleado_form.php?id=<?php echo $empleado['id']; ?>" class="btn btn-warning btn-sm float-end">Editar Empleado</a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <img src="uploads/fotos_empleados/<?php echo htmlspecialchars($empleado['foto']); ?>" alt="Foto" class="img-thumbnail mb-3" width="200">
                </div>
                
                <div class="col-md-9">
                    <ul class="nav nav-tabs" id="empleadoTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">Datos Personales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="laboral-tab" data-bs-toggle="tab" data-bs-target="#laboral" type="button" role="tab" aria-controls="laboral" aria-selected="false">Datos Laborales</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="empleadoTabContent">
                        <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                            <dl class="mt-3">
                                <?php display_field('Nombre Completo', $empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
                                <?php display_field('NIF/NIE', $empleado['nif']); ?>
                                <?php display_field('Nº Seguridad Social', $empleado['ss']); ?>
                                <?php display_field('Fecha Nacimiento', $empleado['fecha_nacimiento'] ? date("d/m/Y", strtotime($empleado['fecha_nacimiento'])) : ''); ?>
                                <?php display_field('Dirección', $empleado['direccion']); ?>
                                <?php display_field('Teléfono', $empleado['telefono']); ?>
                                <?php display_field('Email Personal', $empleado['email_personal']); ?>
                                <?php display_field('Estado Civil', $empleado['estado_civil']); ?>
                            </dl>
                        </div>
                        <div class="tab-pane fade" id="laboral" role="tabpanel" aria-labelledby="laboral-tab">
                            <dl class="mt-3">
                                <?php display_field('Departamento', $empleado['departamento']); ?>
                                <?php display_field('Puesto', $empleado['puesto']); ?>
                                <?php display_field('Fecha Contratación', $empleado['fecha_contratacion'] ? date("d/m/Y", strtotime($empleado['fecha_contratacion'])) : ''); ?>
                                <?php display_field('Tipo de Contrato', $empleado['tipo_contrato']); ?>
                                <?php display_field('Salario Bruto Anual', $empleado['salario_bruto_anual'] ? number_format($empleado['salario_bruto_anual'], 2, ',', '.') . ' €' : ''); ?>
                                <?php display_field('Comunidad (IRPF)', $empleado['comunidad_autonoma']); ?>
                                <hr>
                                <?php display_field('Usuario Sistema', $empleado['nombre_usuario']); ?>
                                <?php display_field('Compañía (ERP)', $empleado['nombre_compania']); ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div> <?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>