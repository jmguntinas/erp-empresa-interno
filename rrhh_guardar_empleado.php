<?php
// Lógica pura: Usamos auth y db del ERP, sin HTML
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';

// Validar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso no permitido.");
}

// --- Gestión de la subida de fotos ---
$foto_nombre = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
    $upload_dir = __DIR__ . '/uploads/fotos_empleados/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generar un nombre único
    $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $foto_nombre = uniqid('foto_') . '_' . time() . '.' . $extension;
    $upload_file = $upload_dir . $foto_nombre;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_file)) {
        // Manejar error de subida
        $foto_nombre = null; 
    }
}
// --- Fin gestión de fotos ---

// Recoger datos del formulario
$id = $_GET['id'] ?? null;
$nombre = $_POST['nombre'];
$apellidos = $_POST['apellidos'];
$nif = $_POST['nif'];
$ss = $_POST['ss'] ?: null;
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
$direccion = $_POST['direccion'] ?: null;
$telefono = $_POST['telefono'] ?: null;
$email_personal = $_POST['email_personal'] ?: null;
$departamento = $_POST['departamento'] ?: null;
$puesto = $_POST['puesto'] ?: null;
$fecha_contratacion = $_POST['fecha_contratacion'] ?: null;
$salario_bruto_anual = $_POST['salario_bruto_anual'] ?: null;
$comunidad_autonoma = $_POST['comunidad_autonoma'] ?: null;
$estado_civil = $_POST['estado_civil'] ?: null;
$tipo_contrato = $_POST['tipo_contrato'] ?: null;
$user_id = $_POST['user_id'] ?: null;
$company_id = $_POST['company_id'] ?: null;

try {
    if ($id) {
        // --- ACTUALIZAR (UPDATE) ---
        $params = [
            $nombre, $apellidos, $nif, $ss, $fecha_nacimiento, $direccion, $telefono, $email_personal,
            $departamento, $puesto, $fecha_contratacion, $salario_bruto_anual, $comunidad_autonoma,
            $estado_civil, $tipo_contrato, $user_id, $company_id
        ];

        // SQL base (usando tabla 'hr_empleados')
        $sql = "UPDATE hr_empleados SET 
                    nombre = ?, apellidos = ?, nif = ?, ss = ?, fecha_nacimiento = ?, 
                    direccion = ?, telefono = ?, email_personal = ?, departamento = ?, 
                    puesto = ?, fecha_contratacion = ?, salario_bruto_anual = ?, 
                    comunidad_autonoma = ?, estado_civil = ?, tipo_contrato = ?,
                    user_id = ?, company_id = ? 
                ";

        // Añadir la foto solo si se subió una nueva
        if ($foto_nombre) {
            $sql .= ", foto = ? ";
            $params[] = $foto_nombre;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

    } else {
        // --- INSERTAR (CREATE) ---
        
        // Si no se subió foto, usar 'default.png'
        if (!$foto_nombre) {
            $foto_nombre = 'default.png';
        }

        $sql = "INSERT INTO hr_empleados 
                    (nombre, apellidos, nif, ss, fecha_nacimiento, direccion, telefono, email_personal, 
                     departamento, puesto, fecha_contratacion, salario_bruto_anual, 
                     comunidad_autonoma, estado_civil, tipo_contrato, user_id, company_id, foto) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre, $apellidos, $nif, $ss, $fecha_nacimiento, $direccion, $telefono, $email_personal,
            $departamento, $puesto, $fecha_contratacion, $salario_bruto_anual, $comunidad_autonoma,
            $estado_civil, $tipo_contrato, $user_id, $company_id, $foto_nombre
        ]);
    }

    // Redirigir de vuelta al listado
    header("Location: rrhh_empleados.php");
    exit;

} catch (PDOException $e) {
    // Manejo de errores (ej. NIF duplicado)
    die("Error al guardar el empleado: " . $e->getMessage());
}
?>