<?php
/**
 * Sistema de encriptación seguro para datos sensibles
 */

class SecureEncryption {
    private $key;
    private $cipher = 'AES-256-GCM';
    
    public function __construct($key = null) {
        $this->key = $key ?: $GLOBALS['ENCRYPTION_KEY'] ?? bin2hex(random_bytes(32));
        
        if (strlen($this->key) !== 64) {
            throw new InvalidArgumentException('La clave de encriptación debe tener 64 caracteres (32 bytes en hex)');
        }
    }
    
    /**
     * Encriptar datos sensibles
     */
    public function encrypt($data) {
        if (empty($data)) {
            return null;
        }
        
        $iv = random_bytes(12); // 96 bits para GCM
        $key = hex2bin($this->key);
        
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($encrypted === false) {
            throw new RuntimeException('Error al encriptar los datos');
        }
        
        // Combinar IV + tag + datos encriptados
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Desencriptar datos sensibles
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return null;
        }
        
        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new InvalidArgumentException('Datos encriptados inválidos');
        }
        
        $key = hex2bin($this->key);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);
        
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($decrypted === false) {
            throw new RuntimeException('Error al desencriptar los datos');
        }
        
        return $decrypted;
    }
    
    /**
     * Generar hash seguro para contraseñas
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iteraciones
            'threads' => 3          // 3 hilos
        ]);
    }
    
    /**
     * Verificar contraseña
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar token seguro
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generar hash HMAC seguro
     */
    public function hmac($data, $key = null) {
        $key = $key ?: $this->key;
        return hash_hmac('sha256', $data, $key);
    }
    
    /**
     * Verificar hash HMAC
     */
    public function verifyHmac($data, $hash, $key = null) {
        $expected = $this->hmac($data, $key);
        return hash_equals($expected, $hash);
    }
}

// Funciones de conveniencia
function encrypt_sensitive_data($data) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->encrypt($data);
}

function decrypt_sensitive_data($encryptedData) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->decrypt($encryptedData);
}

function secure_hash_password($password) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->hashPassword($password);
}

function secure_verify_password($password, $hash) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->verifyPassword($password, $hash);
}

function generate_secure_token($length = 32) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->generateToken($length);
}

function secure_hmac($data, $key = null) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->hmac($data, $key);
}

function verify_secure_hmac($data, $hash, $key = null) {
    static $encryption = null;
    if ($encryption === null) {
        $encryption = new SecureEncryption();
    }
    return $encryption->verifyHmac($data, $hash, $key);
}
