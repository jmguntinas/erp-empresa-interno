<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica del Formulario de Empleado ---
$empleado = [
    'id' => '', 'nombre' => '', 'apellidos' => '', 'nif' => '', 'ss' => '',
    'fecha_nacimiento' => '', 'direccion' => '', 'telefono' => '', 'email_personal' => '',
    'departamento' => '', 'puesto' => '', 'fecha_contratacion' => '', 'salario_bruto_anual' => '',
    'comunidad_autonoma' => '', 'estado_civil' => '', 'tipo_contrato' => '', 'user_id' => null, 'company_id' => null
];
$titulo = "Añadir Nuevo Empleado";
$action_url = "rrhh_guardar_empleado.php";

if (isset($_GET['id'])) {
    $titulo = "Editar Empleado";
    $id = $_GET['id'];
    $action_url = "rrhh_guardar_empleado.php?id=$id";
    
    try {
        // Usar la tabla 'hr_empleados'
        $stmt = $pdo->prepare("SELECT * FROM hr_empleados WHERE id = ?");
        $stmt->execute([$id]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$empleado) {
            die("Empleado no encontrado."); // O redirigir
        }
    } catch (PDOException $e) {
        die("Error al cargar empleado: " . $e->getMessage());
    }
}

// Cargar usuarios de 'global_users' para vincular
$stmt_users = $pdo->prepare("SELECT id, username FROM global_users");
$stmt_users->execute();
$usuarios_sistema = $stmt_users->fetchAll();

// Cargar compañías de 'inv_companies' para vincular
$stmt_comp = $pdo->prepare("SELECT id, name FROM inv_companies");
$stmt_comp->execute();
$companias = $stmt_comp->fetchAll();

