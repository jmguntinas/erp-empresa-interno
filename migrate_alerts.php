<?php
// migrate_alerts.php (Adaptado para inv_)
require_once __DIR__ . '/db.php';

// --- MODIFICADO: Usar el nombre de la DB de la conexión ---
$dbName = '';
try { $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn(); } catch (Throwable $e) { die("Error getting DB name: ".$e->getMessage()); }
if (!$dbName) { die("Could not determine database name."); }
// --- FIN MODIFICADO ---

function column_exists(PDO $pdo, $table, $column): bool {
  global $dbName; // Usar el nombre de la DB obtenido
  $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->execute([$dbName, $table, $column]);
  return (int)$q->fetchColumn() > 0;
}

function safe_query(PDO $pdo, $sql){
  try { $pdo->exec($sql); }
  catch (Throwable $e) { echo "[WARN] " . $e->getMessage() . PHP_EOL; }
}

echo "== Migración: inv_product_warehouse_alerts + campos inv_products ==\n";

/* 1) Crear tabla inv_product_warehouse_alerts si no existe */
// --- MODIFICADO: Crear tabla con prefijo y FKs correctas ---
$sql = "CREATE TABLE IF NOT EXISTS inv_product_warehouse_alerts (
  `product_id`  INT NOT NULL,
  `warehouse_id` INT NOT NULL,
  `last_sent`   DATETIME NULL,
  `resent_count` INT NOT NULL DEFAULT 0, -- renombrado de resend_count
  `escalated`    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, warehouse_id),
  CONSTRAINT pwa_product_fk FOREIGN KEY (product_id)
    REFERENCES inv_products(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT pwa_warehouse_fk FOREIGN KEY (warehouse_id)
    REFERENCES inv_warehouses(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
// --- FIN MODIFICADO ---
safe_query($pdo, $sql);
echo " - Tabla inv_product_warehouse_alerts: OK\n";

/* 2) Añadir columnas a inv_products si faltan */
// --- MODIFICADO: Añadir columnas a inv_products ---
$table_products = 'inv_products';
$adds = [
  // Columna 'recommended_stock' ya existe en el esquema base
  // ['recommended_stock', "ALTER TABLE $table_products ADD COLUMN recommended_stock INT NOT NULL DEFAULT 0 AFTER min_stock_level"],
  ['alert_enabled', "ALTER TABLE $table_products ADD COLUMN alert_enabled TINYINT(1) NOT NULL DEFAULT 0"], // Sin AFTER específico
  ['alert_cooldown_minutes', "ALTER TABLE $table_products ADD COLUMN alert_cooldown_minutes INT NOT NULL DEFAULT 60 AFTER alert_enabled"],
  ['alert_max_resends', "ALTER TABLE $table_products ADD COLUMN alert_max_resends INT NOT NULL DEFAULT 3 AFTER alert_cooldown_minutes"],
  ['alert_escalation_email', "ALTER TABLE $table_products ADD COLUMN alert_escalation_email VARCHAR(150) NULL AFTER alert_max_resends"],
  ['alert_last_sent', "ALTER TABLE $table_products ADD COLUMN alert_last_sent DATETIME NULL AFTER alert_escalation_email"],
  ['alert_resend_count', "ALTER TABLE $table_products ADD COLUMN alert_resend_count INT NOT NULL DEFAULT 0 AFTER alert_last_sent"],
  ['alert_escalated', "ALTER TABLE $table_products ADD COLUMN alert_escalated TINYINT(1) NOT NULL DEFAULT 0 AFTER alert_resend_count"],
];
// --- FIN MODIFICADO ---

foreach ($adds as [$col, $sql]) {
  // --- MODIFICADO: Usar $table_products ---
  if (!column_exists($pdo, $table_products, $col)) {
    echo " - Añadiendo columna $col a $table_products...\n";
    safe_query($pdo, $sql);
  } else {
    echo " - Columna $col en $table_products ya existe.\n";
  }
  // --- FIN MODIFICADO ---
}

echo "== Migración completada ==\n";
?>