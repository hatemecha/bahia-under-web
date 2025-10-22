<?php
/**
 * Configuración centralizada de cache para la aplicación
 */

// Función para establecer headers de cache según el tipo de contenido
function set_cache_headers($type = 'dynamic', $max_age = 0) {
    switch ($type) {
        case 'static':
            // Archivos estáticos (CSS, JS, imágenes)
            header('Cache-Control: public, max-age=2592000'); // 30 días
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
            break;
            
        case 'dynamic':
            // Contenido dinámico (páginas PHP)
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            break;
            
        case 'ajax':
            // Respuestas AJAX
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            break;
            
        case 'api':
            // APIs y JSON
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            break;
            
        case 'media':
            // Archivos de media (audio, video)
            header('Cache-Control: public, max-age=15552000'); // 6 meses
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 15552000) . ' GMT');
            break;
            
        case 'custom':
            // Cache personalizado
            if ($max_age > 0) {
                header("Cache-Control: public, max-age=$max_age");
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
            } else {
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
            break;
    }
}

// Función para agregar versioning a archivos estáticos
function add_version_to_static_file($file_path, $version = null) {
    if ($version === null) {
        $version = filemtime($file_path) ?: time();
    }
    return $file_path . '?v=' . $version;
}

// Función para verificar si el contenido ha cambiado (ETag)
function set_etag_header($content) {
    $etag = '"' . md5($content) . '"';
    header('ETag: ' . $etag);
    
    // Verificar si el cliente tiene la versión más reciente
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        http_response_code(304);
        exit;
    }
}

// Función para establecer headers de Last-Modified
function set_last_modified_header($timestamp) {
    $last_modified = gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    header('Last-Modified: ' . $last_modified);
    
    // Verificar si el cliente tiene la versión más reciente
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $if_modified_since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($if_modified_since && $if_modified_since >= $timestamp) {
            http_response_code(304);
            exit;
        }
    }
}

// Función para cache de contenido dinámico con condiciones
function set_conditional_cache_headers($last_modified = null, $etag = null) {
    if ($last_modified) {
        set_last_modified_header($last_modified);
    }
    
    if ($etag) {
        set_etag_header($etag);
    }
    
    // Cache por 1 hora para contenido que puede cambiar
    header('Cache-Control: public, max-age=3600');
}

// Función para limpiar cache del navegador (forzar recarga)
function force_no_cache() {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// Función para establecer Vary header apropiado
function set_vary_header($vary_values = []) {
    if (empty($vary_values)) {
        $vary_values = ['Accept-Encoding'];
    }
    
    if (!empty($_SESSION['uid'])) {
        $vary_values[] = 'Cookie';
    }
    
    header('Vary: ' . implode(', ', $vary_values));
}
