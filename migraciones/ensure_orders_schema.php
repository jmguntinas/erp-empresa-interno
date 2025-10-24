<?php
// migraciones/ensure_orders_schema.php
// Garantiza: purchase_orders, purchase_order_items, delivery_notes.purchase_order_id, índices y FKs.
// Requiere: db.php con $pdo (PDO MySQL).

require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=UTF-8');

// --- MODIFICADO: Definir tablas ---
$t_po = 'inv_purchase_orders';
$t_po_items = 'inv_purchase_order_lines';
$t_dn = 'inv_delivery_notes';
$t_dn_items = 'inv_delivery_note_lines';
$t_suppliers = 'inv_suppliers';
$t_warehouses = 'inv_warehouses';
$t_products = 'inv_products';
// --- FIN MODIFICADO ---


function db_name(PDO $pdo){ return $pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_table(PDO $pdo, $t){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
  $st->execute([db_name($pdo), $t]); return (int)$st->fetchColumn()>0;
}
function has_col(PDO $pdo, $t, $c){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->execute([db_name($pdo), $t, $c]); return (int)$st->fetchColumn()>0;
}
function has_index(PDO $pdo, $t, $idx){
  $st=$pdo->prepare("SHOW INDEX FROM `$t` WHERE Key_name=?"); $st->execute([$idx]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}
function fk_exists(PDO $pdo, $t, $fk){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
  $st->execute([db_name($pdo), $t, $fk]); return (int)$st->fetchColumn()>0;
}
function add_col(PDO $pdo,$t,$ddl){ try{$pdo->exec("ALTER TABLE `$t` ADD $ddl");}catch(Throwable $e){ echo " (WARN: {$e->getMessage()})"; } }
function add_fk(PDO $pdo,$t,$fk,$ddl){ try{$pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `$fk` $ddl");}catch(Throwable $e){ echo " (WARN: {$e->getMessage()})"; } }
function add_index(PDO $pdo,$t,$idx,$cols){ try{$pdo->exec("ALTER TABLE `$t` ADD INDEX `$idx` ($cols)");}catch(Throwable $e){ echo " (WARN: {$e->getMessage()})"; } }

// --- Creación de tablas base (modificado con prefijos) ---
if (!has_table($pdo,$t_po)) {
  echo "- Creando tabla $t_po\n";
  $pdo->exec("CREATE TABLE `$t_po` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `supplier_id` INT NULL,
      `warehouse_id` INT NULL,
      `user_id` INT NULL,
      `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
      `order_date` DATE NULL,
      `expected_date` DATE NULL,
      `notes` TEXT NULL,
      `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
if (!has_table($pdo,$t_po_items)) {
  echo "- Creando tabla $t_po_items\n";
  $pdo->exec("CREATE TABLE `$t_po_items` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `purchase_order_id` INT NOT NULL,
      `product_id` INT NULL,
      `reference` VARCHAR(100) NULL,
      `description` VARCHAR(255) NOT NULL,
      `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
      `unit_cost` DECIMAL(10,4) NULL,
      `discount_percent` DECIMAL(5,2) NULL DEFAULT 0.00,
      `vat_percent` DECIMAL(5,2) NULL DEFAULT 0.00,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
if (!has_table($pdo,$t_dn)) {
  echo "- Creando tabla $t_dn\n";
  $pdo->exec("CREATE TABLE `$t_dn` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `purchase_order_id` INT NULL,
      `supplier_id` INT NULL,
      `warehouse_id` INT NULL,
      `user_id` INT NULL,
      `status` VARCHAR(30) NOT NULL DEFAULT 'received',
      `delivery_date` DATE NULL,
      `notes` TEXT NULL,
      `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
// delivery_note_lines no estaba en el script original pero es necesaria
if (!has_table($pdo,$t_dn_items)) {
    echo "- Creando tabla $t_dn_items\n";
    $pdo->exec("CREATE TABLE `$t_dn_items` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `delivery_note_id` INT NOT NULL,
        `product_id` INT NULL,
        `reference` VARCHAR(100) NULL,
        `description` VARCHAR(255) NOT NULL,
        `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  }


// --- Columnas y FKs (modificado con prefijos) ---
echo "Comprobando FKs y columnas en $t_po...\n";
if (!fk_exists($pdo,$t_po,'fk_po_supplier') && has_table($pdo,$t_suppliers) && has_col($pdo,$t_po,'supplier_id')) {
  echo "- FK fk_po_supplier\n";
  add_fk($pdo,$t_po,'fk_po_supplier', "FOREIGN KEY (supplier_id) REFERENCES $t_suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL");
}
if (!fk_exists($pdo,$t_po,'fk_po_warehouse') && has_table($pdo,$t_warehouses) && has_col($pdo,$t_po,'warehouse_id')) {
  echo "- FK fk_po_warehouse\n";
  add_fk($pdo,$t_po,'fk_po_warehouse', "FOREIGN KEY (warehouse_id) REFERENCES $t_warehouses(id) ON UPDATE CASCADE ON DELETE SET NULL");
}

echo "Comprobando FKs y columnas en $t_po_items...\n";
if (!fk_exists($pdo,$t_po_items,'fk_poi_po') && has_col($pdo,$t_po_items,'purchase_order_id')) {
  echo "- FK fk_poi_po\n";
  add_fk($pdo,$t_po_items,'fk_poi_po', "FOREIGN KEY (purchase_order_id) REFERENCES $t_po(id) ON UPDATE CASCADE ON DELETE CASCADE");
}
if (!fk_exists($pdo,$t_po_items,'fk_poi_product') && has_table($pdo,$t_products) && has_col($pdo,$t_po_items,'product_id')) {
  echo "- FK fk_poi_product\n";
  add_fk($pdo,$t_po_items,'fk_poi_product', "FOREIGN KEY (product_id) REFERENCES $t_products(id) ON UPDATE CASCADE ON DELETE SET NULL");
}

echo "Comprobando FKs y columnas en $t_dn...\n";
if (!fk_exists($pdo,$t_dn,'fk_dn_po') && has_col($pdo,$t_dn,'purchase_order_id')) {
  echo "- FK fk_dn_po\n";
  add_fk($pdo,$t_dn,'fk_dn_po', "FOREIGN KEY (purchase_order_id) REFERENCES $t_po(id) ON UPDATE CASCADE ON DELETE SET NULL");
}
if (!fk_exists($pdo,$t_dn,'fk_dn_supplier') && has_table($pdo,$t_suppliers) && has_col($pdo,$t_dn,'supplier_id')) {
  echo "- FK fk_dn_supplier\n";
  add_fk($pdo,$t_dn,'fk_dn_supplier', "FOREIGN KEY (supplier_id) REFERENCES $t_suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL");
}
if (!fk_exists($pdo,$t_dn,'fk_dn_warehouse') && has_table($pdo,$t_warehouses) && has_col($pdo,$t_dn,'warehouse_id')) {
  echo "- FK fk_dn_warehouse\n";
  add_fk($pdo,$t_dn,'fk_dn_warehouse', "FOREIGN KEY (warehouse_id) REFERENCES $t_warehouses(id) ON UPDATE CASCADE ON DELETE SET NULL");
}

echo "Comprobando FKs y columnas en $t_dn_items...\n";
if (!fk_exists($pdo,$t_dn_items,'fk_dni_dn') && has_col($pdo,$t_dn_items,'delivery_note_id')) {
  echo "- FK fk_dni_dn\n";
  add_fk($pdo,$t_dn_items,'fk_dni_dn', "FOREIGN KEY (delivery_note_id) REFERENCES $t_dn(id) ON UPDATE CASCADE ON DELETE CASCADE");
}
if (!fk_exists($pdo,$t_dn_items,'fk_dni_product') && has_table($pdo,$t_products) && has_col($pdo,$t_dn_items,'product_id')) {
  echo "- FK fk_dni_product\n";
  add_fk($pdo,$t_dn_items,'fk_dni_product', "FOREIGN KEY (product_id) REFERENCES $t_products(id) ON UPDATE CASCADE ON DELETE SET NULL");
}

echo "\nOK: Esquema de pedidos comprobado.\n";