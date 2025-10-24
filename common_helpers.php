<?php
// common_helpers.php - utilidades compartidas con guards
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('current_db')) {
  function current_db(PDO $pdo): string {
    try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); } catch(Throwable $e){ return ''; }
  }
}
if (!function_exists('is_numeric_type')) {
  function is_numeric_type(string $t): bool {
    static $nums = ['int','integer','bigint','smallint','mediumint','tinyint','decimal','numeric','float','double','real'];
    return in_array($t, $nums, true);
  }
}
if (!function_exists('is_text_type')) {
  function is_text_type(string $t): bool {
    static $txt = ['varchar','char','text','mediumtext','longtext'];
    return in_array($t, $txt, true);
  }
}
if (!function_exists('is_long_text')) { function is_long_text(string $t): bool { return in_array($t, ['text','mediumtext','longtext'], true); } }
if (!function_exists('pretty_label')) {
  function pretty_label(string $col): string {
    $col = str_replace('_',' ', $col);
    $col = preg_replace('/\s+/',' ', $col);
    return mb_convert_case($col, MB_CASE_TITLE, "UTF-8");
  }
}
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo, string $table): bool {
    try {
      $db = current_db($pdo);
      $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
      $st->execute([$db, $table]);
      return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
  }
}
if (!function_exists('get_table_columns')) {
  function get_table_columns(PDO $pdo, string $table): array {
    $db = current_db($pdo);
    $st = $pdo->prepare("
      SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, IS_NULLABLE, COLUMN_DEFAULT,
             CHARACTER_MAXIMUM_LENGTH, ORDINAL_POSITION
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
      ORDER BY ORDINAL_POSITION
    ");
    $st->execute([$db, $table]);
    $cols = [];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $name = (string)$r['COLUMN_NAME'];
      $cols[$name] = [
        'type'   => strtolower((string)$r['DATA_TYPE']),
        'key'    => (string)$r['COLUMN_KEY'],
        'null'   => ((string)$r['IS_NULLABLE'] === 'YES'),
        'def'    => $r['COLUMN_DEFAULT'],
        'maxlen' => $r['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int)$r['CHARACTER_MAXIMUM_LENGTH'] : null,
        'pos'    => (int)$r['ORDINAL_POSITION'],
      ];
    }
    return $cols;
  }
}
if (!function_exists('get_primary_key')) {
  function get_primary_key(array $colsMeta): string {
    foreach ($colsMeta as $c=>$m) if (($m['key'] ?? '') === 'PRI') return $c;
    return isset($colsMeta['id']) ? 'id' : array_key_first($colsMeta);
  }
}
if (!function_exists('get_foreign_keys')) {
  function get_foreign_keys(PDO $pdo, string $table): array {
    $db = current_db($pdo);
    $st = $pdo->prepare("
      SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
      WHERE k.TABLE_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $st->execute([$db, $table]);
    $map = [];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $map[(string)$r['COLUMN_NAME']] = [
        'ref_table' => (string)$r['REFERENCED_TABLE_NAME'],
        'ref_col'   => (string)$r['REFERENCED_COLUMN_NAME'],
      ];
    }
    return $map;
  }
}
if (!function_exists('pick_label_column')) {
  function pick_label_column(PDO $pdo, string $table): string {
    $cols = get_table_columns($pdo, $table);
    foreach (['codigo','nombre','name','descripcion','description_corta','ref','referencia'] as $pref) {
      if (isset($cols[$pref])) return $pref;
    }
    foreach ($cols as $c=>$meta) if (is_text_type($meta['type'])) return $c;
    foreach ($cols as $c=>$meta) if (!$meta['type'] || !is_numeric_type($meta['type'])) return $c;
    return 'id';
  }
}
if (!function_exists('fetch_fk_options')) {
  function fetch_fk_options(PDO $pdo, string $table, string $idCol='id', ?string $labelCol=null, int $limit=1000): array {
    $labelCol = $labelCol ?: pick_label_column($pdo, $table);
    $sql = "SELECT `$idCol` AS id, `$labelCol` AS label FROM `$table` ORDER BY `$labelCol` ASC LIMIT $limit";
    try { $st = $pdo->query($sql); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { return []; }
  }
}

// --- Funciones Flash Message ---
if (!function_exists('set_flash')) {
    function set_flash(string $type, string $msg): void {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }
}
if (!function_exists('get_flash_msg')) {
    function get_flash_msg(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $flash = $_SESSION['flash'] ?? null;
        if ($flash) {
            unset($_SESSION['flash']);
            return '<div class="alert alert-' . h($flash['type']) . '">' . h($flash['msg']) . '</div>';
        }
        return '';
    }
}

// --- INICIO: Funciones para Configuración Global en BD ---
if (!function_exists('get_app_setting')) {
    /** Lee una configuración global de la tabla app_settings */
    function get_app_setting(PDO $pdo, string $key, $default = null) {
        try {
            // Crear tabla si no existe (solo la primera vez)
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            // Decodificar JSON si no es falso/nulo, si falla devuelve el valor original (podría no ser JSON)
            return ($value !== false && $value !== null) ? (json_decode($value, true) ?? $value) : $default;
        } catch (Throwable $e) {
            error_log("Error getting app setting '$key': " . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('set_app_setting')) {
    /** Guarda o actualiza una configuración global en app_settings */
    function set_app_setting(PDO $pdo, string $key, $value): bool {
        try {
             // Crear tabla si no existe (solo la primera vez)
             $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Codificar a JSON solo si es array u objeto
            $jsonValue = (is_array($value) || is_object($value)) ? json_encode($value) : $value;
            $stmt = $pdo->prepare(
                "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            return $stmt->execute([$key, $jsonValue]);
        } catch (Throwable $e) {
            error_log("Error setting app setting '$key': " . $e->getMessage());
            return false;
        }
    }
}
// --- FIN: Funciones para Configuración Global en BD ---

?>