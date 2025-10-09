<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// Configurar headers para AJAX
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Obtener término de búsqueda
$query = sanitize_search_query($_GET['q'] ?? '');

$suggestions = [];

if (!empty($query) && strlen($query) >= 2) {
    try {
        $search_term = "%{$query}%";
        $exact_search = "{$query}%";
        
        // Buscar artistas (máximo 5)
        $artist_sql = "
            SELECT id, username, display_name, 'artist' as type
            FROM users 
            WHERE status = 'active' 
            AND (
                username LIKE :query 
                OR display_name LIKE :query
            )
            ORDER BY 
                CASE WHEN username LIKE :exact_query THEN 1 ELSE 2 END,
                username ASC
            LIMIT 5
        ";
        
        $artist_stmt = $pdo->prepare($artist_sql);
        $artist_stmt->bindValue(':query', $search_term);
        $artist_stmt->bindValue(':exact_query', $exact_search);
        $artist_stmt->execute();
        $artists = $artist_stmt->fetchAll();
        
        foreach ($artists as $artist) {
            $suggestions[] = [
                'text' => '@' . $artist['username'] . (!empty($artist['display_name']) ? ' (' . $artist['display_name'] . ')' : ''),
                'type' => 'artist',
                'url' => 'perfil.php?id=' . $artist['id']
            ];
        }

        // Buscar lanzamientos (máximo 5)
        $release_sql = "
            SELECT r.id, r.title, u.username, 'release' as type
            FROM releases r
            JOIN users u ON u.id = r.artist_id
            WHERE r.status = 'approved'
            AND r.title LIKE :query
            ORDER BY 
                CASE WHEN r.title LIKE :exact_query THEN 1 ELSE 2 END,
                r.release_date DESC
            LIMIT 5
        ";
        
        $release_stmt = $pdo->prepare($release_sql);
        $release_stmt->bindValue(':query', $search_term);
        $release_stmt->bindValue(':exact_query', $exact_search);
        $release_stmt->execute();
        $releases = $release_stmt->fetchAll();
        
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
            AND b.title LIKE :query
            ORDER BY 
                CASE WHEN b.title LIKE :exact_query THEN 1 ELSE 2 END,
                b.published_at DESC
            LIMIT 3
        ";
        
        $blog_stmt = $pdo->prepare($blog_sql);
        $blog_stmt->bindValue(':query', $search_term);
        $blog_stmt->bindValue(':exact_query', $exact_search);
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
            WHERE status = 'active'
            AND title LIKE :query
            ORDER BY 
                CASE WHEN title LIKE :exact_query THEN 1 ELSE 2 END,
                event_dt ASC
            LIMIT 3
        ";
        
        $event_stmt = $pdo->prepare($event_sql);
        $event_stmt->bindValue(':query', $search_term);
        $event_stmt->bindValue(':exact_query', $exact_search);
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
