<?php
// Usar init.php para consistencia con el resto de la aplicación
require_once __DIR__ . '/includes/init.php';

// La sesión ya fue iniciada por init.php con start_secure_session()
// No es necesario llamar a session_start() manualmente

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) { http_response_code(400); exit('Falta id.'); }

$stmt = $pdo->prepare("SELECT r.*, u.username FROM releases r JOIN users u ON u.id=r.artist_id WHERE r.id=? LIMIT 1");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r || $r['status']!=='approved') { http_response_code(404); exit('No disponible.'); }
if ((int)$r['download_enabled'] !== 1) { http_response_code(403); exit('Descarga no habilitada.'); }

$q = $pdo->prepare("SELECT track_no, title, audio_path FROM tracks WHERE release_id=? ORDER BY track_no ASC");
$q->execute([$id]);
$tracks = $q->fetchAll();
if (!$tracks) { http_response_code(404); exit('Sin pistas.'); }

$zipName = preg_replace('/[^a-z0-9._-]+/i','_', $r['title']).'_'.strtolower($r['username']).'.zip';

$tmp = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE)!==TRUE) { http_response_code(500); exit('ZIP error'); }

foreach ($tracks as $t) {
  $src = __DIR__ . '/' . $t['audio_path'];
  if (!is_file($src)) continue;
  $base = sprintf('%02d - %s.%s',
    (int)$t['track_no'],
    preg_replace('/[^a-z0-9._ -]+/i','_', $t['title']),
    pathinfo($src, PATHINFO_EXTENSION)
  );
  $zip->addFile($src, $base);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.str_replace('"','',$zipName).'"');
header('Content-Length: '.filesize($tmp));
readfile($tmp);
@unlink($tmp);
