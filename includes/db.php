<?php
/**
 * Conexión a la base de datos
 * 
 * Creo la conexión PDO usando la configuración de config.php
 * 
 * config.php debe ser incluido PRIMERO
 * Las variables $DB_HOST, $DB_NAME, etc. deben estar definidas por config.php
 */

// Verificar que las variables de configuración existen
if (!isset($DB_HOST) || !isset($DB_NAME) || !isset($DB_USER)) {
    // Si no existen, intentar cargar desde config.php
    if (!defined('CONFIG_LOADED')) {
        require_once __DIR__ . '/config.php';
    }
}

// Validación de configuración requerida
if (!isset($DB_HOST) || !isset($DB_NAME)) {
    throw new RuntimeException('Falta configuración de la base de datos. Asegurate de que config.php este incluido primero.');
}

// Establecer valores por defecto si no están definidos
$DB_PASS = $DB_PASS ?? '';
$DB_CHAR = $DB_CHAR ?? 'utf8mb4';

// Configuración de conexión
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', time_zone = '+00:00'"
];

try {
    $GLOBALS['pdo'] = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    
    // Verificar que la conexión funciona
    $GLOBALS['pdo']->query("SELECT 1");
    
    // Asignar también a variable local para compatibilidad
    $pdo = $GLOBALS['pdo'];
    
} catch (PDOException $e) {
    // Log del error
    if (function_exists('devlog')) {
        devlog('DB connection failed', [
            'host' => $DB_HOST,
            'database' => $DB_NAME,
            'user' => $DB_USER,
            'error' => $e->getMessage(),
            'dsn' => $dsn
        ]);
    }
    
    // Definir $pdo como null en caso de error
    $GLOBALS['pdo'] = null;
    $pdo = null;
}
