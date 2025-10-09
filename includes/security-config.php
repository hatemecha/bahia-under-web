<?php
/**
 * Configuración de seguridad para la aplicación
 */

// Configuración de headers de seguridad mejorada
function set_security_headers() {
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar XSS protection del navegador
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions Policy (anteriormente Feature Policy)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()');
    
    // Content Security Policy mejorado pero flexible
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " . // unsafe-eval para compatibilidad
           "style-src 'self' 'unsafe-inline' https:; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https: data:; " .
           "connect-src 'self'; " .
           "media-src 'self' blob: data:; " .
           "object-src 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self'; " .
           "frame-ancestors 'none'; " .
           "upgrade-insecure-requests;";
    header("Content-Security-Policy: $csp");
    
    // HSTS si estamos en HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    // Cache control para páginas sensibles
    if (!empty($_SESSION['uid'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

// Configuración de sesión segura
function configure_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configuración de cookies de sesión seguras
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']), // Solo HTTPS en producción
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_name('ugb_session');
        session_start();
        
        // Regenerar ID de sesión periódicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Función auxiliar para obtener conexión PDO
function get_pdo_connection() {
    static $pdo = null;
    if ($pdo === null) {
        require_once __DIR__ . '/db.php';
        $pdo = $GLOBALS['pdo'] ?? null;
    }
    return $pdo;
}

// Validación de CSRF Token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    // Verificar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    // Verificar que el token existe en la sesión
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Verificar que el token enviado no esté vacío
    if (empty($token)) {
        return false;
    }
    
    // Comparar tokens de forma segura
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sistema de autenticación mejorado
function authenticate_user($login, $password) {
    // Obtener conexión PDO
    $pdo = get_pdo_connection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Rate limiting
    if (!check_rate_limit('login', 5, 900)) { // 5 intentos en 15 minutos
        throw new Exception('Demasiados intentos de login. Intenta más tarde.');
    }
    
    // Validar entrada
    if (empty($login) || empty($password)) {
        throw new Exception('Usuario y contraseña son requeridos');
    }
    
    try {
        // Buscar usuario
        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, role, status, 
                   failed_login_attempts, last_failed_login
            FROM users 
            WHERE (email = :login1 OR username = :login2) 
            AND status IN ('active', 'pending')
            LIMIT 1
        ");
        $stmt->execute(['login1' => $login, 'login2' => $login]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Usar password_verify para evitar timing attacks
            password_verify($password, '$2y$10$dummyhash');
            throw new Exception('Credenciales inválidas');
        }
        
        // Verificar si el usuario está bloqueado por intentos fallidos
        if ($user['failed_login_attempts'] >= 5 && 
            $user['last_failed_login'] && 
            strtotime($user['last_failed_login']) > (time() - 900)) { // 15 minutos
            throw new Exception('Cuenta temporalmente bloqueada por intentos fallidos');
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            // Incrementar contador de intentos fallidos
            $stmt = $pdo->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    last_failed_login = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $user['id']]);
            
            throw new Exception('Credenciales inválidas');
        }
        
        // Resetear contador de intentos fallidos
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, last_failed_login = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => $user['id']]);
        
        return $user;
        
    } catch (PDOException $e) {
        // Log del error de base de datos
        if (function_exists('devlog')) {
            devlog('auth_db_error', [
                'login' => $login,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
        throw new Exception('Error de base de datos: ' . $e->getMessage());
    }
}

// Sistema de autorización mejorado
function require_auth($redirect_to = 'login.php') {
    if (empty($_SESSION['uid'])) {
        header("Location: $redirect_to");
        exit;
    }
}

function require_role($allowed_roles, $redirect_to = 'index.php') {
    require_auth();
    
    $user_role = $_SESSION['role'] ?? 'user';
    if (!in_array($user_role, (array)$allowed_roles, true)) {
        http_response_code(403);
        die('Acceso denegado. Permisos insuficientes.');
    }
}

function require_ownership($resource_user_id, $redirect_to = 'index.php') {
    require_auth();
    
    $current_user_id = (int)($_SESSION['uid'] ?? 0);
    $user_role = $_SESSION['role'] ?? 'user';
    
    // Admin y mod pueden acceder a todo
    if (in_array($user_role, ['admin', 'mod'], true)) {
        return true;
    }
    
    // Verificar ownership
    if ($current_user_id !== (int)$resource_user_id) {
        http_response_code(403);
        die('No tienes permisos para acceder a este recurso.');
    }
    
    return true;
}

// Gestión segura de sesiones - FUNCIÓN CENTRALIZADA
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configuración unificada y segura
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $is_production = ($_ENV['APP_ENV'] ?? 'development') === 'production';
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $is_https || $is_production, // true en HTTPS o producción
            'httponly' => true,
            'samesite' => 'Lax' // Cambiado a Lax para mejor compatibilidad
        ]);
        
        session_name('ugb_session');
        session_start();
        
        // Regenerar ID periódicamente para mayor seguridad
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Asegurar que siempre hay un token CSRF
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

// Gestión segura de tokens remember
function create_remember_token($user_id) {
    // Obtener conexión PDO
    $pdo = get_pdo_connection();
    
    // Generar token seguro
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    
    // Obtener información del cliente
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Calcular expiración
    $expires = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO user_tokens (user_id, token_hash, user_agent, ip, expires_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $hash, $user_agent, $ip, $expires]);
    
    // Establecer cookie
    setcookie('remember', $token, [
        'expires' => time() + (30 * 24 * 60 * 60), // 30 días
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    return $token;
}

function validate_remember_token($token) {
    // Obtener conexión PDO
    $pdo = get_pdo_connection();
    
    if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    
    $hash = hash('sha256', $token);
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT t.user_id, u.username, u.role, u.status
        FROM user_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? 
        AND t.user_agent = ?
        AND t.ip = ?
        AND t.expires_at > UTC_TIMESTAMP()
        AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$hash, $user_agent, $ip]);
    
    return $stmt->fetch();
}

