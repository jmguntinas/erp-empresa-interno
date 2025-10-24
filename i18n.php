<?php
// i18n.php - helpers de traducción ES/EN con fallback
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!function_exists('i18n_langs')) {
  function i18n_langs(): array { return ['es','en']; }
}
if (!function_exists('i18n_default_lang')) {
  function i18n_default_lang(): string { return 'es'; }
}
if (!function_exists('i18n_get_lang')) {
  function i18n_get_lang(): string {
    $lang = $_SESSION['app_lang'] ?? $_COOKIE['APP-LANG'] ?? i18n_default_lang();
    $lang = strtolower(substr((string)$lang,0,2));
    return in_array($lang, i18n_langs(), true) ? $lang : i18n_default_lang();
  }
}
if (!function_exists('i18n_set_lang')) {
  function i18n_set_lang(string $lang): void {
    $lang = strtolower(substr($lang,0,2));
    if (!in_array($lang, i18n_langs(), true)) $lang = i18n_default_lang();
    $_SESSION['app_lang'] = $lang;
    setcookie('APP-LANG', $lang, [
      'expires' => time()+60*60*24*365, // 1 año
      'path' => '/',
      'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'httponly' => true, // Más seguro para cookie de idioma
      'samesite' => 'Lax',
    ]);
    // Limpiar cache al cambiar idioma
    unset($GLOBALS['__I18N_CACHE']);
  }
}

// Cache simple en memoria para evitar consultas repetidas
$GLOBALS['__I18N_CACHE'] = [];
$GLOBALS['__I18N_LOADED'] = [];

if (!function_exists('i18n_load_cache')) {
  // Carga todas las traducciones de un idioma a la cache global
  function i18n_load_cache(PDO $pdo, string $lang): void {
    if (!empty($GLOBALS['__I18N_LOADED'][$lang])) return; // Ya cargado

    try {
        $st = $pdo->prepare("SELECT scope, `key`, value FROM i18n_translations WHERE lang = ?");
        $st->execute([$lang]);
        $translations = $st->fetchAll(PDO::FETCH_ASSOC);

        // --- Esta es la línea 62 (aprox) ---
        foreach($translations as $row) {
            $GLOBALS['__I18N_CACHE'][$row['scope'].':'.$row['key']] = $row['value'];
        }
        // --- Fin Línea 62 ---

        $GLOBALS['__I18N_LOADED'][$lang] = true;
    } catch (Throwable $e) {
        // Error cargando traducciones (ej. tabla no existe), no hacer nada crítico
        error_log("Error loading i18n cache: " . $e->getMessage());
        $GLOBALS['__I18N_LOADED'][$lang] = true; // Marcar como cargado para no reintentar
    }
  }
}

// Función simple para adivinar plurales/género básicos en ES (muy simple)
if (!function_exists('i18n_guess_es')) {
    function i18n_guess_es(string $key): string {
        $key = str_replace(['.', '_'], ' ', $key); // Reemplaza puntos y guiones bajos
        $key = preg_replace('/(?<!^)([A-Z])/', ' $1', $key); // Inserta espacio antes de mayúsculas (camelCase)
        $key = trim($key);
        $key = mb_strtolower($key);
        // Capitalizar primera letra de cada palabra
        return mb_convert_case($key, MB_CASE_TITLE, "UTF-8");
    }
}

if (!function_exists('t')) {
  // Función principal de traducción
  function t(PDO $pdo, string $scope, string $key, ?string $fallback=null): string {
    $lang = i18n_get_lang();
    // Asegurarse de que las traducciones para el idioma están cargadas
    if (empty($GLOBALS['__I18N_LOADED'][$lang])) {
      i18n_load_cache($pdo, $lang);
    }

    $cache = $GLOBALS['__I18N_CACHE'] ?? [];
    $val = $cache[$scope.':'.$key] ?? null;

    if ($val !== null) return $val; // Devolver traducción si existe

    // Si no hay traducción, devolver el fallback
    // Si el idioma es 'es', intentar "adivinar" una versión legible
    if ($fallback === null) $fallback = $key;
    return ($lang === 'es') ? i18n_guess_es($fallback) : $fallback;
  }
}

if (!function_exists('tl')) {
  // Traduce etiqueta de columna (scope='column')
  function tl(PDO $pdo, string $table, string $column, ?string $fallback=null): string {
    // La clave será 'table.column.label'
    return t($pdo, 'column', $table.'.'.$column.'.label', $fallback ?? $column);
  }
}

if (!function_exists('ti')) {
  // Traduce textos de UI (scope='ui')
  function ti(PDO $pdo, string $key, ?string $fallback=null): string {
     return t($pdo, 'ui', $key, $fallback ?? $key);
  }
}


/* ====== Semilla inicial (Opcional pero útil) ====== */
if (!function_exists('i18n_seed_if_missing')) {
  // Añade traducciones por defecto (en inglés o español "adivinado") si no existen
  // ¡OJO! Esto solo añade el término base, necesita traducción manual posterior.
  function i18n_seed_if_missing(PDO $pdo, string $scope, array $termsMap): void {
      try {
          $st_check = $pdo->prepare("SELECT 1 FROM i18n_translations WHERE scope=? AND `key`=? AND lang=? LIMIT 1");
          $st_insert = $pdo->prepare("INSERT INTO i18n_translations (scope, `key`, lang, value) VALUES (?, ?, ?, ?)");

          $defaultLang = i18n_default_lang(); // ej. 'es'
          $otherLangs = array_diff(i18n_langs(), [$defaultLang]); // ej. ['en']

          foreach ($termsMap as $key => $fallbackValue) {
              // Comprobar idioma por defecto
              $st_check->execute([$scope, $key, $defaultLang]);
              if (!$st_check->fetchColumn()) {
                   $guessedValue = ($defaultLang === 'es') ? i18n_guess_es($fallbackValue) : $fallbackValue;
                   $st_insert->execute([$scope, $key, $defaultLang, $guessedValue]);
              }
              // Comprobar otros idiomas (se inserta el fallback, necesita traducción)
              foreach ($otherLangs as $lang) {
                   $st_check->execute([$scope, $key, $lang]);
                   if (!$st_check->fetchColumn()) {
                       // Insertar el fallback original (en inglés usualmente) para ser traducido después
                       $st_insert->execute([$scope, $key, $lang, $fallbackValue]);
                   }
              }
          }
      } catch (Throwable $e) {
           error_log("Error seeding i18n: " . $e->getMessage());
      }
  }
}
if (!function_exists('i18n_set_lang_from_request')) {
  /**
   * Comprueba si se ha pasado un parámetro 'lang' en GET o POST
   * y llama a i18n_set_lang() si es un idioma válido.
   */
  function i18n_set_lang_from_request(): void {
    $requested_lang = null;
    if (!empty($_GET['lang'])) {
      $requested_lang = $_GET['lang'];
    } elseif (!empty($_POST['lang'])) {
      $requested_lang = $_POST['lang'];
    }

    if ($requested_lang) {
      $lang_code = strtolower(substr((string)$requested_lang, 0, 2));
      if (in_array($lang_code, i18n_langs(), true)) {
        i18n_set_lang($lang_code); // Llama a la función existente para guardar el idioma
      }
    }
  }
}
?>