<?php
/**
 * ensure_database.php
 * - Crea la BD si no existe
 * - Aplica migraciones idempotentes para alinear el esquema con el proyecto
 * Uso:
 * CLI:    php ensure_database.php
 * Web:    http://localhost/tu_proyecto/ensure_database.php
 */

/* ========= CONFIG ========= */
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
// --- MODIFICADO: Nombre de la base de datos ---
$DB_NAME = 'empresa_global_db';
// --- FIN MODIFICADO ---
$DB_CHARSET = 'utf8mb4';
$DB_COLLATE = 'utf8mb4_unicode_ci';
/* ========================= */

header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

function logok($m){ echo "✓ $m\n"; }
function logi($m){ echo "→ $m\n"; }
function logw($m){ echo "⚠ $m\n"; }
function loge($m){ echo "✗ $m\n"; }

function pdo_connect_no_db($host, $user, $pass){
  $dsn = "mysql:host=$host;charset=utf8mb4";
  $opt = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
  return new PDO($dsn,$user,$pass,$opt);
}
function pdo_connect($host, $db, $user, $pass, $charset){
  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  $opt = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
  return new PDO($dsn,$user,$pass,$opt);
}
function table_exists(PDO $pdo, $table){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
  $st->execute([$table]); return (int)$st->fetchColumn() > 0;
}
function column_exists(PDO $pdo, $table, $col){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->execute([$table,$col]); return (int)$st->fetchColumn() > 0;
}
function fk_exists(PDO $pdo, $table, $fk){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
  $st->execute([$table,$fk]); return (int)$st->fetchColumn() > 0;
}
function add_fk_if_missing(PDO $pdo, $t, $fk, $col, $refT, $refCol, $onDel, $onUpd){
  if (!fk_exists($pdo,$t,$fk)){
    try{ $pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `$fk` FOREIGN KEY ($col) REFERENCES `$refT`($refCol) ON DELETE $onDel ON UPDATE $onUpd"); logi("✓ Añadida FK $fk"); }
    catch(Throwable $e){ logw("WARN: no se pudo añadir FK $fk ({$e->getMessage()})"); }
  } else { logi("· FK $fk ya existe"); }
}
function add_col_if_missing(PDO $pdo, $t, $ddl){
    // Extraer nombre de columna del DDL
    if (preg_match('/^\s*`?([a-zA-Z0-9_]+)`?\s+/', $ddl, $matches)) {
        $colName = $matches[1];
        if (!column_exists($pdo, $t, $colName)) {
            try { $pdo->exec("ALTER TABLE `$t` ADD COLUMN $ddl"); logi("✓ Añadida columna $colName a $t"); }
            catch(Throwable $e){ logw("WARN: no se pudo añadir columna $colName ({$e->getMessage()})"); }
        } else { logi("· Columna $colName en $t ya existe"); }
    } else { logw("WARN: no se pudo extraer nombre de columna de DDL: $ddl"); }
}

try {
  logi("Conectando a MySQL...");
  $pdoSys = pdo_connect_no_db($DB_HOST, $DB_USER, $DB_PASS);
  logok("Conexión OK.");

  logi("Comprobando base de datos '$DB_NAME'...");
  $st=$pdoSys->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=?");
  $st->execute([$DB_NAME]);
  if ((int)$st->fetchColumn() === 0){
    logi("Base de datos '$DB_NAME' no existe, creando...");
    $pdoSys->exec("CREATE DATABASE `$DB_NAME` CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATE");
    logok("Base de datos '$DB_NAME' creada.");
  } else { logok("Base de datos '$DB_NAME' ya existe."); }
  unset($pdoSys); // Cerramos conexión sin DB

  logi("Conectando a '$DB_NAME'...");
  $pdo = pdo_connect($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET);
  logok("Conexión OK.");

  logi("Comprobando/creando tablas...");

  // --- MODIFICADO: Todas las tablas con prefijo inv_ ---
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_companies` ( id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, tax_id VARCHAR(32) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address VARCHAR(255) NULL, city VARCHAR(80) NULL, postal_code VARCHAR(20) NULL, country VARCHAR(80) NULL, logo_url VARCHAR(255) NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, is_active TINYINT(1) NOT NULL DEFAULT 1 ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_warehouses` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(190) NOT NULL, code VARCHAR(50) NULL, address VARCHAR(255) NULL, city VARCHAR(80) NULL, country VARCHAR(80) NULL, contact_name VARCHAR(190) NULL, phone VARCHAR(50) NULL, email VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_categories` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, parent_id INT NULL, name VARCHAR(190) NOT NULL, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_products` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, category_id INT NULL, sku VARCHAR(100) NULL, name VARCHAR(190) NOT NULL, description TEXT NULL, cost DECIMAL(12,4) NULL, pvp DECIMAL(12,4) NULL, vat_percent DECIMAL(5,2) NULL, image_url VARCHAR(255) NULL, min_stock INT NULL, recommended_stock INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_suppliers` ( id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, name VARCHAR(190) NOT NULL, tax_id VARCHAR(32) NULL, email VARCHAR(190) NULL, phone VARCHAR(50) NULL, address VARCHAR(255) NULL, contact_name VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_product_stock` ( product_id INT NOT NULL, warehouse_id INT NOT NULL, quantity INT NOT NULL DEFAULT 0, PRIMARY KEY (product_id, warehouse_id) ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_movements` ( id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, warehouse_id INT NOT NULL, user_id INT NULL, type ENUM('IN','OUT','ADJUST') NOT NULL, qty INT NOT NULL, reason VARCHAR(255) NULL, reference VARCHAR(100) NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_purchase_orders` ( id INT AUTO_INCREMENT PRIMARY KEY, supplier_id INT NULL, warehouse_id INT NULL, user_id INT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', order_date DATE NULL, expected_date DATE NULL, notes TEXT NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_purchase_order_lines` ( id INT AUTO_INCREMENT PRIMARY KEY, purchase_order_id INT NOT NULL, product_id INT NULL, reference VARCHAR(100) NULL, description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00, unit_cost DECIMAL(10,4) NULL, discount_percent DECIMAL(5,2) NULL DEFAULT 0.00, vat_percent DECIMAL(5,2) NULL DEFAULT 0.00 ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_delivery_notes` ( id INT AUTO_INCREMENT PRIMARY KEY, purchase_order_id INT NULL, supplier_id INT NULL, warehouse_id INT NULL, user_id INT NULL, status VARCHAR(30) NOT NULL DEFAULT 'received', delivery_date DATE NULL, delivery_ref VARCHAR(100) NULL, notes TEXT NULL, created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS `inv_delivery_note_lines` ( id INT AUTO_INCREMENT PRIMARY KEY, delivery_note_id INT NOT NULL, product_id INT NULL, reference VARCHAR(100) NULL, description VARCHAR(255) NOT NULL, quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00, cost DECIMAL(10,4) NULL ) ENGINE=InnoDB DEFAULT CHARSET=$DB_CHARSET COLLATE=$DB_COLLATE;");
  // --- FIN MODIFICADO ---

  logok("Tablas base OK.");

  logi("Comprobando/añadiendo columnas...");
  // --- MODIFICADO: Comprobar/añadir columnas en tablas inv_ ---
  add_col_if_missing($pdo,'inv_products','`sale_price` DECIMAL(12,4) NULL AFTER pvp');
  add_col_if_missing($pdo,'inv_products','`unit_type` VARCHAR(20) NULL AFTER name');
  add_col_if_missing($pdo,'inv_products','`ean13` VARCHAR(13) NULL AFTER sku');
  add_col_if_missing($pdo,'inv_companies','`is_active` TINYINT(1) NOT NULL DEFAULT 1');
  add_col_if_missing($pdo,'inv_delivery_notes','`company_id` INT NULL AFTER purchase_order_id');
  add_col_if_missing($pdo,'inv_delivery_notes','`client_id` INT NULL AFTER warehouse_id');
  add_col_if_missing($pdo,'inv_delivery_notes','`project_id` INT NULL AFTER client_id');
  // --- FIN MODIFICADO ---
  logok("Columnas OK.");


  logi("Comprobando/añadiendo FKs...");
  // --- MODIFICADO: Añadir FKs usando tablas inv_ ---
  if (table_exists($pdo,'inv_warehouses')){ add_fk_if_missing($pdo,'inv_warehouses','fk_wh_company','`company_id`','inv_companies','`id`','SET NULL','CASCADE'); }
  if (table_exists($pdo,'inv_categories')){
    add_fk_if_missing($pdo,'inv_categories','fk_cat_company','`company_id`','inv_companies','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_categories','fk_cat_parent','`parent_id`','inv_categories','`id`','SET NULL','CASCADE');
  }
  if (table_exists($pdo,'inv_products')){
    add_fk_if_missing($pdo,'inv_products','fk_prod_company','`company_id`','inv_companies','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_products','fk_prod_category','`category_id`','inv_categories','`id`','SET NULL','CASCADE');
  }
  if (table_exists($pdo,'inv_suppliers')){ add_fk_if_missing($pdo,'inv_suppliers','fk_sup_company','`company_id`','inv_companies','`id`','SET NULL','CASCADE'); }
  if (table_exists($pdo,'inv_product_stock')){
    add_fk_if_missing($pdo,'inv_product_stock','fk_ps_product','`product_id`','inv_products','`id`','CASCADE','CASCADE');
    add_fk_if_missing($pdo,'inv_product_stock','fk_ps_warehouse','`warehouse_id`','inv_warehouses','`id`','CASCADE','CASCADE');
  }
  if (table_exists($pdo,'inv_movements')){
    add_fk_if_missing($pdo,'inv_movements','fk_mov_product','`product_id`','inv_products','`id`','CASCADE','CASCADE');
    add_fk_if_missing($pdo,'inv_movements','fk_mov_warehouse','`warehouse_id`','inv_warehouses','`id`','CASCADE','CASCADE');
  }
  if (table_exists($pdo,'inv_purchase_orders')){
    add_fk_if_missing($pdo,'inv_purchase_orders','fk_po_supplier','`supplier_id`','inv_suppliers','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_purchase_orders','fk_po_warehouse','`warehouse_id`','inv_warehouses','`id`','SET NULL','CASCADE');
  }
  if (table_exists($pdo,'inv_purchase_order_lines')){
    add_fk_if_missing($pdo,'inv_purchase_order_lines','fk_poi_po','`purchase_order_id`','inv_purchase_orders','`id`','CASCADE','CASCADE');
    add_fk_if_missing($pdo,'inv_purchase_order_lines','fk_poi_product','`product_id`','inv_products','`id`','RESTRICT','CASCADE');
    if (column_exists($pdo,'inv_purchase_order_lines','warehouse_id')){ // Columna opcional
      add_fk_if_missing($pdo,'inv_purchase_order_lines','fk_poi_wh','`warehouse_id`','inv_warehouses','`id`','SET NULL','CASCADE');
    }
  }
  if (table_exists($pdo,'inv_delivery_notes')){
    add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_company','`company_id`','inv_companies','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_supplier','`supplier_id`','inv_suppliers','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_warehouse','`warehouse_id`','inv_warehouses','`id`','SET NULL','CASCADE');
    add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_po','`purchase_order_id`','inv_purchase_orders','`id`','SET NULL','CASCADE');
    if (table_exists($pdo, 'inv_clients')) {
        add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_client','`client_id`','inv_clients','`id`','SET NULL','CASCADE');
    }
    if (table_exists($pdo, 'inv_projects')) {
        add_fk_if_missing($pdo,'inv_delivery_notes','fk_dn_project','`project_id`','inv_projects','`id`','SET NULL','CASCADE');
    }
  }
  if (table_exists($pdo,'inv_delivery_note_lines')){
    add_fk_if_missing($pdo,'inv_delivery_note_lines','fk_dni_dn','`delivery_note_id`','inv_delivery_notes','`id`','CASCADE','CASCADE');
    add_fk_if_missing($pdo,'inv_delivery_note_lines','fk_dni_product','`product_id`','inv_products','`id`','RESTRICT','CASCADE');
  }
  // --- FIN MODIFICADO ---
  logok("FKs OK.");

  logi("Comprobando Índices...");
  // Aquí irían ADD INDEX IF NOT EXISTS si fueran necesarios
  logok("Índices OK.");

  logok("Proceso completado.");

} catch (PDOException $e) {
  loge("ERROR PDO: ".$e->getMessage());
} catch (Throwable $e) {
  loge("ERROR: ".$e->getMessage());
} finally {
  echo "</pre>";
}