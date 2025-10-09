<?php
/**
 * Funciones de seguridad para prevenir vulnerabilidades de inyección
 */

/**
 * Sanitiza entrada de usuario para prevenir XSS
 */
function sanitize_input($input, $max_length = null) {
    if ($input === null) return null;
    
    $input = trim($input);
    if ($max_length && strlen($input) > $max_length) {
        $input = substr($input, 0, $max_length);
    }
    
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Valida y sanitiza parámetros de ordenamiento para prevenir inyección SQL
 */
function validate_order_param($order, $allowed_orders) {
    if (!is_string($order) || !array_key_exists($order, $allowed_orders)) {
        return array_key_first($allowed_orders); // Retorna el primer valor por defecto
    }
    return $order;
}

/**
 * Valida parámetros de paginación
 */
function validate_pagination($page, $per_page = 12, $max_per_page = 100) {
    $page = max(1, (int)$page);
    $per_page = max(1, min((int)$per_page, $max_per_page));
    return [$page, $per_page];
}

/**
 * Valida IDs numéricos
 */
function validate_id($id, $min = 1) {
    $id = (int)$id;
    return $id >= $min ? $id : 0;
}

/**
 * Valida email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Sanitiza contenido de texto para mostrar (mantiene saltos de línea)
 */
function sanitize_text_display($text) {
    return nl2br(sanitize_input($text));
}

/**
 * Valida tipo de archivo subido con verificaciones robustas
 */
function validate_uploaded_file($file, $allowed_types, $max_size = 5242880) { // 5MB por defecto
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Verificar que el archivo temporal existe y es legible
    if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        return false;
    }
    
    // Verificar MIME type real del contenido
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types, true)) {
        return false;
    }
    
    // Verificación adicional para imágenes
    if (strpos($mime_type, 'image/') === 0) {
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            return false; // No es una imagen válida
        }
        
        // Verificar dimensiones máximas (prevenir ataques de memoria)
        if ($image_info[0] > 5000 || $image_info[1] > 5000) {
            return false;
        }
        
        // Verificar que no contiene código PHP disfrazado
        if (detect_malicious_content($file['tmp_name'])) {
            log_security_event('malicious_file_upload_blocked', [
                'filename' => $file['name'] ?? 'unknown',
                'mime_type' => $mime_type
            ]);
            return false;
        }
    }
    
    // Verificación adicional para archivos de audio
    if (strpos($mime_type, 'audio/') === 0) {
        // Verificar que el archivo tiene contenido de audio válido
        $handle = fopen($file['tmp_name'], 'rb');
        if ($handle) {
            $header = fread($handle, 12);
            fclose($handle);
            
            // Verificar headers de archivos de audio comunes
            $audio_headers = [
                'ID3' => substr($header, 0, 3), // MP3 con ID3
                'ÿû' => substr($header, 0, 2),  // MP3 sin ID3
                'RIFF' => substr($header, 0, 4), // WAV
                'fLaC' => substr($header, 4, 4)  // FLAC
            ];
            
            $is_valid_audio = false;
            foreach ($audio_headers as $header_check) {
                if (strpos($header, $header_check) !== false) {
                    $is_valid_audio = true;
                    break;
                }
            }
            
            if (!$is_valid_audio) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Genera nombre de archivo seguro con hash único
 */
function generate_safe_filename($original_name, $prefix = '') {
    $path_info = pathinfo($original_name);
    $filename = $path_info['filename'] ?? 'file';
    $extension = $path_info['extension'] ?? '';
    
    // Sanitizar nombre del archivo
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename); // Reemplazar múltiples _ con uno solo
    $filename = trim($filename, '._-');
    
    if (empty($filename)) {
        $filename = 'file';
    }
    
    // Generar hash único para evitar colisiones y hacer nombres impredecibles
    $unique_hash = bin2hex(random_bytes(8));
    $filename = $filename . '_' . $unique_hash;
    
    if ($prefix) {
        $filename = $prefix . '_' . $filename;
    }
    
    return $filename . ($extension ? '.' . $extension : '');
}

/**
 * Valida roles de usuario
 */
function validate_user_role($role, $allowed_roles = ['user', 'artist', 'mod', 'admin']) {
    return in_array($role, $allowed_roles, true);
}

/**
 * Valida estado de contenido
 */
function validate_content_status($status, $allowed_statuses = ['draft', 'pending', 'published', 'rejected']) {
    return in_array($status, $allowed_statuses, true);
}

/**
 * Escapa parámetros para consultas SQL (uso adicional a prepared statements)
 */
function escape_sql_identifier($identifier) {
    // Solo permite letras, números y guiones bajos
    return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
}

/**
 * Valida y sanitiza búsquedas
 */
function sanitize_search_query($query, $max_length = 100) {
    $query = trim($query);
    if (strlen($query) > $max_length) {
        $query = substr($query, 0, $max_length);
    }
    
    // Escapar caracteres especiales para LIKE
    $query = str_replace(['%', '_'], ['\%', '\_'], $query);
    
    return $query;
}

/**
 * Detecta contenido malicioso en archivos subidos
 * Busca patrones comunes de código ejecutable disfrazado
 * 
 * @param string $filepath Ruta al archivo a analizar
 * @return bool True si se detecta contenido malicioso, False si es seguro
 */
function detect_malicious_content($filepath) {
    if (!is_file($filepath) || !is_readable($filepath)) {
        return true; // Si no podemos leer el archivo, es sospechoso
    }
    
    // Verificar si es una imagen real válida primero
    $image_info = @getimagesize($filepath);
    if ($image_info !== false) {
        // Es una imagen válida según getimagesize()
        // Solo buscar patrones maliciosos en el contenido textual
        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (in_array($image_info[2], $allowedImageTypes, true)) {
            // Es una imagen de tipo permitido, usar detección más específica
            return detect_malicious_in_image($filepath);
        }
    }
    
    // Para archivos no-imagen, hacer análisis completo
    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return true;
    }
    
    $content = fread($handle, 8192);
    fclose($handle);
    
    if ($content === false) {
        return true;
    }
    
    // Patrones de código malicioso común
    $malicious_patterns = [
        // PHP tags
        '/<\?php/i',
        '/<\?=/i',
        '/<\?/i',
        
        // Funciones peligrosas de PHP
        '/\beval\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bbase64_decode\s*\(/i',
        '/\bgzinflate\s*\(/i',
        '/\bstr_rot13\s*\(/i',
        
        // Scripts embebidos
        '/<script/i',
        
        // Null bytes (usado para bypass de extensión)
        '/\x00/',
        
        // Webshells comunes
        '/c99shell/i',
        '/r57shell/i',
        '/phpshell/i',
        '/wso\s*shell/i',
        '/b374k/i',
    ];
    
    // Verificar cada patrón
    foreach ($malicious_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            devlog('malicious_content_detected', [
                'file' => basename($filepath),
                'pattern' => $pattern
            ]);
            return true;
        }
    }
    
    // Verificar extensiones dobles (ej: image.php.jpg)
    $filename = basename($filepath);
    if (preg_match('/\.(php|phtml|php3|php4|php5|phps|pht|phar|asp|aspx|jsp|cgi|pl|py|sh|bat|cmd)\./i', $filename)) {
        devlog('double_extension_detected', ['filename' => $filename]);
        return true;
    }
    
    return false; // El archivo es seguro
}

