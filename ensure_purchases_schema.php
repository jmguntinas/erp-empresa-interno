<?php
// ensure_purchases_schema.php (Adaptado para inv_)
// Crea/actualiza el esquema necesario para compras, etc.

declare(strict_types=1);

// 1) Conexión PDO (revisa la ruta si tu db.php está en otra carpeta)
require_once __DIR__ . '/db.php';

// 2) Helpers
function out($msg){ echo htmlspecialchars($msg)."<br>\n"; }
function exec_sql(PDO $pdo, string $sql, string $label=''): void {
  if ($label) { out("→ ".$label." …"); }
  try {
    $pdo->exec($sql);
    if ($label) { out("✓ OK: ".$label); }
  } catch (PDOException $e) {
      out("✗ ERROR en [$label]: " . $e->getMessage());
      // Considera lanzar la excepción o manejarla de otra forma
  }
}

// 3) Empezamos
out("Iniciando ensure_purchases_schema (adaptado para inv_)...");

// 4) Desactivar FKs temporalmente (MySQL/MariaDB)
try { $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); } catch (Throwable $e) { /* ignoramos si no aplica */ }

// 5) Tablas base (Asegurar tablas globales y crear las de compras con prefijo inv_)

// Tablas GLOBALES (ya deberían existir por ensure_database.php, pero por si acaso)
exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `global_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(150) UNIQUE,
  `is_active` BOOLEAN NOT NULL DEFAULT true,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "global_users");

// Tablas INVENTARIO (ya deberían existir por ensure_database.php)
exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
  -- ... resto de columnas
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_companies");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
  -- ... resto de columnas
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_suppliers");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_warehouses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
  -- ... resto de columnas
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_warehouses");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
  -- ... resto de columnas
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_products");

// Tablas de COMPRAS (con prefijo inv_)
exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NULL,
  `warehouse_id` INT NULL,
  `created_by_user_id` INT NULL, -- FK a global_users
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `order_date` DATE NULL,
  `expected_date` DATE NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `inv_suppliers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`created_by_user_id`) REFERENCES `global_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_purchase_orders");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_purchase_order_lines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL, -- Renombrado de purchase_order_id
  `product_id` INT NULL,
  `reference` VARCHAR(100) NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,4) NULL, -- Renombrado de unit_cost
  `discount_percent` DECIMAL(5,2) NULL DEFAULT 0.00,
  `vat_percent` DECIMAL(5,2) NULL DEFAULT 0.00,
  FOREIGN KEY (`order_id`) REFERENCES `inv_purchase_orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_purchase_order_lines");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_delivery_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT NULL,
  `supplier_id` INT NULL, -- Mantenido por si viene de proveedor
  `client_id` INT NULL,   -- Añadido para salidas a cliente
  `warehouse_id` INT NULL,
  `created_by_user_id` INT NULL, -- FK a global_users
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft', -- 'draft', 'sent', 'delivered'
  `delivery_date` DATE NULL,
  `delivery_ref` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`purchase_order_id`) REFERENCES `inv_purchase_orders`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `inv_suppliers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  -- FOREIGN KEY (`client_id`) REFERENCES `inv_clients`(`id`) ON DELETE SET NULL ON UPDATE CASCADE, -- Asegúrate que inv_clients existe
  FOREIGN KEY (`warehouse_id`) REFERENCES `inv_warehouses`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`created_by_user_id`) REFERENCES `global_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_delivery_notes");

exec_sql($pdo, "
CREATE TABLE IF NOT EXISTS `inv_delivery_note_lines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `note_id` INT NOT NULL, -- Renombrado de delivery_note_id
  `product_id` INT NULL,
  `reference` VARCHAR(100) NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,4) NULL, -- Renombrado de cost/price
  FOREIGN KEY (`note_id`) REFERENCES `inv_delivery_notes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `inv_products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", "inv_delivery_note_lines");


// 6) Reactivar FKs
try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $e) { /* ignoramos si no aplica */ }

// 7) Admin de prueba opcional (ya gestionado por crear_admin.php)
out("✓ Esquema de compras asegurado.");