<?php
// alerts.php
// Lógica de alertas de stock (global y por almacén) usando app_send_mail() sin Composer.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php'; // expone app_send_mail($to,$subject,$body,...)
require_once __DIR__ . '/config.php';

/** Devuelve el email primario al que enviar alertas:
 * - ALERT_TO_EMAIL (si está definido)
 * - o el email del primer admin encontrado en 'global_users'
 */
function app_primary_admin_email(PDO $pdo): ?string {
  if (defined('ALERT_TO_EMAIL') && ALERT_TO_EMAIL) return ALERT_TO_EMAIL;
  // --- MODIFICADO ---
  $email = $pdo->query("SELECT email FROM global_users WHERE email LIKE '%@%' ORDER BY id ASC LIMIT 1")->fetchColumn();
  // --- FIN MODIFICADO ---
  return $email ?: null;
}

/** Remitente de los correos */
function app_from_email(): string {
  return (defined('ALERT_FROM_EMAIL') && ALERT_FROM_EMAIL) ? ALERT_FROM_EMAIL : 'no-reply@local';
}

/**
 * Alerta GLOBAL (por producto, no por almacén).
 * Se dispara si $stock_now < min_stock y respeta cooldown, reintentos y escalado del propio producto.
 * Úsala si quieres avisos agregados sin distinguir almacenes.
 */
function maybe_send_stock_alert_global(PDO $pdo, array $productRow, int $stockNow): bool {
  // Lógica de esta función (si existe) no proporcionada en el original
  // ...
  return false;
}


/**
 * Alerta POR ALMACÉN (product_id + warehouse_id)
 * Se dispara si $stock_now < min_stock y respeta cooldown, reintentos y escalado
 * en la tabla 'inv_product_warehouse_alerts'.
 */
function maybe_send_stock_alert(
  PDO $pdo,
  int $product_id,
  int $warehouse_id,
  int $stock_now
): bool {

  // 1) Cargar producto (requiere min_stock)
  // --- MODIFICADO ---
  $p = $pdo->prepare("SELECT p.* FROM inv_products p WHERE p.id=?");
  // --- FIN MODIFICADO ---
  $p->execute([$product_id]);
  $prod = $p->fetch(PDO::FETCH_ASSOC);

  // No hay producto, no tiene min_stock, o el stock es suficiente
  if (!$prod || empty($prod['min_stock']) || $stock_now >= (int)$prod['min_stock']) {
    return false;
  }
  
  // 2) Emails (normal + escalado)
  $to_normal = app_primary_admin_email($pdo);
  $to_escal  = defined('ALERT_ESCALATION_EMAIL') ? ALERT_ESCALATION_EMAIL : null;
  if (!$to_normal && !$to_escal) return false; // Nadie a quien avisar
  if (!$to_normal) $to_normal = $to_escal; // Si no hay normal, usar escalado

  // 3) Cooldown y reintentos
  $cooldown_h = defined('ALERT_COOLDOWN_H') ? (int)ALERT_COOLDOWN_H : 24;
  $max_res    = defined('ALERT_MAX_RESEND') ? (int)ALERT_MAX_RESEND : 3;
  $escalated  = false;
  $res_cnt    = 0;

  // --- MODIFICADO ---
  $st = $pdo->prepare("SELECT * FROM inv_product_warehouse_alerts WHERE product_id=? AND warehouse_id=?");
  // --- FIN MODIFICADO ---
  $st->execute([$product_id, $warehouse_id]);
  $state = $st->fetch(PDO::FETCH_ASSOC);

  if ($state) {
    $escalated = (bool)$state['escalated'];
    $res_cnt   = (int)$state['resent_count'];
    // Cooldown
    if ($state['last_sent'] && time() - strtotime($state['last_sent']) < ($cooldown_h * 3600)) {
      return false; // En cooldown
    }
  } else {
    // --- MODIFICADO ---
    $pdo->prepare("INSERT INTO inv_product_warehouse_alerts (product_id, warehouse_id) VALUES (?,?)")
        ->execute([$product_id, $warehouse_id]);
    // --- FIN MODIFICADO ---
  }
  $res_cnt++;

  // 4) Subject (normal o escalado)
  // --- MODIFICADO ---
  $w = $pdo->prepare("SELECT name FROM inv_warehouses WHERE id=?");
  // --- FIN MODIFICADO ---
  $w->execute([$warehouse_id]);
  $wname = $w->fetchColumn() ?: ('#'.$warehouse_id);

  $to = $to_normal;
  $subject = "[ALERTA STOCK] {$prod['name']} (SKU {$prod['sku']}) ALMACÉN {$wname} < mínimo";
  if ($res_cnt >= $max_res && $to_escal && !$escalated) {
    $to = $to_escal;
    $subject = "[ESCALADO] {$subject}";
  }
  if (!$to) return false;

  // 5) Contenido
  $body = "Producto: {$prod['name']} (SKU: {$prod['sku']})\\n".
          "Almacén: {$wname}\\n".
          "Stock actual: {$stock_now}\\n".
          "Mínimo: {$prod['min_stock']}\\n".
          "Recomendado: " . ($prod['recommended_stock'] ?? 'N/A') . "\\n".
          "Fecha: ".date('Y-m-d H:i:s')."\\n";

  // 6) Envío y actualización de estado
  if (app_send_mail($to, $subject, $body)) {
    if ($state) {
      if ($res_cnt >= $max_res && $to_escal && !$escalated) {
        // --- MODIFICADO ---
        $pdo->prepare("UPDATE inv_product_warehouse_alerts SET last_sent=NOW(), escalated=1 WHERE product_id=? AND warehouse_id=?")
            ->execute([$product_id, $warehouse_id]);
        // --- FIN MODIFICADO ---
      } else {
        // --- MODIFICADO ---
        $pdo->prepare("UPDATE inv_product_warehouse_alerts SET last_sent=NOW(), resent_count=? WHERE product_id=? AND warehouse_id=?")
            ->execute([$res_cnt, $product_id, $warehouse_id]);
        // --- FIN MODIFICADO ---
      }
    }
    return true;
  }
  return false;
}