<?php
/**
 * Configuración optimizada de logging
 * Solo registra actividades importantes y errores críticos
 */

// Configuración de niveles de log
define('LOG_LEVEL_DEBUG', 0);
define('LOG_LEVEL_INFO', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_ERROR', 3);
define('LOG_LEVEL_CRITICAL', 4);

// Nivel mínimo de log (solo ERROR y CRITICAL en producción)
$minLogLevel = ($_ENV['APP_ENV'] ?? 'development') === 'production' ? LOG_LEVEL_ERROR : LOG_LEVEL_INFO;

/**
 * Función optimizada de logging que solo registra actividades importantes
 */
function log_important($message, $context = [], $level = LOG_LEVEL_INFO) {
    global $minLogLevel;
    
    // Solo registrar si el nivel es suficiente
    if ($level < $minLogLevel) {
        return;
    }
    
    // Actividades importantes que SI deben registrarse
    $importantActivities = [
        'login_success', 'login_failed', 'logout',
        'user_registration', 'user_activation', 'user_deactivation',
        'password_change', 'password_reset',
        'role_change', 'status_change',
        'file_upload', 'file_delete',
        'admin_action', 'mod_action',
        'security_violation', 'unauthorized_access',
        'database_error', 'system_error',
        'backup_created', 'backup_failed',
        'config_change', 'system_maintenance'
    ];
    
    // Verificar si es una actividad importante
    $isImportant = false;
    foreach ($importantActivities as $activity) {
        if (strpos($message, $activity) !== false) {
            $isImportant = true;
            break;
        }
    }
    
    // Solo registrar actividades importantes o errores
    if (!$isImportant && $level < LOG_LEVEL_ERROR) {
        return;
    }
    
    // Usar el sistema de logging existente
    if (function_exists('devlog')) {
        $levelNames = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
        $logMessage = '[' . $levelNames[$level] . '] ' . $message;
        devlog($logMessage, $context);
    }
}

/**
 * Logging específico para actividades de usuario importantes
 */
function log_user_activity($action, $user_id = null, $context = []) {
    $user_id = $user_id ?? ($_SESSION['uid'] ?? null);
    
    $importantUserActions = [
        'login', 'logout', 'register', 'activate', 'deactivate',
        'password_change', 'password_reset', 'email_change',
        'role_change', 'status_change', 'profile_update'
    ];
    
    if (in_array($action, $importantUserActions)) {
        log_important("user_$action", array_merge([
            'user_id' => $user_id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
        ], $context), LOG_LEVEL_INFO);
    }
}

/**
 * Logging específico para actividades de administración
 */
function log_admin_activity($action, $target_id = null, $context = []) {
    $admin_id = $_SESSION['uid'] ?? null;
    
    log_important("admin_$action", array_merge([
        'admin_id' => $admin_id,
        'target_id' => $target_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
    ], $context), LOG_LEVEL_INFO);
}

/**
 * Logging específico para actividades de moderación
 */
function log_mod_activity($action, $target_id = null, $context = []) {
    $mod_id = $_SESSION['uid'] ?? null;
    
    log_important("mod_$action", array_merge([
        'mod_id' => $mod_id,
        'target_id' => $target_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
    ], $context), LOG_LEVEL_INFO);
}

/**
 * Logging específico para errores del sistema
 */
function log_system_error($component, $error, $context = []) {
    log_important("system_error", array_merge([
        'component' => $component,
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ], $context), LOG_LEVEL_ERROR);
}

/**
 * Logging específico para violaciones de seguridad
 */
function log_security_violation($violation_type, $context = []) {
    log_important("security_violation", array_merge([
        'violation_type' => $violation_type,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
    ], $context), LOG_LEVEL_WARNING);
}

/**
 * Logging específico para operaciones de archivos
 */
function log_file_operation($operation, $filename = null, $context = []) {
    $importantFileOps = ['upload', 'delete', 'download', 'backup'];
    
    if (in_array($operation, $importantFileOps)) {
        log_important("file_$operation", array_merge([
            'filename' => $filename,
            'user_id' => $_SESSION['uid'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $context), LOG_LEVEL_INFO);
    }
}

/**
 * Logging específico para cambios de configuración
 */
function log_config_change($config_key, $old_value, $new_value, $context = []) {
    log_important("config_change", array_merge([
        'config_key' => $config_key,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'user_id' => $_SESSION['uid'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ], $context), LOG_LEVEL_INFO);
}

/**
 * Función para limpiar logs antiguos automáticamente
 */
function cleanup_old_logs($days = 30) {
    $logFiles = [
        __DIR__ . '/../logs/app.log',
        __DIR__ . '/../logs/secure.log',
        __DIR__ . '/../dev/error_log.php',
        __DIR__ . '/../dev/debug.log'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile) && filemtime($logFile) < (time() - ($days * 24 * 60 * 60))) {
            // Rotar el archivo de log
            $backupFile = $logFile . '.' . date('Y-m-d-H-i-s');
            rename($logFile, $backupFile);
            
            // Crear nuevo archivo de log
            if (strpos($logFile, '.php') !== false) {
                file_put_contents($logFile, "<?php exit; ?>\n", LOCK_EX);
            } else {
                file_put_contents($logFile, "", LOCK_EX);
            }
            
            log_important("log_rotated", [
                'log_file' => basename($logFile),
                'backup_file' => basename($backupFile)
            ], LOG_LEVEL_INFO);
        }
    }
}

// Limpiar logs antiguos si es necesario (solo una vez por día)
$lastCleanup = $_SESSION['last_log_cleanup'] ?? 0;
if (time() - $lastCleanup > 86400) { // 24 horas
    cleanup_old_logs();
    $_SESSION['last_log_cleanup'] = time();
}
?>
