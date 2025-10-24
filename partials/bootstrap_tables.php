<?php
// partials/bootstrap_tables.php - Helpers for rendering Bootstrap tables/forms

if (!function_exists('list_tables')) {
  /** Lista todas las tablas de la BD actual */
  function list_tables(PDO $pdo): array {
    $db = current_db($pdo); // Necesita current_db() de common_helpers
    $st = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=?");
    $st->execute([$db]);
    return array_map(fn($r)=>$r['TABLE_NAME'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
  }
}

if (!function_exists('list_columns')) {
  /** Devuelve metadatos de las columnas de una tabla (compatible con el formato original) */
  function list_columns(PDO $pdo, string $table): array {
    // Reutiliza get_table_columns de common_helpers pero adapta el formato si es necesario
    $colsMeta = get_table_columns($pdo, $table); // Necesita get_table_columns() de common_helpers
    $output = [];
    foreach ($colsMeta as $name => $meta) {
        $output[] = [
            'name' => $name,
            'type' => $meta['type'],
            'key' => $meta['key'],
            'null' => $meta['null'],
            'default' => $meta['def'],
            'maxlen' => $meta['maxlen'],
            'pos' => $meta['pos'],
        ];
    }
    return $output;
  }
}


if (!function_exists('list_related_tables_for_select')) {
  /** Prepara datos para selects de FKs */
  function list_related_tables_for_select(PDO $pdo, string $table): array {
    $fks = get_foreign_keys($pdo, $table); // Necesita get_foreign_keys() de common_helpers
    $related = [];
    foreach ($fks as $col => $fkInfo) {
      $related[$col] = [
        'table' => $fkInfo['ref_table'],
        'label_col' => pick_label_column($pdo, $fkInfo['ref_table']), // Necesita pick_label_column() de common_helpers
        'options' => fetch_fk_options($pdo, $fkInfo['ref_table'], $fkInfo['ref_col'] ?: 'id') // Necesita fetch_fk_options() de common_helpers
      ];
    }
    return $related;
  }
}

if (!function_exists('num_format')) {
  /** Formatea números decimales/enteros */
  function num_format($val): string {
    if ($val === null || $val === '') return '';
    $fval = (float)$val;
    // Si tiene decimales (comparando con su versión entera)
    if (abs($fval - floor($fval)) > 0.00001) {
        return number_format($fval, 2, ',', '.');
    } else {
        return number_format($fval, 0, ',', '.');
    }
  }
}


if (!function_exists('render_crud_form_field')) {
  /** Renderiza un campo de formulario Bootstrap basado en metadatos de columna */
  function render_crud_form_field(PDO $pdo, array $col, $value, array $related, string $label): string {
    $k = $col['name'];
    $type = $col['type'];
    $labelHtml = '<label class="form-label form-label-sm">' . h($label) . '</label>'; // Necesita h()
    $attrs = 'name="' . h($k) . '" class="form-select form-select-sm"';

    if (isset($related[$k])) { // Select para FK
        $optsHtml = '<option value="">-- Seleccionar --</option>';
        foreach ($related[$k]['options'] as $opt) {
            $sel = ((string)($value ?? '') === (string)$opt['id']) ? ' selected' : '';
            $optsHtml .= '<option value="' . h($opt['id']) . '"' . $sel . '>' . h($opt['label']) . '</option>';
        }
        return $labelHtml . '<select ' . $attrs . '>' . $optsHtml . '</select>';
    } elseif ($k === 'is_active' || $type === 'tinyint') { // Checkbox/Switch
        $checked = $value ? ' checked' : '';
        return '<div class="form-check form-switch mt-3">' .
               '<input class="form-check-input" type="checkbox" name="' . h($k) . '" value="1"' . $checked . '>' .
               '<label class="form-check-label">' . h($label) . '</label>' .
               '</div>';
    } elseif (is_long_text($type)) { // Textarea
        return $labelHtml . '<textarea class="form-control form-control-sm" name="' . h($k) . '" rows="3">' . h($value) . '</textarea>';
    } elseif (is_numeric_type($type)) { // Input Number
        $step = ($type == 'decimal' || $type == 'float' || $type == 'double') ? '0.01' : '1';
        return $labelHtml . '<input type="number" step="' . $step . '" class="form-control form-control-sm" name="' . h($k) . '" value="' . h($value) . '">';
    } else { // Input Text (varchar, etc.)
        $maxlen = $col['maxlen'] ?? 255;
        return $labelHtml . '<input class="form-control form-control-sm" name="' . h($k) . '" value="' . h($value) . '" maxlength="' . h($maxlen) . '">';
    }
  }
}


if (!function_exists('render_crud_filters')) {
  /** Renderiza los filtros para la tabla CRUD */
 function render_crud_filters(PDO $pdo, array $cols, array $related, array $searchables, array $activeFilters, string $q, int $total, int $per, array $colLabels): string {
    ob_start(); // Inicia buffer de salida
    ?>
    <form class="row g-2">
      <div class="col-12">
        <div class="row g-2">
            <div class="col-md-6">
                <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="Buscar en campos de texto...">
            </div>
            <div class="col-md-6 text-end small text-muted align-self-center">
                <?= number_format($total,0,',','.') ?> resultados · <?= (int)$per ?> / pág
            </div>
        </div>
      </div>
      <?php foreach($searchables as $col=>$enabled):
          if(!$enabled || !isset($cols[$col])) continue; $c = $cols[$col]; $label = $colLabels[$col]; $val = $activeFilters[$col] ?? ''; ?>
          <div class="col-md-3">
              <label class="form-label form-label-sm"><?= h($label) ?></label>
              <?php if (isset($related[$col])): ?>
                  <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                      <option value="">-- Todos --</option>
                      <?php foreach($related[$col]['options'] as $opt): ?>
                      <option value="<?= h($opt['id']) ?>" <?= ((string)$val === (string)$opt['id']) ? 'selected' : '' ?>><?= h($opt['label']) ?></option>
                      <?php endforeach; ?>
                  </select>
              <?php elseif ($c['type'] === 'tinyint'): ?>
                   <select class="form-select form-select-sm" name="s_<?= h($col) ?>">
                      <option value="">-- Todos --</option>
                      <option value="1" <?= $val === '1' ? 'selected' : '' ?>>Sí</option>
                      <option value="0" <?= $val === '0' ? 'selected' : '' ?>>No</option>
                  </select>
              <?php else: ?>
                  <input class="form-control form-control-sm" name="s_<?= h($col) ?>" value="<?= h($val) ?>">
              <?php endif; ?>
          </div>
      <?php endforeach; ?>
      <div class="col-12">
          <div class="d-flex justify-content-end gap-2 mt-2">
              <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Buscar</button>
              <a class="btn btn-sm btn-outline-secondary" href="<?= basename($_SERVER['PHP_SELF']) ?>"><i class="bi bi-eraser"></i> Limpiar</a>
          </div>
      </div>
    </form>
    <?php
    return ob_get_clean(); // Devuelve el contenido del buffer
  }
}

// --- INICIO DE CÓDIGO AÑADIDO ---
if (!function_exists('render_pagination')) {
    /**
     * Renderiza la paginación Bootstrap.
     * @param string $baseURL La URL base (ej. 'productos.php')
     * @param int $currentPage La página actual.
     * @param int $totalPages El número total de páginas.
     * @param array $currentParams Array de parámetros GET actuales (para mantener filtros).
     * @return string HTML de la paginación, o string vacío si no se necesita.
     */
    function render_pagination(string $baseURL, int $currentPage, int $totalPages, array $currentParams): string {
        if ($totalPages <= 1) {
            return '';
        }

        unset($currentParams['page']); // Quitar el parámetro 'page' para construir los nuevos enlaces
        $queryString = http_build_query($currentParams);
        $baseURL .= '?' . ($queryString ? $queryString . '&' : '');

        $html = '<nav class="mt-3 d-flex justify-content-end"><ul class="pagination pagination-sm mb-0">';

        // Botón Primera y Anterior
        $disabledFirst = ($currentPage <= 1) ? ' disabled' : '';
        $html .= '<li class="page-item' . $disabledFirst . '"><a class="page-link" href="' . h($baseURL . 'page=1') . '">«</a></li>';
        $prevPage = max(1, $currentPage - 1);
        $html .= '<li class="page-item' . $disabledFirst . '"><a class="page-link" href="' . h($baseURL . 'page=' . $prevPage) . '">‹</a></li>';

        // Números de página (simplificado para no mostrar demasiados)
        // Muestra +/- 2 páginas alrededor de la actual
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);

        // Ajustes para bordes
        if ($currentPage <= 3) $endPage = min($totalPages, 5);
        if ($currentPage >= $totalPages - 2) $startPage = max(1, $totalPages - 4);
        
        if ($startPage > 1) {
             $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i == $currentPage) ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . h($baseURL . 'page=' . $i) . '">' . $i . '</a></li>';
        }
        
         if ($endPage < $totalPages) {
             $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        // Botón Siguiente y Última
        $disabledLast = ($currentPage >= $totalPages) ? ' disabled' : '';
        $nextPage = min($totalPages, $currentPage + 1);
        $html .= '<li class="page-item' . $disabledLast . '"><a class="page-link" href="' . h($baseURL . 'page=' . $nextPage) . '">›</a></li>';
        $html .= '<li class="page-item' . $disabledLast . '"><a class="page-link" href="' . h($baseURL . 'page=' . $totalPages) . '">»</a></li>';

        $html .= '</ul></nav>';
        return $html;
    }
}
// --- FIN DE CÓDIGO AÑADIDO ---