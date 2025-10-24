<?php
// migraciones/ensure_companies_columns.php
require_once __DIR__ . '/../db.php';

// --- MODIFICADO: Definir tabla ---
$table = 'inv_companies';
// --- FIN MODIFICADO ---

try {
  $pdo->exec("
    ALTER TABLE $table
      ADD COLUMN IF NOT EXISTS tax_id      VARCHAR(32)  NULL AFTER name,
      ADD COLUMN IF NOT EXISTS email       VARCHAR(190) NULL AFTER tax_id,
      ADD COLUMN IF NOT EXISTS phone       VARCHAR(50)  NULL AFTER email,
      ADD COLUMN IF NOT EXISTS address     VARCHAR(255) NULL AFTER phone,
      ADD COLUMN IF NOT EXISTS city        VARCHAR(80)  NULL AFTER address,
      ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20)  NULL AFTER city,
      ADD COLUMN IF NOT EXISTS country     VARCHAR(80)  NULL AFTER postal_code,
      ADD COLUMN IF NOT EXISTS logo_url    VARCHAR(255) NULL AFTER country,
      ADD COLUMN IF NOT EXISTS created_at  DATETIME     NULL DEFAULT CURRENT_TIMESTAMP;
  ");
  echo "OK: columnas comprobadas/creadas en '$table'.\n";
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: " . $e->getMessage();
}