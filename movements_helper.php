<?php
// movements_helper.php (Adaptado para inv_ y global_users)
// Centraliza la creación de movimientos y actualiza inv_product_stock.

if (!function_exists('create_movement')) {
  /**
   * Crea un movimiento de stock y actualiza la tabla inv_product_stock.
   *
   * @param PDO      $pdo
   * @param int      $product_id
   * @param int      $warehouse_id
   * @param float    $quantity      Cantidad (siempre positiva).
   * @param string   $type          'entrada' | 'salida' | 'ajuste'
   * @param string   $reason        Motivo del movimiento (ej. 'Venta', 'Compra #123', 'Ajuste inventario')
   * @param int|null $reference_id  ID del documento relacionado (ej. ID de albarán, pedido, etc.)
   * @param int|null $user_id       ID del usuario global que realiza la acción (opcional)
   * @return bool                   True si se creó el movimiento, False en caso de error.
   */
  function create_movement(
      PDO $pdo,
      int $product_id,
      int $warehouse_id,
      float $quantity,
      string $type,
      string $reason = '',
      ?int $reference_id = null,
      ?int $user_id = null
  ): bool {

    // Validar tipo
    if (!in_array($type, ['entrada', 'salida', 'ajuste'])) {
      error_log("Tipo de movimiento inválido: $type");
      return false;
    }
    // Cantidad debe ser positiva
    if ($quantity <= 0) {
        error_log("Cantidad de movimiento debe ser positiva: $quantity");
        return false;
    }

    // Determinar la cantidad a insertar en movements (positiva o negativa)
    $mov_qty = 0;
    if ($type === 'entrada') {
        $mov_qty = $quantity;
    } elseif ($type === 'salida') {
        $mov_qty = -$quantity;
    } elseif ($type === 'ajuste') {
        // Para ajuste, la cantidad puede ser positiva o negativa
        // Asumimos que $quantity representa el *nuevo* stock deseado.
        // O mejor, que $quantity es la *diferencia* a aplicar.
        // Vamos a asumir que $quantity es la diferencia (+/-)
        $mov_qty = $quantity; // Permitir negativos aquí si viene de ajuste
    }


    try {
        $pdo->beginTransaction();

        // 1. Insertar en inv_movements
        // --- MODIFICADO: Usar tabla inv_movements ---
        $stmt_mov = $pdo->prepare("
            INSERT INTO inv_movements
                (product_id, warehouse_id, quantity, type, reason, reference_id, user_id, movement_date)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        // Usar $mov_qty calculado (+/-), pero guardar el 'type' original ('entrada', 'salida', 'ajuste')
        $stmt_mov->execute([
            $product_id,
            $warehouse_id,
            $mov_qty,
            $type,
            $reason,
            $reference_id,
            $user_id // Puede ser null
        ]);
        // --- FIN MODIFICADO ---

        // 2. Actualizar inv_product_stock (INSERT ... ON DUPLICATE KEY UPDATE)
        // --- MODIFICADO: Usar tabla inv_product_stock ---
        $stmt_stock = $pdo->prepare("
            INSERT INTO inv_product_stock (product_id, warehouse_id, quantity, last_updated)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                last_updated = NOW()
        ");
        // Aquí siempre usamos $mov_qty (+/-) para sumar o restar al stock existente
        $stmt_stock->execute([$product_id, $warehouse_id, $mov_qty]);
        // --- FIN MODIFICADO ---

        $pdo->commit();

        // Opcional: Llamar a la función de alerta de stock bajo
        // require_once __DIR__ . '/alerts.php';
        // $current_stock_st = $pdo->prepare("SELECT quantity FROM inv_product_stock WHERE product_id=? AND warehouse_id=?");
        // $current_stock_st->execute([$product_id, $warehouse_id]);
        // $current_stock = (int)$current_stock_st->fetchColumn();
        // maybe_send_stock_alert($pdo, $product_id, $warehouse_id, $current_stock);

        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al crear movimiento: " . $e->getMessage());
        return false;
    }
  }
}
?>