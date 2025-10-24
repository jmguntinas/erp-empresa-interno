<?php
// migraciones/ensure_suppliers_columns.php
require_once __DIR__ . '/../db.php';

/** Helpers esquema **/
// --- MODIFICADO: Definir tabla ---
$table = 'inv_suppliers';
// --- FIN MODIFICADO ---

function db_name(PDO $pdo){ return $pdo->query("SELECT DATABASE()")->fetchColumn(); }
function has_col(PDO $pdo,$table,$col){
  $sql="SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st=$pdo->prepare($sql); $st->execute([db_name($pdo), $table, $col]);
  return (int)$st->fetchColumn() > 0;
}
function has_fk(PDO $pdo,$table,$fkName){
  $sql="SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'";
  $st=$pdo->prepare($sql); $st->execute([db_name($pdo), $table, $fkName]);
  return (int)$st->fetchColumn() > 0;
}
function add_col(PDO $pdo,$table,$ddl){
  $pdo->exec("ALTER TABLE `$table` ADD $ddl");
}
function add_fk(PDO $pdo,$table,$fkName,$ddl){
  $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$fkName` $ddl");
}

try {
  $pdo->beginTransaction();

  // Columnas base
  if (!has_col($pdo,$table,'name'))        add_col($pdo,$table,"`name` VARCHAR(190) NOT NULL");
  if (!has_col($pdo,$table,'email'))       add_col($pdo,$table,"`email` VARCHAR(190) NULL");
  if (!has_col($pdo,$table,'phone'))       add_col($pdo,$table,"`phone` VARCHAR(50) NULL");
  if (!has_col($pdo,$table,'tax_id'))      add_col($pdo,$table,"`tax_id` VARCHAR(32) NULL");
  if (!has_col($pdo,$table,'address'))     add_col($pdo,$table,"`address` VARCHAR(255) NULL");
  if (!has_col($pdo,$table,'contact_name'))add_col($pdo,$table,"`contact_name` VARCHAR(190) NULL");
  if (!has_col($pdo,$table,'is_active'))   add_col($pdo,$table,"`is_active` TINYINT(1) NOT NULL DEFAULT 1");
  if (!has_col($pdo,$table,'company_id'))  add_col($pdo,$table,"`company_id` INT NULL");
  if (!has_col($pdo,$table,'created_at'))  add_col($pdo,$table,"`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");

  // FK a companies(id)
  // --- MODIFICADO: Apuntar a 'inv_companies' ---
  if (has_col($pdo,$table,'company_id') && !has_fk($pdo,$table,'fk_suppliers_company')) {
    // limpia índice viejo si existiera con otro nombre (opcional)
    try { $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `suppliers_company_id_fk`"); } catch(Throwable $e){}
    add_fk($pdo,$table,'fk_suppliers_company',
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