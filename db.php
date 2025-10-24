<?php
// db.php - Conexión a la base de datos usando PDO

// 1. Incluir el archivo de configuración donde están las constantes DB_HOST, DB_USER, etc.
//    Asegúrate de que config.php exista en el mismo directorio o ajusta la ruta.
require_once __DIR__ . '/config.php';

// 2. Comprobar si las constantes necesarias están definidas (medida de seguridad)
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    die("Error: Faltan constantes de configuración de base de datos en config.php.");
}

// Opciones de PDO para mejor manejo de errores y codificación
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanzar excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devolver arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usar preparaciones nativas
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'      // Asegurar UTF-8 en la conexión
];

// 3. Construir el DSN (Data Source Name) usando las constantes de config.php
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

try {
    // 4. Crear la instancia de PDO usando las constantes
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (\PDOException $e) {
    // Manejar error de conexión
    error_log("Error de conexión PDO: " . $e->getMessage()); // Registrar el error detallado
    // Mostrar mensaje genérico al usuario en un entorno de producción
    die("Error de conexión a la base de datos. Por favor, intente más tarde o contacte al administrador."); 
}

// La variable $pdo ahora está disponible globalmente para los scripts que incluyan db.php
?>