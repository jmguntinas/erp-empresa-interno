<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica de Empleados ---
// (Adaptada al nuevo esquema 'hr_empleados')
try {
    $stmt = $pdo->prepare("SELECT * FROM hr_empleados ORDER BY apellidos, nombre");
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar empleados: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Empleados</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item active">RRHH - Empleados</li>
    </ol>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Listado de Empleados
            <a href="rrhh_empleado_form.php" class="btn btn-primary btn-sm float-end">Añadir Empleado</a>
        </div>
        <div class="card-body">
            <table id="datatablesSimple"> 
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>NIF</th>
                        <th>Puesto</th>
                        <th>Departamento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $empleado): ?>
                        <tr>
                            <td>
                                <img src="uploads/fotos_empleados/<?php echo htmlspecialchars($empleado['foto']); ?>" alt="Foto" width="50" class="img-thumbnail">
                            </td>
                            <td><?php echo htmlspecialchars($empleado['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($empleado['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($empleado['nif']); ?></td>
                            <td><?php echo htmlspecialchars($empleado['puesto']); ?></td>
                            <td><?php echo htmlspecialchars($empleado['departamento']); ?></td>
                            <td>
                                <a href="rrhh_empleado_ver.php?id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a>
                                <a href="rrhh_empleado_form.php?id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="rrhh_empleado_eliminar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro?');"><i class="fas fa-trash"></i></a>
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