$comunidades = [
    'Andalucía', 'Aragón', 'Asturias', 'Baleares', 'Canarias', 'Cantabria', 'Castilla-La Mancha',
    'Castilla y León', 'Cataluña', 'Ceuta', 'Comunidad Valenciana', 'Extremadura', 'Galicia',
    'La Rioja', 'Madrid', 'Melilla', 'Murcia', 'Navarra', 'País Vasco'
];
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $titulo; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item"><a href="rrhh_empleados.php">Empleados</a></li>
        <li class="breadcrumb-item active"><?php echo $titulo; ?></li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-edit me-1"></i>
            Datos del Empleado
        </div>
        <div class="card-body">
            <form action="<?php echo $action_url; ?>" method="POST" enctype="multipart/form-data">
                
                <ul class="nav nav-tabs" id="empleadoTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">Datos Personales</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="laboral-tab" data-bs-toggle="tab" data-bs-target="#laboral" type="button" role="tab" aria-controls="laboral" aria-selected="false">Datos Laborales</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sistema-tab" data-bs-toggle="tab" data-bs-target="#sistema" type="button" role="tab" aria-controls="sistema" aria-selected="false">Sistema</button>
                    </li>
                </ul>

                <div class="tab-content" id="empleadoTabContent">
                    
                    <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                        <div class="row mt-3">
                            <div class="col-md-4 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($empleado['nombre']); ?>" required>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="apellidos" class="form-label">Apellidos *</label>
                                <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($empleado['apellidos']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nif" class="form-label">NIF/NIE *</label>
                                <input type="text" class="form-control" id="nif" name="nif" value="<?php echo htmlspecialchars($empleado['nif']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ss" class="form-label">Nº Seguridad Social</label>
                                <input type="text" class="form-control" id="ss" name="ss" value="<?php echo htmlspecialchars($empleado['ss']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($empleado['fecha_nacimiento']); ?>">
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($empleado['direccion']); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($empleado['telefono']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email_personal" class="form-label">Email Personal</label>
                                <input type="email" class="form-control" id="email_personal" name="email_personal" value="<?php echo htmlspecialchars($empleado['email_personal']); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="estado_civil" class="form-label">Estado Civil</label>
                                <select class="form-select" id="estado_civil" name="estado_civil">
                                    <option value="" <?php echo $empleado['estado_civil']=='' ? 'selected' : ''; ?>>-- Seleccionar --</option>
                                    <option value="Soltero/a" <?php echo $empleado['estado_civil']=='Soltero/a' ? 'selected' : ''; ?>>Soltero/a</option>
                                    <option value="Casado/a" <?php echo $empleado['estado_civil']=='Casado/a' ? 'selected' : ''; ?>>Casado/a</option>
                                    <option value="Divorciado/a" <?php echo $empleado['estado_civil']=='Divorciado/a' ? 'selected' : ''; ?>>Divorciado/a</option>
                                    <option value="Viudo/a" <?php echo $empleado['estado_civil']=='Viudo/a' ? 'selected' : ''; ?>>Viudo/a</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="foto" class="form-label">Foto</label>
                                <input class="form-control" type="file" id="foto" name="foto">
                                <?php if (!empty($empleado['foto']) && $empleado['foto'] != 'default.png'): ?>
                                    <img src="uploads/fotos_empleados/<?php echo htmlspecialchars($empleado['foto']); ?>" alt="Foto actual" class="img-thumbnail mt-2" width="100">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="laboral" role="tabpanel" aria-labelledby="laboral-tab">
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label for="departamento" class="form-label">Departamento</label>
                                <input type="text" class="form-control" id="departamento" name="departamento" value="<?php echo htmlspecialchars($empleado['departamento']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="puesto" class="form-label">Puesto</label>
                                <input type="text" class="form-control" id="puesto" name="puesto" value="<?php echo htmlspecialchars($empleado['puesto']); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_contratacion" class="form-label">Fecha Contratación</label>
                                <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion" value="<?php echo htmlspecialchars($empleado['fecha_contratacion']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                                <select class="form-select" id="tipo_contrato" name="tipo_contrato">
                                    <option value="" <?php echo $empleado['tipo_contrato']=='' ? 'selected' : ''; ?>>-- Seleccionar --</option>
                                    <option value="Indefinido" <?php echo $empleado['tipo_contrato']=='Indefinido' ? 'selected' : ''; ?>>Indefinido</option>
                                    <option value="Temporal" <?php echo $empleado['tipo_contrato']=='Temporal' ? 'selected' : ''; ?>>Temporal</option>
                                    <option value="Formación" <?php echo $empleado['tipo_contrato']=='Formación' ? 'selected' : ''; ?>>Formación</option>
                                    <option value="Prácticas" <?php echo $empleado['tipo_contrato']=='Prácticas' ? 'selected' : ''; ?>>Prácticas</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="salario_bruto_anual" class="form-label">Salario Bruto Anual</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="salario_bruto_anual" name="salario_bruto_anual" value="<?php echo htmlspecialchars($empleado['salario_bruto_anual']); ?>">
                                    <span class="input-group-text">€</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="comunidad_autonoma" class="form-label">Comunidad Autónoma (IRPF)</label>
                                <select class="form-select" id="comunidad_autonoma" name="comunidad_autonoma">
                                    <option value="" <?php echo $empleado['comunidad_autonoma']=='' ? 'selected' : ''; ?>>-- Seleccionar --</option>
                                    <?php foreach ($comunidades as $ca): ?>
                                    <option value="<?php echo $ca; ?>" <?php echo $empleado['comunidad_autonoma']==$ca ? 'selected' : ''; ?>>
                                        <?php echo $ca; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="sistema" role="tabpanel" aria-labelledby="sistema-tab">
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label for="user_id" class="form-label">Usuario del Sistema (Login)</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">-- Sin acceso al sistema --</option>
                                    <?php foreach ($usuarios_sistema as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $empleado['user_id']==$u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['username']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Vincula este empleado a una cuenta de 'global_users' para que pueda iniciar sesión.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="company_id" class="form-label">Compañía (Inventario)</label>
                                <select class="form-select" id="company_id" name="company_id">
                                    <option value="">-- Seleccionar compañía --</option>
                                    <?php foreach ($companias as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $empleado['company_id']==$c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Vincula al empleado con una compañía del módulo de inventario.</div>
                            </div>
                        </div>
                    </div>

                </div> <hr>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="rrhh_empleados.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>

</div> <?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>