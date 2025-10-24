<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

$error = '';
$success = '';

// --- Lógica de Festivos ---

// POST: Añadir o Eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Añadir
    if (isset($_POST['fecha']) && isset($_POST['descripcion'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO hr_festivos (fecha, descripcion) VALUES (?, ?)");
            $stmt->execute([$_POST['fecha'], $_POST['descripcion']]);
            $success = "Festivo añadido correctamente.";
        } catch (PDOException $e) {
            $error = "Error al añadir festivo (quizás la fecha ya existe): " . $e->getMessage();
        }
    }
}

// GET: Eliminar
if (isset($_GET['eliminar'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM hr_festivos WHERE id = ?");
        $stmt->execute([$_GET['eliminar']]);
        $success = "Festivo eliminado correctamente.";
    } catch (PDOException $e) {
        $error = "Error al eliminar festivo: " . $e->getMessage();
    }
}

// Cargar listado de festivos
try {
    $stmt_list = $pdo->prepare("SELECT * FROM hr_festivos ORDER BY fecha ASC");
    $stmt_list->execute();
    $festivos = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar festivos: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestionar Días Festivos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item"><a href="rrhh_vacaciones.php">Calendario</a></li>
        <li class="breadcrumb-item active">Gestionar Festivos</li>
    </ol>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus me-1"></i>
            Añadir Nuevo Festivo
        </div>
        <div class="card-body">
            <form action="rrhh_gestionar_festivos.php" method="POST" class="row g-3">
                <div class="col-md-4">
                    <label for="fecha" class="form-label">Fecha *</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" required>
                </div>
                <div class="col-md-6">
                    <label for="descripcion" class="form-label">Descripción *</label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="Ej: Navidad" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Añadir</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-times me-1"></i>
            Listado de Festivos
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($festivos)): ?>
                        <tr><td colspan="3" class="text-center">No hay festivos definidos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($festivos as $festivo): ?>
                        <tr>
                            <td><?php echo date("d/m/Y", strtotime($festivo['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($festivo['descripcion']); ?></td>
                            <td>
                                <a href="rrhh_gestionar_festivos.php?eliminar=<?php echo $festivo['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?');" title="Eliminar"><i class="fas fa-trash"></i></a>
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