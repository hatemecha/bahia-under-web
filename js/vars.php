<?php
// Generar variables globales para JavaScript (versión segura)
header('Content-Type: application/javascript');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Vary: Cookie, Accept-Encoding');

require_once __DIR__ . '/../includes/init.php';

// Obtener parámetros de la URL (validados)
$releaseId = isset($_GET['release_id']) ? max(0, (int)$_GET['release_id']) : 0;
$blogId = isset($_GET['blog_id']) ? max(0, (int)$_GET['blog_id']) : 0;

// Obtener datos de sesión (solo lo necesario)
$userId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
$userRole = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') : 'user';

// Validar roles permitidos
$allowedRoles = ['user', 'artist', 'mod', 'admin'];
if (!in_array($userRole, $allowedRoles, true)) {
    $userRole = 'user';
}

// Generar token CSRF para formularios
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

// Configuración de la aplicación (solo lo necesario para el cliente)
$appConfig = [
    'debug' => $APP_DEBUG,
    'url' => $APP_URL,
    'version' => '2.0'
];

echo "// Variables globales generadas dinámicamente (versión segura)\n";
echo "// Generado en: " . date('Y-m-d H:i:s') . "\n";
echo "window.releaseId = $releaseId;\n";
echo "window.blogId = $blogId;\n";
echo "window.userId = $userId;\n";
echo "window.userRole = '$userRole';\n";
echo "window.csrfToken = '$csrfToken';\n";
echo "window.appConfig = " . json_encode($appConfig, JSON_UNESCAPED_SLASHES) . ";\n";

// Solo mostrar logs de debug en desarrollo
if ($APP_DEBUG) {
    echo "console.log('Variables globales cargadas:', {releaseId: $releaseId, blogId: $blogId, userId: $userId, userRole: '$userRole'});\n";
}

// Log de acceso a variables
log_access('js_vars', 'load', $userId, [
    'release_id' => $releaseId,
    'blog_id' => $blogId
]);
?>
