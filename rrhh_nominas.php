<?php
// Usamos el sistema de autenticación y DB del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';   
require_once __DIR__ . '/partials/header.php'; // Header del ERP

// --- Lógica de Nóminas ---
try {
    // Cargar empleados para el selector
    // Usar 'hr_empleados'
    $stmt_emp = $pdo->prepare("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo, salario_bruto_anual, comunidad_autonoma 
                               FROM hr_empleados 
                               ORDER BY apellidos, nombre");
    $stmt_emp->execute();
    $empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar empleados: " . $e->getMessage();
}
// --- Fin Lógica ---
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Cálculo de Nómina (Estimación IRPF)</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard (ERP)</a></li>
        <li class="breadcrumb-item active">RRHH - Cálculo Nóminas</li>
    </ol>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calculator me-1"></i>
            Calculadora de Salario Neto
        </div>
        <div class="card-body">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="selector_empleado" class="form-label">Seleccionar Empleado</label>
                        <select id="selector_empleado" class="form-select">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                        data-salario="<?php echo $emp['salario_bruto_anual']; ?>" 
                                        data-ca="<?php echo htmlspecialchars($emp['comunidad_autonoma']); ?>">
                                    <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="salario_bruto" class="form-label">Salario Bruto Anual *</label>
                        <input type="number" id="salario_bruto" class="form-control" placeholder="Introduce el salario o selecciona un empleado">
                    </div>
                    <div class="mb-3">
                        <label for="comunidad_autonoma" class="form-label">Comunidad Autónoma *</label>
                        <input type="text" id="comunidad_autonoma" class="form-control" placeholder="Introduce la C.A. o selecciona un empleado">
                        <div class="form-text">El nombre debe coincidir exactamente con el de la BBDD (Ej: 'Madrid')</div>
                    </div>
                    <button id="btn_calcular_nomina" class="btn btn-primary">Calcular</button>
                </div>

                <div class="col-md-6">
                    <h4>Resultado de la Estimación</h4>
                    <div id="resultado_nomina">
                        <table class="table">
                            <tbody>
                                <tr><th>Salario Bruto Anual:</th><td id="res_bruto_anual">0.00 €</td></tr>
                                <tr><th>Retención IRPF Estatal:</th><td id="res_irpf_estatal">0.00 €</td></tr>
                                <tr><th>Retención IRPF Autonómico:</th><td id="res_irpf_auto">0.00 €</td></tr>
                                <tr class="table-secondary"><th>Total Retención IRPF:</th><td id="res_irpf_total">0.00 € (0.00 %)</td></tr>
                                <tr class="table-info"><th>Salario Neto Anual:</th><td id="res_neto_anual">0.00 €</td></tr>
                                <tr class="table-success"><th>Salario Neto Mensual (12 pagas):</th><td id="res_neto_mensual">0.00 €</td></tr>
                            </tbody>
                        </table>
                        <div class="alert alert-warning small">
                            <strong>Atención:</strong> Este es un cálculo simplificado basado en tramos y no incluye deducciones, SS, ni otras variables. Usar solo como estimación.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div> <script src="js/rrhh_calculo_nomina.js"></script> 

<?php
// Usamos el footer del ERP
require_once __DIR__ . '/partials/footer.php'; 
?>