<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// Configurar headers para AJAX
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Vary: Accept-Encoding');

// Obtener término de búsqueda
$query = sanitize_search_query($_GET['q'] ?? '');
$suggestions = [];

if (!empty($query) && strlen($query) >= 2) {
    try {
        // Preparar términos de búsqueda
        $search_term = "%{$query}%";
        $exact_search = "{$query}%";
        
        // Buscar artistas (máximo 5) - versión híbrida que funciona
        $artist_sql = "
            SELECT id, username, display_name, 'artist' as type
            FROM users 
            WHERE status = 'active'
            LIMIT 10
        ";
        
        $artist_stmt = $pdo->prepare($artist_sql);
        $artist_stmt->execute();
        $all_artists = $artist_stmt->fetchAll();
        
        // Filtrar en PHP con ordenamiento inteligente
        $artists = [];
        foreach ($all_artists as $artist) {
            $username_match = stripos($artist['username'], $query);
            $display_match = stripos($artist['display_name'] ?? '', $query);
            
            if ($username_match !== false || $display_match !== false) {
                $artists[] = $artist;
            }
        }
        
        // Ordenar por relevancia (coincidencias exactas primero)
        usort($artists, function($a, $b) use ($query) {
            $a_starts = (stripos($a['username'], $query) === 0) ? 1 : 0;
            $b_starts = (stripos($b['username'], $query) === 0) ? 1 : 0;
            return $b_starts - $a_starts;
        });
        
        $artists = array_slice($artists, 0, 5);
        
        foreach ($artists as $artist) {
            $suggestions[] = [
                'text' => '@' . $artist['username'] . (!empty($artist['display_name']) ? ' (' . $artist['display_name'] . ')' : ''),
                'type' => 'artist',
                'url' => 'perfil.php?id=' . $artist['id']
            ];
        }

        // Buscar lanzamientos (máximo 5) - versión híbrida que funciona
        $release_sql = "
            SELECT r.id, r.title, u.username, 'release' as type
            FROM releases r
            JOIN users u ON u.id = r.artist_id
            WHERE r.status = 'approved'
            LIMIT 10
        ";
        
        $release_stmt = $pdo->prepare($release_sql);
        $release_stmt->execute();
        $all_releases = $release_stmt->fetchAll();
        
        // Filtrar en PHP con ordenamiento inteligente
        $releases = [];
        foreach ($all_releases as $release) {
            $title_match = stripos($release['title'], $query);
            $username_match = stripos($release['username'], $query);
            
            if ($title_match !== false || $username_match !== false) {
                $releases[] = $release;
            }
        }
        
        // Ordenar por relevancia (coincidencias exactas primero)
        usort($releases, function($a, $b) use ($query) {
            $a_title_starts = (stripos($a['title'], $query) === 0) ? 2 : 0;
            $a_username_starts = (stripos($a['username'], $query) === 0) ? 1 : 0;
            $b_title_starts = (stripos($b['title'], $query) === 0) ? 2 : 0;
            $b_username_starts = (stripos($b['username'], $query) === 0) ? 1 : 0;
            
            $a_score = $a_title_starts + $a_username_starts;
            $b_score = $b_title_starts + $b_username_starts;
            
            return $b_score - $a_score;
        });
        
        $releases = array_slice($releases, 0, 5);
        
        foreach ($releases as $release) {
            $suggestions[] = [
                'text' => $release['title'] . ' - @' . $release['username'],
                'type' => 'release',
                'url' => 'lanzamiento.php?id=' . $release['id']
            ];
        }
        
        // Buscar blogs (máximo 3)
        $blog_sql = "
            SELECT b.id, b.title, u.username, 'blog' as type
            FROM blogs b
            JOIN users u ON u.id = b.author_id
            WHERE b.status = 'published'
            AND (
                b.title LIKE :query 
                OR u.username LIKE :query
                OR b.title LIKE :exact_search
                OR u.username LIKE :exact_search
            )
            ORDER BY 
                CASE WHEN b.title LIKE :exact_search THEN 1 
                     WHEN u.username LIKE :exact_search THEN 2
                     ELSE 3 END,
                b.published_at DESC
            LIMIT 3
        ";
        
        $blog_stmt = $pdo->prepare($blog_sql);
        $blog_stmt->bindValue(':query', $search_term);
        $blog_stmt->bindValue(':exact_search', $exact_search);
        $blog_stmt->execute();
        $blogs = $blog_stmt->fetchAll();
        
        foreach ($blogs as $blog) {
            $suggestions[] = [
                'text' => $blog['title'] . ' - @' . $blog['username'],
                'type' => 'blog',
                'url' => 'blog-view.php?id=' . $blog['id']
            ];
        }

        // Buscar eventos (máximo 3)
        $event_sql = "
            SELECT id, title, 'event' as type
            FROM events
            WHERE status = 'published'
            AND (
                title LIKE :query 
                OR title LIKE :exact_search
            )
            ORDER BY 
                CASE WHEN title LIKE :exact_search THEN 1 ELSE 2 END,
                event_date ASC
            LIMIT 3
        ";
        
        $event_stmt = $pdo->prepare($event_sql);
        $event_stmt->bindValue(':query', $search_term);
        $event_stmt->bindValue(':exact_search', $exact_search);
        $event_stmt->execute();
        $events = $event_stmt->fetchAll();
        
        foreach ($events as $event) {
            $suggestions[] = [
                'text' => $event['title'],
                'type' => 'event',
                'url' => 'eventos.php'
            ];
        }

    } catch (Throwable $e) {
        devlog('search_suggestions.failed', ['err' => $e->getMessage(), 'query' => $query]);
    }
}

echo json_encode([
    'suggestions' => $suggestions,
    'query' => $query
]);
?>