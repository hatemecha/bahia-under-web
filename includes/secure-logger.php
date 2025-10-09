<?php
/**
 * Sistema de logging seguro que no expone información sensible
 */

class SecureLogger {
    private $logFile;
    private $logLevel;
    private $sensitive_fields = [
        'password', 'password_hash', 'token', 'secret', 'key', 'hash',
        'email', 'phone', 'ssn', 'credit_card', 'cvv', 'pin',
        'session_id', 'remember_token', 'api_key', 'access_token'
    ];
    
    public function __construct($logFile = null, $logLevel = 'info') {
        $this->logFile = $logFile ?: (__DIR__ . '/../logs/secure.log');
        $this->logLevel = $logLevel;
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        // Proteger archivo de log
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, "<?php exit; ?>\n", LOCK_EX);
        }
    }
    
    /**
     * Sanitizar datos para logging
     */
    private function sanitizeData($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $key_lower = strtolower($key);
                
                // Verificar si el campo es sensible
                $is_sensitive = false;
                foreach ($this->sensitive_fields as $field) {
                    if (strpos($key_lower, $field) !== false) {
                        $is_sensitive = true;
                        break;
                    }
                }
                
                if ($is_sensitive) {
                    $sanitized[$key] = '[REDACTED]';
                } elseif (is_array($value)) {
                    $sanitized[$key] = $this->sanitizeData($value);
                } elseif (is_string($value) && strlen($value) > 100) {
                    $sanitized[$key] = substr($value, 0, 100) . '...';
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        }
        
        if (is_string($data) && strlen($data) > 100) {
            return substr($data, 0, 100) . '...';
        }
        
        return $data;
    }
    
    /**
     * Escribir log de seguridad
     */
    public function security($message, $context = []) {
        $this->writeLog('SECURITY', $message, $context);
    }
    
    /**
     * Escribir log de error
     */
    public function error($message, $context = []) {
        $this->writeLog('ERROR', $message, $context);
    }
    
    /**
     * Escribir log de advertencia
     */
    public function warning($message, $context = []) {
        $this->writeLog('WARNING', $message, $context);
    }
    
    /**
     * Escribir log de información
     */
    public function info($message, $context = []) {
        $this->writeLog('INFO', $message, $context);
    }
    
    /**
     * Escribir log de debug
     */
    public function debug($message, $context = []) {
        if ($this->logLevel === 'debug') {
            $this->writeLog('DEBUG', $message, $context);
        }
    }
    
    /**
     * Escribir log de auditoría
     */
    public function audit($action, $user_id = null, $context = []) {
        $auditContext = array_merge([
            'action' => $action,
            'user_id' => $user_id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('AUDIT', "User action: $action", $auditContext);
    }
    
    /**
     * Escribir log de acceso
     */
    public function access($resource, $action, $user_id = null, $context = []) {
        $accessContext = array_merge([
            'resource' => $resource,
            'action' => $action,
            'user_id' => $user_id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('ACCESS', "Access to $resource: $action", $accessContext);
    }
    
    /**
     * Escribir log de datos
     */
    public function data($operation, $table = null, $record_id = null, $context = []) {
        $dataContext = array_merge([
            'operation' => $operation,
            'table' => $table,
            'record_id' => $record_id,
            'user_id' => $_SESSION['uid'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('DATA', "Data operation: $operation", $dataContext);
    }
    
    /**
     * Escribir log de sistema
     */
    public function system($component, $message, $context = []) {
        $systemContext = array_merge([
            'component' => $component,
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('SYSTEM', "[$component] $message", $systemContext);
    }
    
    /**
     * Escribir log de rendimiento
     */
    public function performance($operation, $duration, $context = []) {
        $perfContext = array_merge([
            'operation' => $operation,
            'duration_ms' => $duration,
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('PERFORMANCE', "Performance: $operation", $perfContext);
    }
    
    /**
     * Escribir log de excepción
     */
    public function exception($exception, $context = []) {
        $exceptionContext = array_merge([
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => $_SESSION['uid'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        $this->writeLog('EXCEPTION', "Exception: " . $exception->getMessage(), $exceptionContext);
    }
    
    /**
     * Escribir log al archivo
     */
    private function writeLog($level, $message, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $this->sanitizeData($context)
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        
        // Escribir al archivo de log
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // También escribir al log de PHP si es un error crítico
        if (in_array($level, ['ERROR', 'EXCEPTION', 'SECURITY'])) {
            error_log("[$level] $message - " . json_encode($context));
        }
    }
    
    /**
     * Rotar logs (llamar periódicamente)
     */
    public function rotateLogs($maxSize = 10485760) { // 10MB
        if (file_exists($this->logFile) && filesize($this->logFile) > $maxSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
            rename($this->logFile, $backupFile);
            
            // Crear nuevo archivo de log
            file_put_contents($this->logFile, "<?php exit; ?>\n", LOCK_EX);
            
            // Comprimir archivo anterior
            if (function_exists('gzopen')) {
                $gz = gzopen($backupFile . '.gz', 'w9');
                gzwrite($gz, file_get_contents($backupFile));
                gzclose($gz);
                unlink($backupFile);
            }
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs($days = 30) {
        $logDir = dirname($this->logFile);
        $files = glob($logDir . '/*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < (time() - ($days * 24 * 60 * 60))) {
                unlink($file);
            }
        }
    }
}

// Instancia global del logger
$secureLogger = new SecureLogger();

// Funciones de conveniencia
function log_security($message, $context = []) {
    global $secureLogger;
    $secureLogger->security($message, $context);
}

function log_error($message, $context = []) {
    global $secureLogger;
    $secureLogger->error($message, $context);
}

function log_warning($message, $context = []) {
    global $secureLogger;
    $secureLogger->warning($message, $context);
}

function log_info($message, $context = []) {
    global $secureLogger;
    $secureLogger->info($message, $context);
}

function log_debug($message, $context = []) {
    global $secureLogger;
    $secureLogger->debug($message, $context);
}

function log_audit($action, $user_id = null, $context = []) {
    global $secureLogger;
    $secureLogger->audit($action, $user_id, $context);
}

function log_access($resource, $action, $user_id = null, $context = []) {
    global $secureLogger;
    $secureLogger->access($resource, $action, $user_id, $context);
}

function log_data($operation, $table = null, $record_id = null, $context = []) {
    global $secureLogger;
    $secureLogger->data($operation, $table, $record_id, $context);
}

function log_system($component, $message, $context = []) {
    global $secureLogger;
    $secureLogger->system($component, $message, $context);
}

function log_performance($operation, $duration, $context = []) {
    global $secureLogger;
    $secureLogger->performance($operation, $duration, $context);
}

function log_exception($exception, $context = []) {
    global $secureLogger;
    $secureLogger->exception($exception, $context);
}
