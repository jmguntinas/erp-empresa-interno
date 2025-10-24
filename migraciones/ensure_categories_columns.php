<?php
// migraciones/ensure_categories_columns.php
require_once __DIR__ . '/../db.php';

// --- MODIFICADO: Definir tabla ---
$table = 'inv_categories';
// --- FIN MODIFICADO ---

function db_name(PDO $pdo){ return $pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_col(PDO $pdo,$table,$col){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->execute([db_name($pdo),$table,$col]);
  return (int)$st->fetchColumn()>0;
}
function has_fk(PDO $pdo,$table,$fk){
  $st=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
  $st->execute([db_name($pdo),$table,$fk]);
  return (int)$st->fetchColumn()>0;
}
function add_col(PDO $pdo,$t,$ddl){ $pdo->exec("ALTER TABLE `$t` ADD $ddl"); }
function add_fk(PDO $pdo,$t,$fk,$ddl){ $pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `$fk` $ddl"); }

try{
  $pdo->beginTransaction();

  if (!has_col($pdo,$table,'name'))        add_col($pdo,$table,"`name` VARCHAR(190) NOT NULL");
  if (!has_col($pdo,$table,'parent_id'))   add_col($pdo,$table,"`parent_id` INT NULL");
  if (!has_col($pdo,$table,'description')) add_col($pdo,$table,"`description` VARCHAR(255) NULL");
  if (!has_col($pdo,$table,'is_active'))   add_col($pdo,$table,"`is_active` TINYINT(1) NOT NULL DEFAULT 1");
  if (!has_col($pdo,$table,'company_id'))  add_col($pdo,$table,"`company_id` INT NULL");
  if (!has_col($pdo,$table,'created_at'))  add_col($pdo,$table,"`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");

  // FKs: self y companies
  // --- MODIFICADO: Apuntar a sí misma 'inv_categories' ---
  if (has_col($pdo,$table,'parent_id') && !has_fk($pdo,$table,'fk_categories_parent')) {
    add_fk($pdo,$table,'fk_categories_parent',
      "FOREIGN KEY (`parent_id`) REFERENCES `inv_categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
  }
  // --- MODIFICADO: Apuntar a 'inv_companies' ---
  if (has_col($pdo,$table,'company_id') && !has_fk($pdo,$table,'fk_categories_company')) {
    add_fk($pdo,$table,'fk_categories_company',
      "FOREIGN KEY (`company_id`) REFERENCES `inv_companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
  }
  // --- FIN MODIFICADO ---

  $pdo->commit();
  echo "OK: columnas comprobadas/creadas en '$table'.\n";

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo "ERROR: " . $e->getMessage();
}