/**
 * Detecta contenido malicioso específicamente en imágenes
 * Usa patrones más estrictos para evitar falsos positivos con datos binarios
 * 
 * @param string $filepath Ruta a la imagen
 * @return bool True si se detecta contenido malicioso
 */
function detect_malicious_in_image($filepath) {
    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return true;
    }
    
    // Leer contenido
    $content = fread($handle, 8192);
    fclose($handle);
    
    if ($content === false) {
        return true;
    }
    
    // Patrones específicos de código PHP/script (más estrictos para imágenes)
    // Buscamos patrones que NO deberían estar en imágenes binarias
    $strict_patterns = [
        // PHP tags completos (no fragmentos)
        '/<\?php\s/i',
        '/<\?=\s/i',
        
        // Funciones peligrosas con contexto
        '/\beval\s*\(\s*[\'"\$]/i',
        '/\bexec\s*\(\s*[\'"\$]/i',
        '/\bshell_exec\s*\(\s*[\'"\$]/i',
        '/\bsystem\s*\(\s*[\'"\$]/i',
        '/\bpassthru\s*\(\s*[\'"\$]/i',
        
        // Webshells conocidos (nombres completos)
        '/c99shell/i',
        '/r57shell/i',
        '/wso\s+shell/i',
    ];
    
    foreach ($strict_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            devlog('malicious_content_in_image', [
                'file' => basename($filepath),
                'pattern' => $pattern
            ]);
            return true;
        }
    }
    
    // Verificar extensiones dobles
    $filename = basename($filepath);
    if (preg_match('/\.(php|phtml|php3|php4|php5|phps|pht|phar)\./i', $filename)) {
        devlog('double_extension_in_image', ['filename' => $filename]);
        return true;
    }
    
    return false; // Imagen segura
}
