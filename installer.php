<?php
// installer.php (Adaptado para estructura global)
// *** ADVERTENCIA ***: Es MUCHO MÁS SEGURO usar el archivo SQL `empresa_global_db.sql`
// y luego `crear_admin.php`. Usa este instalador bajo tu propio riesgo y SOLO si
// estás seguro de que la base de datos está vacía o no te importa perder datos.

declare(strict_types=1);

// Helper CSRF simple (sin dependencia de auth.php completo)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
function csrf_token() { return $_SESSION['csrf_token']; }
function check_csrf() { $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf']); if (!$ok) die('CSRF inválido'); }

$done=false; $error=''; $success=''; $createdConfig = false;

// Intentar cargar config.php si existe (para pre-rellenar)
$defaults = ['host'=>'localhost', 'db'=>'empresa_global_db', 'user'=>'root', 'pass'=>''];
if (file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
    $defaults['host'] = defined('DB_HOST') ? DB_HOST : $defaults['host'];
    $defaults['db']   = defined('DB_NAME') ? DB_NAME : $defaults['db'];
    $defaults['user'] = defined('DB_USER') ? DB_USER : $defaults['user'];
    $defaults['pass'] = defined('DB_PASS') ? DB_PASS : $defaults['pass'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $host = trim($_POST['host'] ?? $defaults['host']);
  $db   = trim($_POST['db'] ?? $defaults['db']);
  $user = trim($_POST['user'] ?? $defaults['user']);
  $pass = $_POST['pass'] ?? $defaults['pass'];
  $admin_user = trim($_POST['admin_user'] ?? 'admin');
  $admin_email = trim($_POST['admin_email'] ?? 'admin@local');
  $admin_pass = $_POST['admin_pass'] ?? '';

  if (!$host || !$db || !$user || !$admin_user || !$admin_email || !$admin_pass) {
    $error='Completa todos los campos obligatorios.';
  } else {
    try {
      // 1. Crear config.php (si no existe)
      $configPath = __DIR__ . '/config.php';
      if (!file_exists($configPath)) {
          $configContent = "<?php\ndefine('DB_HOST','$host');\ndefine('DB_NAME','$db');\ndefine('DB_USER','$user');\ndefine('DB_PASS','$pass');\n?>";
          if (file_put_contents($configPath, $configContent) === false) {
              throw new Exception("No se pudo escribir config.php. Verifica los permisos.");
          }
          $createdConfig = true;
          $success .= "Archivo config.php creado.\n";
      }

      // 2. Conectar SIN base de datos para crearla
      $pdoSys = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $pdoSys->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      unset($pdoSys); // Cerrar conexión sys
      $success .= "Base de datos '$db' asegurada/creada.\n";

      // 3. Conectar CON base de datos
      $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

      // 4. Crear TODAS las tablas (¡idempotente!)
      // Copiamos la estructura completa de `empresa_global_db.sql`
      // Tablas Globales
      $pdo->exec("CREATE TABLE IF NOT EXISTS `global_roles` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `role_name` VARCHAR(100) NOT NULL UNIQUE, `description` TEXT ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `global_users` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `username` VARCHAR(100) NOT NULL UNIQUE, `password_hash` VARCHAR(255) NOT NULL, `email` VARCHAR(150) UNIQUE, `is_active` BOOLEAN NOT NULL DEFAULT true, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `global_user_roles` ( `user_id` INT NOT NULL, `role_id` INT NOT NULL, PRIMARY KEY (`user_id`, `role_id`), FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`) ON DELETE CASCADE, FOREIGN KEY (`role_id`) REFERENCES `global_roles`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      // Tablas Inventario (inv_)
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_companies` ( id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, tax_id VARCHAR(32) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address VARCHAR(255) NULL, city VARCHAR(80) NULL, postal_code VARCHAR(20) NULL, country VARCHAR(80) NULL, logo_url VARCHAR(255) NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, is_active TINYINT(1) NOT NULL DEFAULT 1, company_type ENUM('propia','cliente','proveedor') NOT NULL DEFAULT 'propia' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Añadido is_active y company_type
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_warehouses` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(190) NOT NULL, code VARCHAR(50) NULL, address VARCHAR(255) NULL, city VARCHAR(80) NULL, country VARCHAR(80) NULL, contact_name VARCHAR(190) NULL, phone VARCHAR(50) NULL, email VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_categories` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, parent_id INT NULL, name VARCHAR(190) NOT NULL, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`parent_id`) REFERENCES `inv_categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_products` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, category_id INT NULL, sku VARCHAR(100) UNIQUE, name VARCHAR(190) NOT NULL, description TEXT NULL, purchase_price DECIMAL(12,4) NULL, sale_price DECIMAL(12,4) NULL, vat_percent DECIMAL(5,2) NULL, image_url VARCHAR(255) NULL, min_stock_level INT NULL, recommended_stock INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, unit_type VARCHAR(20) NULL, ean13 VARCHAR(13) NULL, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`category_id`) REFERENCES `inv_categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Añadidos campos y FKs
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_suppliers` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(190) NOT NULL, tax_id VARCHAR(32) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address VARCHAR(255) NULL, contact_name VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_clients` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(190) NOT NULL, internal_ref VARCHAR(50) NULL, tax_id VARCHAR(32) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address VARCHAR(255) NULL, contact_name VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Añadido company_id y FK
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_product_stock` ( product_id INT NOT NULL, warehouse_id INT NOT NULL, quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00, last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (product_id, warehouse_id), FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE CASCADE, FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Quantity DECIMAL
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_movements` ( id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, warehouse_id INT NOT NULL, user_id INT NULL, type ENUM('entrada','salida','ajuste') NOT NULL, quantity DECIMAL(10,2) NOT NULL, reason VARCHAR(255) NULL, reference_id INT NULL, movement_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE CASCADE, FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE CASCADE, FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`) ON DELETE SET NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Quantity DECIMAL, FK user_id
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_purchase_orders` ( id INT AUTO_INCREMENT PRIMARY KEY, supplier_id INT NULL, warehouse_id INT NULL, created_by_user_id INT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', order_date DATE NULL, expected_date DATE NULL, notes TEXT NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (`supplier_id`) REFERENCES `inv_suppliers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`created_by_user_id`) REFERENCES `global_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_purchase_order_lines` ( id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NULL, reference VARCHAR(100) NULL, description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00, unit_price DECIMAL(10,4) NULL, discount_percent DECIMAL(5,2) NULL DEFAULT 0.00, vat_percent DECIMAL(5,2) NULL DEFAULT 0.00, FOREIGN KEY (`order_id`) REFERENCES `inv_purchase_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_delivery_notes` ( id INT AUTO_INCREMENT PRIMARY KEY, purchase_order_id INT NULL, supplier_id INT NULL, client_id INT NULL, warehouse_id INT NULL, created_by_user_id INT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', delivery_date DATE NULL, delivery_ref VARCHAR(100) NULL, notes TEXT NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (`purchase_order_id`) REFERENCES `inv_purchase_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`supplier_id`) REFERENCES `inv_suppliers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`client_id`) REFERENCES `inv_clients`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, FOREIGN KEY (`created_by_user_id`) REFERENCES `global_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // Añadido client_id y FK
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_delivery_note_lines` ( id INT AUTO_INCREMENT PRIMARY KEY, note_id INT NOT NULL, product_id INT NULL, reference VARCHAR(100) NULL, description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00, unit_price DECIMAL(10,4) NULL, FOREIGN KEY (`note_id`) REFERENCES `inv_delivery_notes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_product_suppliers` ( id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, supplier_id INT NOT NULL, unit_cost DECIMAL(10, 4) DEFAULT NULL, discount DECIMAL(5, 2) DEFAULT 0.00, is_primary BOOLEAN DEFAULT FALSE, supplier_ref VARCHAR(100) DEFAULT NULL, UNIQUE KEY `product_supplier` (`product_id`, `supplier_id`), FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE CASCADE, FOREIGN KEY (`supplier_id`) REFERENCES `inv_suppliers`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_projects` ( id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, internal_ref VARCHAR(100) DEFAULT NULL, company_id INT DEFAULT NULL, client_id INT DEFAULT NULL, status VARCHAR(50) DEFAULT 'active', created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL, FOREIGN KEY (`client_id`) REFERENCES `inv_clients`(`id`) ON DELETE SET NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_product_warehouse_alerts` ( product_id INT NOT NULL, warehouse_id INT NOT NULL, last_sent DATETIME DEFAULT NULL, resent_count INT DEFAULT 0, escalated TINYINT(1) DEFAULT 0, PRIMARY KEY (`product_id`, `warehouse_id`), FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE CASCADE, FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_departments` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(120) NOT NULL, code VARCHAR(50) DEFAULT NULL, FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      // Tablas RRHH (hr_)
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_empleados` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNIQUE, `company_id` INT, `nombre` VARCHAR(100) NOT NULL, `apellidos` VARCHAR(150) NOT NULL, `foto` VARCHAR(255) DEFAULT 'default.png', `nif` VARCHAR(20) NOT NULL UNIQUE, `ss` VARCHAR(20) UNIQUE, `fecha_nacimiento` DATE, `direccion` TEXT, `telefono` VARCHAR(20), `email_personal` VARCHAR(100), `departamento` VARCHAR(100), `puesto` VARCHAR(100), `fecha_contratacion` DATE, `salario_bruto_anual` DECIMAL(10, 2), `comunidad_autonoma` VARCHAR(100), `estado_civil` VARCHAR(50), `tipo_contrato` VARCHAR(50), FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`), FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_vacaciones` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `empleado_id` INT NOT NULL, `fecha_inicio` DATE NOT NULL, `fecha_fin` DATE NOT NULL, `dias_solicitados` INT NOT NULL, `estado` ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente', `comentarios` TEXT, `fecha_solicitud` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `gestionado_por_id` INT, FOREIGN KEY (`empleado_id`) REFERENCES `hr_empleados`(`id`), FOREIGN KEY (`gestionado_por_id`) REFERENCES `global_users`(`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_festivos` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `fecha` DATE NOT NULL UNIQUE, `descripcion` VARCHAR(255) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_tramos_irpf` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `limite_inferior` DECIMAL(10, 2) NOT NULL, `limite_superior` DECIMAL(10, 2), `porcentaje` DECIMAL(5, 2) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_tramos_irpf_autonomico` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `comunidad` VARCHAR(100) NOT NULL, `limite_inferior` DECIMAL(10, 2) NOT NULL, `limite_superior` DECIMAL(10, 2), `porcentaje` DECIMAL(5, 2) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      // Añadir tablas faltantes hr_opciones_calendario, hr_dashboard_kpis
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_opciones_calendario` ( id INT AUTO_INCREMENT PRIMARY KEY, opcion_nombre VARCHAR(100) NOT NULL UNIQUE, opcion_valor VARCHAR(255) NOT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
      $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_dashboard_kpis` ( id INT AUTO_INCREMENT PRIMARY KEY, kpi_name VARCHAR(100) NOT NULL UNIQUE, kpi_value VARCHAR(255) NOT NULL, last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

      $success .= "Estructura de tablas asegurada/creada.\n";

      // 5. Crear usuario admin y asignarle rol
      $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
      // Insertar o actualizar admin en global_users
      $stmt_user = $pdo->prepare("INSERT INTO global_users (username, email, password_hash, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE email=VALUES(email), password_hash=VALUES(password_hash), is_active=1");
      $stmt_user->execute([$admin_user, $admin_email, $hash]);
      // Obtener ID de admin
      $stmt_id = $pdo->prepare("SELECT id FROM global_users WHERE username=?");
      $stmt_id->execute([$admin_user]);
      $admin_id = $stmt_id->fetchColumn();
      // Insertar rol 'Admin General' si no existe
      $pdo->exec("INSERT IGNORE INTO global_roles (role_name, description) VALUES ('Admin General', 'Acceso total')");
      // Obtener ID del rol
      $stmt_role = $pdo->prepare("SELECT id FROM global_roles WHERE role_name='Admin General'");
      $stmt_role->execute();
      $role_id = $stmt_role->fetchColumn();
      // Asignar rol a admin si tenemos ambos IDs
      if ($admin_id && $role_id) {
          $stmt_assign = $pdo->prepare("INSERT IGNORE INTO global_user_roles (user_id, role_id) VALUES (?, ?)");
          $stmt_assign->execute([$admin_id, $role_id]);
          $success .= "Usuario admin '$admin_user' creado/actualizado y rol 'Admin General' asignado.\n";
      } else {
          $error .= "No se pudo asignar el rol 'Admin General' al usuario admin.\n";
      }

      // 6. Finalizar
      $done=true;

    } catch(PDOException $e){
      $error = "Error PDO: " . $e->getMessage();
    } catch(Throwable $e){
      $error = "Error general: " . $e->getMessage();
    }
  }
}

$pageTitle = 'Instalador';
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light p-4">
<div class="container bg-white p-4 rounded shadow-sm" style="max-width: 720px;">
<h4>Instalador ERP Global</h4>

<?php if($done): ?>
<div class="alert alert-success">
  <strong>¡Instalación completada!</strong><br>
  <?= nl2br(htmlspecialchars($success)) ?>
</div>
<div class="alert alert-danger"><strong>¡IMPORTANTE!</strong> Elimina este archivo (<code>installer.php</code>) ahora.</div>
<p><a class="btn btn-primary" href="login.php">Ir al Login</a></p>

<?php else: ?>
<p class="text-danger"><strong>ADVERTENCIA:</strong> Este instalador creará la base de datos y tablas si no existen. Si ya existen, intentará añadir las faltantes pero <strong>podría fallar o causar problemas si el esquema es muy diferente</strong>. Úsalo con precaución, preferiblemente sobre una base de datos vacía. Es más seguro usar el archivo SQL manualmente.</p>

<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($success && !$error): ?><div class="alert alert-info"><?= nl2br(htmlspecialchars($success)) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <h5>1. Conexión Base de Datos</h5>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Host BD *</label><input class="form-control" name="host" value="<?= htmlspecialchars($defaults['host']) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Nombre BD *</label><input class="form-control" name="db" value="<?= htmlspecialchars($defaults['db']) ?>" required></div>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Usuario BD *</label><input class="form-control" name="user" value="<?= htmlspecialchars($defaults['user']) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Contraseña BD</label><input type="password" class="form-control" name="pass" value="<?= htmlspecialchars($defaults['pass']) ?>"></div>
  </div>
  <hr>
  <h5>2. Usuario Administrador Global</h5>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Username Admin *</label><input class="form-control" name="admin_user" value="admin" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email Admin *</label><input type="email" class="form-control" name="admin_email" value="admin@local" required></div>
  </div>
  <div class="mb-3">
      <label class="form-label">Contraseña Admin *</label>
      <input type="password" class="form-control" name="admin_pass" required>
      <div class="form-text">Se creará el usuario con el rol 'Admin General'.</div>
  </div>
  <button type="submit" class="btn btn-primary">Instalar / Comprobar Esquema</button>
  <?php if ($createdConfig): ?>
      <span class="ms-2 text-success">config.php ha sido creado.</span>
  <?php elseif (file_exists(__DIR__ . '/config.php')): ?>
       <span class="ms-2 text-muted">config.php ya existe, no se modificará.</span>
  <?php endif; ?>
</form>
<?php endif; ?>

</div> </body></html>