<?php
/**
 * Archivo de inicialización centralizada
 * Incluir este archivo para no tener que incluir cada uno. 
 */

// Incluir dependencias básicas
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dev.php';
require_once __DIR__ . '/security-config.php';
require_once __DIR__ . '/secure-logger.php';
require_once __DIR__ . '/logging-config.php';

// Iniciar sesión segura de forma centralizada
start_secure_session();

// Headers de seguridad
if (function_exists('set_security_headers')) {
    set_security_headers();
}
