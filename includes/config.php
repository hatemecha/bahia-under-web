<?php
/**
 * Configuración consolidada de la aplicación
 * 
 * Este archivo centraliza TODA la configuración de la aplicación,
 * incluyendo base de datos, seguridad, logging, etc.
 * 
 * Orden de prioridad para variables:
 * 1. Variables de entorno del sistema (getenv)
 * 2. Archivo .env en la raíz del proyecto
 * 3. Valores por defecto
 */

// Función helper para cargar .env
function load_env_file($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        if (empty($line) || strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear línea KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Solo establecer si no existe ya en el entorno del sistema
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    return true;
}

// Cargar .env desde la raíz del proyecto
$env_loaded = load_env_file(__DIR__ . '/../.env');

// Configuración de base de datos desde variables de entorno, por si acaso esta la tipica de XAMPP. Creo que tengo esto duplicado pero meh.
$DB_HOST = $_ENV['DB_HOST'] ?? '127.0.0.1';
$DB_NAME = $_ENV['DB_NAME'] ?? 'bahia_under';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_CHAR = $_ENV['DB_CHAR'] ?? 'utf8mb4';

// Configuración de seguridad
$APP_ENV = $_ENV['APP_ENV'] ?? 'development';
$APP_DEBUG = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$APP_URL = $_ENV['APP_URL'] ?? 'http://localhost';

// Configuración de encriptación
$APP_KEY = $_ENV['APP_KEY'] ?? bin2hex(random_bytes(32));
$ENCRYPTION_KEY = $_ENV['ENCRYPTION_KEY'] ?? bin2hex(random_bytes(32));

// Configuración de sesiones
$SESSION_LIFETIME = (int)($_ENV['SESSION_LIFETIME'] ?? 0);
$SESSION_SECURE = filter_var($_ENV['SESSION_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$SESSION_HTTPONLY = filter_var($_ENV['SESSION_HTTPONLY'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$SESSION_SAMESITE = $_ENV['SESSION_SAMESITE'] ?? 'Strict';

// Configuración de logs
$LOG_LEVEL = $_ENV['LOG_LEVEL'] ?? 'info';
$LOG_CHANNEL = $_ENV['LOG_CHANNEL'] ?? 'file';

// Configuración de archivos
$UPLOAD_MAX_SIZE = (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880); // 5MB
$UPLOAD_ALLOWED_TYPES = explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'jpg,jpeg,png,gif,webp,mp3,wav,flac');

// Configuración de rate limiting
$RATE_LIMIT_ENABLED = filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$RATE_LIMIT_MAX_ATTEMPTS = (int)($_ENV['RATE_LIMIT_MAX_ATTEMPTS'] ?? 10);
$RATE_LIMIT_WINDOW = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 300);

// Configuración de seguridad adicional
$FORCE_HTTPS = filter_var($_ENV['FORCE_HTTPS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$CSP_ENABLED = filter_var($_ENV['CSP_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$HSTS_ENABLED = filter_var($_ENV['HSTS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

// Configuración de base de datos con SSL
$DB_SSL = filter_var($_ENV['DB_SSL'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$DB_SSL_CA = $_ENV['DB_SSL_CA'] ?? null;
$DB_SSL_CERT = $_ENV['DB_SSL_CERT'] ?? null;
$DB_SSL_KEY = $_ENV['DB_SSL_KEY'] ?? null;

// Configuración de backup
$BACKUP_ENABLED = filter_var($_ENV['BACKUP_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$BACKUP_RETENTION_DAYS = (int)($_ENV['BACKUP_RETENTION_DAYS'] ?? 30);

// Configuración de monitoreo
$MONITORING_ENABLED = filter_var($_ENV['MONITORING_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$MONITORING_ENDPOINT = $_ENV['MONITORING_ENDPOINT'] ?? null;

// Validar configuración crítica
if (empty($DB_PASS) && $APP_ENV === 'production') {
    throw new Exception('DB_PASS debe estar configurado en producción');
}

if (empty($APP_KEY) && $APP_ENV === 'production') {
    throw new Exception('APP_KEY debe estar configurado en producción');
}

if (empty($ENCRYPTION_KEY) && $APP_ENV === 'production') {
    throw new Exception('ENCRYPTION_KEY debe estar configurado en producción');
}

// Configurar zona horaria
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Argentina/Buenos_Aires');

// Configurar nivel de error según el entorno
if ($APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/app.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/app.log');
}


// Marcar que la configuración fue cargada
define('CONFIG_LOADED', true);