function invalidate_remember_tokens($user_id) {
    // Obtener conexión PDO
    $pdo = get_pdo_connection();
    
    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// Logging de seguridad (usar el nuevo sistema)
function log_security_event($event, $details = []) {
    // Usar el nuevo sistema de logging seguro
    if (function_exists('log_security')) {
        log_security("Security event: $event", $details);
    } else {
        // Fallback al sistema anterior
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['uid'] ?? null,
            'event' => $event,
            'details' => $details
        ];
        
        error_log('SECURITY: ' . json_encode($log_entry));
    }
}

// Rate limiting básico
function check_rate_limit($action, $max_attempts = 10, $time_window = 300) {
    $key = "rate_limit_{$action}_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $rate_data = $_SESSION[$key];
    
    // Reset si ha pasado el tiempo
    if (time() - $rate_data['first_attempt'] > $time_window) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Verificar límite
    if ($rate_data['count'] >= $max_attempts) {
        return false;
    }
    
    // Incrementar contador
    $_SESSION[$key]['count']++;
    return true;
}

// Sanitización de archivos subidos
function sanitize_uploaded_file($file) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'flac'];
    $allowed_mime_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/flac', 'audio/x-flac'
    ];
    
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions, true)) {
        return false;
    }
    
    // Verificar MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    if (!in_array($mime_type, $allowed_mime_types, true)) {
        return false;
    }
    
    // Verificar tamaño (5MB por defecto)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    
    return true;
}


// Validación de entrada estricta
function validate_strict_input($input, $type, $options = []) {
    switch ($type) {
        case 'int':
            $value = filter_var($input, FILTER_VALIDATE_INT, $options);
            return $value !== false ? $value : null;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ?: null;
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) ?: null;
            
        case 'alphanumeric':
            return preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : null;
            
        case 'username':
            return preg_match('/^[a-z0-9_]{3,30}$/', $input) ? $input : null;
            
        default:
            return sanitize_input($input);
    }
}
