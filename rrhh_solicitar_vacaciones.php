<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

$user_id = $_SESSION['user_id'];
$empleado_id = null;
$error = '';
$success = '';

// Buscar el ID de empleado vinculado al ID de usuario
try {
    $stmt_emp = $pdo->prepare("SELECT id FROM hr_empleados WHERE user_id = ?");
    $stmt_emp->execute([$user_id]);
    $empleado_id = $stmt_emp->fetchColumn();
} catch (PDOException $e) {
    $error = "Error al identificar al empleado: " . $e->getMessage();
}

if (!$empleado_id) {
    $error = "Tu cuenta de usuario no está vinculada a ningún perfil de empleado. Contacta con RRHH.";
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $empleado_id) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $comentarios = $_POST['comentarios'];
    
    // --- Cálculo simple de días (mejora: excluir festivos/fines de semana) ---
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $dias = $fin->diff($inicio)->days + 1;
    // --- Fin cálculo simple ---

    if ($dias <= 0) {
        $error = "La fecha de fin debe ser igual o posterior a la fecha de inicio.";
    } else {
        try {
            $sql = "INSERT INTO hr_vacaciones (empleado_id, fecha_inicio, fecha_fin, dias_solicitados, comentarios, estado)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$empleado_id, $fecha_inicio, $fecha_fin, $dias, $comentarios]);
            $success = "¡Solicitud enviada correctamente! Serás redirigido al calendario...";
            header("Refresh: 3; url=rrhh_vacaciones.php");
        } catch (PDOException $e) {
            $error = "Error al enviar la solicitud: " . $e->getMessage();
        }
    }
}

?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Solicitar Vacaciones</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item"><a href="rrhh_vacaciones.php">Calendario</a></li>
        <li class="breadcrumb-item active">Solicitar Vacaciones</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-plus me-1"></i>
            Nueva Solicitud
        </div>
        <div class="card-body">
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($empleado_id && !$success): ?>
                <form action="rrhh_solicitar_vacaciones.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_fin" class="form-label">Fecha de Fin (incluida) *</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="comentarios" class="form-label">Comentarios (Opcional)</label>
                        <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
                    <a href="rrhh_vacaciones.php" class="btn btn-secondary">Cancelar</a>
                </form>
            <?php elseif(!$success): ?>
                <p class="text-danger">No puedes solicitar vacaciones hasta que tu cuenta esté vinculada a un empleado.</p>
            <?php endif; ?>
        </div>
    </div>
</div> <?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>