<?php
// lib/table_detector.php (supports pre-mapped config)
if (!function_exists('td_current_db')) {
  function td_current_db(PDO $pdo): string {
    try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); } catch (Throwable $e) { return ''; }
  }
}
if (!function_exists('td_list_tables')) {
  function td_list_tables(PDO $pdo): array {
    $db = td_current_db($pdo);
    $st = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=?");
    $st->execute([$db]);
    return array_map(fn($r)=>$r['TABLE_NAME'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
  }
}
if (!function_exists('td_best_match')) {
  function td_best_match(string $entity, array $tables): ?string {
    $patterns = [
      'products'  => ['products','productos','producto','articulos','items'],
      'orders'    => ['purchase_orders','orders','pedidos','ventas_pedidos','sales_orders'],
      'deliveries'=> ['delivery_notes','albaranes','albaran','delivery_note'],
      'companies' => ['companies','empresas','company'],
    ];
    $cands = $patterns[$entity] ?? [];
    $scores = [];
    foreach ($tables as $t) {
      $lt = mb_strtolower($t); $score = 0;
      foreach ($cands as $i=>$pat) {
        if ($lt === $pat) { $score = 100 - $i; break; } // Exact match
        if (str_starts_with($lt, $pat)) $score = max($score, 80 - $i);
        if (str_ends_with($lt, $pat)) $score = max($score, 70 - $i);
        if (mb_strpos($lt, $pat)!==false) $score = max($score, 50 - $i);
      }
      if ($score > 0) $scores[$t] = $score;
    }
    if ($scores) { arsort($scores); return array_key_first($scores); }
    return null;
  }
}
if (!function_exists('td_detect_all')) {
  function td_detect_all(PDO $pdo): array {
    $tables = td_list_tables($pdo);
    $entities = ['products','orders','deliveries','companies'];
    $map = [];
    foreach ($entities as $e) { $map[$e] = td_best_match($e, $tables); }
    return $map;
  }
}
if (!function_exists('td_config_path')) {
  function td_config_path(): string {
    $base = __DIR__; // lib/
    try {
      if (defined('APP_ROOT')) $base = rtrim(APP_ROOT,'/');
      elseif (defined('__DIR__')) $base = dirname(__DIR__); // app/
    } catch (Throwable $e) {}
    // Assume if "lib" is in root, or one level below
    if (basename($base)=='lib') $base = dirname($base);
    // Assume if we are in /app/lib, config is in /app/
    if (basename($base)!=='app' && is_dir($base . '/app')) $base .= '/app';
    // If has /config, save there
    elseif (is_dir($base . '/config')) $base .= '/config';
    // If has /storage, save there
    elseif (is_dir($base . '/storage')) { $base .= '/storage'; if (is_dir($base . '/app')) $base .= '/app'; }
    // If has /var, save there
    elseif (is_dir($base . '/var')) { $base .= '/var'; if (is_dir($base . '/cache')) $base .= '/cache'; }
    // If not, in root
    else {}
    // Fallback if we are in /app/lib and config is in /config
    if (basename($base)=='app' && !is_dir($base) && is_dir(dirname($base).'/config')) { $base = dirname($base).'/config'; }
    // Ensure writable
    if (!is_writable($base)) { $base = sys_get_temp_dir(); }
    // Ensure dir
    if (!is_dir($base)) { @mkdir($base, 0775, true); }
    return $base . '/tables.json';
  }
}
if (!function_exists('td_save_config')) {
  function td_save_config(array $map): bool {
    $path = td_config_path();
    $json = json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    return (bool)@file_put_contents($path, $json);
  }
}
if (!function_exists('td_load_config')) {
  function td_load_config(): array {
    $path = td_config_path();
    if (is_file($path)) {
        $txt = @file_get_contents($path);
        $arr = json_decode((string)$txt, true);
        if (is_array($arr)) return $arr;
    }
    
    // --- MODIFICACIÓN: Añadir mapa por defecto si tables.json no existe ---
    // (Tu config/tables.json ya tiene esto, pero lo ponemos como fallback)
    $defaults = [
        "products" => "inv_products",
        "orders" => "inv_purchase_orders",
        "order_lines" => "inv_purchase_order_lines",
        "deliveries" => "inv_delivery_notes",
        "delivery_lines" => "inv_delivery_note_lines",
        "companies" => "inv_companies",
        "categories" => "inv_categories",
        "clients" => "inv_clients",
        "movements" => "inv_movements",
        "product_stock" => "inv_product_stock",
        "product_suppliers" => "inv_product_suppliers",
        "suppliers" => "inv_suppliers",
        "warehouses" => "inv_warehouses"
    ];
    return $defaults;
    // --- FIN MODIFICACIÓN ---
  }
}
if (!function_exists('td_get_table')) {
  function td_get_table(PDO $pdo, string $entity): ?string {
    // Override explícito por GET
    if (isset($_GET['table']) && $_GET['table']!=='') {
      $t = preg_replace('/[^a-zA-Z0-9_\\-]/','', $_GET['table']); // Basic sanitation
      $_SESSION['tables'][$entity] = $t;
      $cfg = td_load_config(); $cfg[$entity]=$t; td_save_config($cfg);
      return $t;
    }
    // Sesión
    if (!empty($_SESSION['tables'][$entity])) return $_SESSION['tables'][$entity];
    
    // Config pre-cargada
    $cfg = td_load_config();
    
    // --- INICIO DE CORRECCIÓN ---
    // 1. Comprobar si la entidad (ej. 'products') está en el config
    if (!empty($cfg[$entity])) {
        return $cfg[$entity]; 
    }

    // 2. Si no, comprobar si la 'entidad' que nos pasaron (ej. 'inv_products') 
    //    es uno de los *valores* válidos en el config.
    if (in_array($entity, $cfg, true)) {
        return $entity; // La entidad ya es un nombre de tabla válido
    }
    // --- FIN DE CORRECCIÓN ---

    // Auto-detect (fallback)
    $t = td_best_match($entity, td_list_tables($pdo));
    if ($t) {
      $_SESSION['tables'][$entity] = $t;
      $cfg[$entity]=$t; td_save_config($cfg);
      return $t;
    }
    
    // Si todo falla, devuelve null (esto es lo que causaba el error)
    return null; 
  }
}