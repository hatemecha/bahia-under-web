<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

$dev_dir = __DIR__ . '/../dev';
if (!is_dir($dev_dir)) { @mkdir($dev_dir, 0775, true); }

// Log **con nombre exacto** solicitado:
define('DEV_LOG', $dev_dir . '/error_log.php');

/**
 * Protegemos el archivo de log para que, si alguien lo abre por web,
 * no muestre contenido. La primera línea será `<?php exit; ?>` y luego
 * PHP seguirá escribiendo texto plano debajo (no ejecutable).
 */
if (!file_exists(DEV_LOG)) {
  @file_put_contents(DEV_LOG, "<?php exit; ?>\n", LOCK_EX);
}

// Enviamos los errores de PHP a DEV_LOG
ini_set('log_errors', '1');
ini_set('error_log', DEV_LOG);

/**
 * Logger seguro que sanitiza datos sensibles automáticamente
 * 
 * Esta función ahora usa el sistema SecureLogger para prevenir
 * la exposición de información sensible en los logs.
 * 
 * Campos automáticamente redactados:
 * - password, password_hash, token, secret, key, hash
 * - email, phone, ssn, credit_card, cvv, pin
 * - session_id, remember_token, api_key, access_token
 * 
 * @param string $msg Mensaje de log
 * @param array $ctx Contexto adicional (se sanitizara)
 */
function devlog(string $msg, array $ctx = []): void {
  // Lista de campos sensibles que deben ser redactados
  static $sensitive_fields = [
    'password', 'password_hash', 'passwd', 'pwd', 'pass',
    'token', 'secret', 'key', 'hash', 'salt',
    'email', 'phone', 'ssn', 'credit_card', 'card_number', 'cvv', 'pin',
    'session_id', 'remember_token', 'api_key', 'access_token', 'refresh_token',
    'authorization', 'auth', 'cookie', 'csrf_token'
  ];
  
  // Sanitizar contexto
  $sanitized_ctx = sanitize_log_data($ctx, $sensitive_fields);
  
  // Formatear línea de log
  $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
  if ($sanitized_ctx) {
    $line .= ' ' . json_encode($sanitized_ctx, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
  
  // Escribir al log
  error_log($line);
}

/**
 * Sanitiza datos para logging, redactando información sensible
 * 
 * @param mixed $data Datos a sanitizar
 * @param array $sensitive_fields Lista de campos sensibles
 * @return mixed Datos sanitizados
 */
function sanitize_log_data($data, array $sensitive_fields) {
  if (is_array($data)) {
    $sanitized = [];
    foreach ($data as $key => $value) {
      $key_lower = strtolower((string)$key);
      
      // Verificar si el campo es sensible
      $is_sensitive = false;
      foreach ($sensitive_fields as $field) {
        if (strpos($key_lower, $field) !== false) {
          $is_sensitive = true;
          break;
        }
      }
      
      if ($is_sensitive) {
        // Redactar campo sensible
        $sanitized[$key] = '[REDACTED]';
      } elseif (is_array($value)) {
        // Recursivamente sanitizar arrays anidados
        $sanitized[$key] = sanitize_log_data($value, $sensitive_fields);
      } elseif (is_object($value)) {
        // Convertir objetos a arrays y sanitizar
        $sanitized[$key] = sanitize_log_data((array)$value, $sensitive_fields);
      } elseif (is_string($value) && strlen($value) > 200) {
        // Truncar strings largos
        $sanitized[$key] = substr($value, 0, 200) . '... [TRUNCATED]';
      } else {
        $sanitized[$key] = $value;
      }
    }
    return $sanitized;
  }
  
  if (is_object($data)) {
    return sanitize_log_data((array)$data, $sensitive_fields);
  }
  
  if (is_string($data) && strlen($data) > 200) {
    return substr($data, 0, 200) . '... [TRUNCATED]';
  }
  
  return $data;
}
