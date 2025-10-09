<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// Incluir header de forma segura
try {
    include __DIR__ . '/includes/header.php';
} catch (Exception $e) {
    // Si hay error con el header, usar versión simplificada
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Búsqueda - Bahía Under</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/matrix-theme.css">
    </head>
    <body>
        <header class="site-header">
            <div class="container nav">
                <a class="brand" href="index.php">
                    <span class="brand-text">Bahia <span class="ground">Under</span></span>
                </a>
                <nav class="menu">
                    <a href="musica.php">Música</a>
                    <a href="eventos.php">Agenda</a>
                    <a href="blog.php">Blog</a>
                </nav>
                <div class="actions">
                    <form class="search" role="search" method="get" action="buscar.php">
                        <input aria-label="Buscar" name="q" type="search" placeholder="Buscar..." value="<?php echo htmlspecialchars($query ?? ''); ?>" />
                    </form>
                </div>
            </div>
        </header>
    <?php
}

// Obtener término de búsqueda
$query = sanitize_search_query($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$results = [
    'artists' => [],
    'releases' => [],
    'blogs' => [],
    'events' => []
];

$total_results = 0;

if (!empty($query) && strlen($query) >= 2) {
    try {
        // Buscar artistas/perfiles - VERSIÓN SIMPLIFICADA
        $search_term = "%{$query}%";
        
        $artist_sql = "SELECT id, username, display_name, bio, avatar_path, brand_color, created_at FROM users WHERE status = 'active' AND (username LIKE ? OR display_name LIKE ? OR bio LIKE ?) ORDER BY created_at DESC LIMIT ? OFFSET ?";
        
        $artist_stmt = $pdo->prepare($artist_sql);
        $artist_stmt->execute([$search_term, $search_term, $search_term, $limit, $offset]);
        $results['artists'] = $artist_stmt->fetchAll();

        // Buscar lanzamientos - VERSIÓN SIMPLIFICADA
        $release_sql = "SELECT r.id, r.title, r.slug, r.description, r.genre, r.tags_csv, r.release_date, r.cover_path, r.download_enabled, r.type, r.artist_id, u.username, u.display_name, u.brand_color, COUNT(t.id) as track_count FROM releases r JOIN users u ON u.id = r.artist_id LEFT JOIN tracks t ON t.release_id = r.id WHERE r.status = 'approved' AND (r.title LIKE ? OR r.description LIKE ? OR r.genre LIKE ? OR r.tags_csv LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?) GROUP BY r.id, r.title, r.slug, r.description, r.genre, r.tags_csv, r.release_date, r.cover_path, r.download_enabled, r.type, r.artist_id, u.username, u.display_name, u.brand_color ORDER BY r.release_date DESC LIMIT ? OFFSET ?";
        
        $release_stmt = $pdo->prepare($release_sql);
        $release_stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $limit, $offset]);
        $results['releases'] = $release_stmt->fetchAll();

        // Buscar entradas de blog - VERSIÓN SIMPLIFICADA
        $blog_sql = "SELECT b.id, b.title, b.slug, b.excerpt, b.published_at, b.author_id, u.username, u.display_name, u.brand_color FROM blogs b JOIN users u ON u.id = b.author_id WHERE b.status = 'published' AND (b.title LIKE ? OR b.excerpt LIKE ? OR b.content LIKE ?) ORDER BY b.published_at DESC LIMIT ? OFFSET ?";
        
        $blog_stmt = $pdo->prepare($blog_sql);
        $blog_stmt->execute([$search_term, $search_term, $search_term, $limit, $offset]);
        $results['blogs'] = $blog_stmt->fetchAll();

        // Buscar eventos - VERSIÓN SIMPLIFICADA
        $event_sql = "SELECT e.id, e.title, e.description, e.event_dt, e.place_name, e.flyer_path, e.maps_url FROM events e WHERE e.status = 'active' AND (e.title LIKE ? OR e.description LIKE ? OR e.place_name LIKE ?) ORDER BY e.event_dt ASC LIMIT ? OFFSET ?";
        
        $event_stmt = $pdo->prepare($event_sql);
        $event_stmt->execute([$search_term, $search_term, $search_term, $limit, $offset]);
        $results['events'] = $event_stmt->fetchAll();

        // Contar total de resultados - VERSIÓN SIMPLIFICADA
        $total_artists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active' AND (username LIKE ? OR display_name LIKE ? OR bio LIKE ?)");
        $total_artists->execute([$search_term, $search_term, $search_term]);
        
        $total_releases = $pdo->prepare("SELECT COUNT(DISTINCT r.id) FROM releases r JOIN users u ON u.id = r.artist_id WHERE r.status = 'approved' AND (r.title LIKE ? OR r.description LIKE ? OR r.genre LIKE ? OR r.tags_csv LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)");
        $total_releases->execute([$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
        
        $total_blogs = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)");
        $total_blogs->execute([$search_term, $search_term, $search_term]);
        
        $total_events = $pdo->prepare("SELECT COUNT(*) FROM events WHERE status = 'active' AND (title LIKE ? OR description LIKE ? OR place_name LIKE ?)");
        $total_events->execute([$search_term, $search_term, $search_term]);
        
        $total_results = $total_artists->fetchColumn() + 
                        $total_releases->fetchColumn() + 
                        $total_blogs->fetchColumn() + 
                        $total_events->fetchColumn();

    } catch (Throwable $e) {
        devlog('search.failed', ['err' => $e->getMessage(), 'query' => $query]);
        $results = [
            'artists' => [],
            'releases' => [],
            'blogs' => [],
            'events' => []
        ];
    }
}

// Función helper para resaltar términos de búsqueda
function highlight_search($text, $query) {
    if (empty($query)) return htmlspecialchars($text);
    $highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
    return $highlighted;
}
?>

<main>
    <div class="container">
        <div class="search-header">
            <h1>Búsqueda</h1>
            
            <form class="search-form" method="get" action="<?php echo u('buscar.php'); ?>">
                <div class="search-input-group">
                    <input 
                        type="search" 
                        name="q" 
                        value="<?php echo htmlspecialchars($query); ?>" 
                        placeholder="Buscar artistas, lanzamientos, blogs, eventos..." 
                        class="search-input"
                        required
                    />
                    <button type="submit" class="btn primary">Buscar</button>
                </div>
            </form>
        </div>

        <?php if (!empty($query)): ?>
            <div class="search-results">
                <?php if ($total_results === 0): ?>
                    <div class="empty">
                        <h2>No se encontraron resultados</h2>
                        <p>No hay resultados para "<strong><?php echo htmlspecialchars($query); ?></strong>".</p>
                        <p>Intenta con otros términos o verifica la ortografía.</p>
                    </div>
                <?php else: ?>
                    <div class="results-summary">
                        <p>Se encontraron <strong><?php echo number_format($total_results); ?></strong> resultados para "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
                    </div>

                    <!-- Artistas -->
                    <?php if (!empty($results['artists'])): ?>
                        <section class="search-section">
                            <h2>Artistas (<?php echo count($results['artists']); ?>)</h2>
                            <div class="grid artists-grid">
                                <?php foreach ($results['artists'] as $artist): ?>
                                    <article class="card artist-card">
                                        <div class="artist-avatar">
                                            <?php if (!empty($artist['avatar_path'])): ?>
                                                <img src="<?php echo htmlspecialchars(u($artist['avatar_path'])); ?>" alt="Avatar de <?php echo htmlspecialchars($artist['username']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder" style="background-color: <?php echo htmlspecialchars($artist['brand_color'] ?? '#6366f1'); ?>">
                                                    <?php echo strtoupper(substr($artist['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="artist-info">
                                            <h3>
                                                <a href="<?php echo u('perfil.php'); ?>?id=<?php echo (int)$artist['id']; ?>">
                                                    @<?php echo highlight_search($artist['username'], $query); ?>
                                                </a>
                                            </h3>
                                            <?php if (!empty($artist['display_name'])): ?>
                                                <p class="display-name"><?php echo highlight_search($artist['display_name'], $query); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($artist['bio'])): ?>
                                                <p class="bio"><?php echo highlight_search(substr($artist['bio'], 0, 150), $query); ?><?php echo strlen($artist['bio']) > 150 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Lanzamientos -->
                    <?php if (!empty($results['releases'])): ?>
                        <section class="search-section">
                            <h2>Lanzamientos (<?php echo count($results['releases']); ?>)</h2>
                            <div class="grid releases-grid">
                                <?php foreach ($results['releases'] as $release): ?>
                                    <article class="card release-card">
                                        <a class="cover" href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$release['id']; ?>">
                                            <?php if (!empty($release['cover_path'])): ?>
                                                <img src="<?php echo htmlspecialchars(u($release['cover_path'])); ?>" alt="Portada de <?php echo htmlspecialchars($release['title']); ?>">
                                            <?php else: ?>
                                                <div class="cover-placeholder">
                                                    <span><?php echo strtoupper(substr($release['title'], 0, 2)); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        <div class="release-info">
                                            <h3>
                                                <a href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$release['id']; ?>">
                                                    <?php echo highlight_search($release['title'], $query); ?>
                                                </a>
                                            </h3>
                                            <div class="meta">
                                                <span>
                                                    <a href="<?php echo u('perfil.php'); ?>?id=<?php echo (int)$release['artist_id']; ?>" class="profile-link">
                                                        @<?php echo highlight_search($release['username'], $query); ?>
                                                    </a>
                                                </span>
                                                · <span><?php echo htmlspecialchars($release['type']); ?></span>
                                                <?php if (!empty($release['release_date'])): ?>
                                                    · <time datetime="<?php echo htmlspecialchars($release['release_date']); ?>">
                                                        <?php echo date('d/m/Y', strtotime($release['release_date'])); ?>
                                                    </time>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($release['description'])): ?>
                                                <p class="description"><?php echo highlight_search(substr($release['description'], 0, 100), $query); ?><?php echo strlen($release['description']) > 100 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            <div class="actions">
                                                <span class="chip"><?php echo (int)$release['track_count']; ?> pistas</span>
                                                <?php if ((int)$release['download_enabled'] === 1): ?>
                                                    <span class="badge">⬇︎ Descargable</span>
                                                <?php else: ?>
                                                    <span class="badge muted">Solo streaming</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Blogs -->
                    <?php if (!empty($results['blogs'])): ?>
                        <section class="search-section">
                            <h2>Blog (<?php echo count($results['blogs']); ?>)</h2>
                            <div class="blog-results">
                                <?php foreach ($results['blogs'] as $blog): ?>
                                    <article class="card blog-card">
                                        <div class="blog-content">
                                            <h3>
                                                <a href="<?php echo u('blog-view.php'); ?>?id=<?php echo (int)$blog['id']; ?>">
                                                    <?php echo highlight_search($blog['title'], $query); ?>
                                                </a>
                                            </h3>
                                            <div class="blog-meta">
                                                <span>
                                                    Por <a href="<?php echo u('perfil.php'); ?>?id=<?php echo (int)$blog['author_id']; ?>">@<?php echo htmlspecialchars($blog['username']); ?></a>
                                                </span>
                                                <?php if (!empty($blog['published_at'])): ?>
                                                    · <time datetime="<?php echo htmlspecialchars($blog['published_at']); ?>">
                                                        <?php echo date('d/m/Y', strtotime($blog['published_at'])); ?>
                                                    </time>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($blog['excerpt'])): ?>
                                                <p class="excerpt"><?php echo highlight_search($blog['excerpt'], $query); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Eventos -->
                    <?php if (!empty($results['events'])): ?>
                        <section class="search-section">
                            <h2>Eventos (<?php echo count($results['events']); ?>)</h2>
                            <div class="events-results">
                                <?php foreach ($results['events'] as $event): ?>
                                    <article class="card event-card">
                                        <div class="event-flyer">
                                            <?php if (!empty($event['flyer_path'])): ?>
                                                <img src="<?php echo htmlspecialchars(u($event['flyer_path'])); ?>" alt="Flyer de <?php echo htmlspecialchars($event['title']); ?>">
                                            <?php else: ?>
                                                <div class="flyer-placeholder">
                                                    <span><?php echo strtoupper(substr($event['title'], 0, 2)); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-info">
                                            <h3><?php echo highlight_search($event['title'], $query); ?></h3>
                                            <div class="event-meta">
                                                <time datetime="<?php echo htmlspecialchars($event['event_dt']); ?>">
                                                    <?php echo date('d/m/Y H:i', strtotime($event['event_dt'])); ?>
                                                </time>
                                                <?php if (!empty($event['place_name'])): ?>
                                                    · <span><?php echo highlight_search($event['place_name'], $query); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($event['description'])): ?>
                                                <p class="event-description"><?php echo highlight_search(substr($event['description'], 0, 150), $query); ?><?php echo strlen($event['description']) > 150 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($event['maps_url'])): ?>
                                                <a class="btn" href="<?php echo htmlspecialchars($event['maps_url']); ?>" target="_blank" rel="noopener">Ver en mapa</a>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="search-help">
                <h2>¿Qué puedes buscar?</h2>
                <div class="help-grid">
                    <div class="help-item">
                        <h3>🎵 Artistas</h3>
                        <p>Busca por nombre de usuario, nombre artístico o biografía</p>
                    </div>
                    <div class="help-item">
                        <h3>💿 Lanzamientos</h3>
                        <p>Encuentra singles, EPs y álbumes por título, género o descripción</p>
                    </div>
                    <div class="help-item">
                        <h3>📝 Blog</h3>
                        <p>Lee artículos y noticias de la escena</p>
                    </div>
                    <div class="help-item">
                        <h3>📅 Eventos</h3>
                        <p>Descubre próximos shows y eventos</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.search-header {
    text-align: center;
    margin: 2rem 0 3rem;
}

.search-form {
    max-width: 600px;
    margin: 0 auto;
}

.search-input-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 0.5rem;
    font-size: 1rem;
    background: var(--bg-color);
    color: var(--text-color);
}

.search-input:focus {
    outline: none;
    border-color: var(--brand-color);
}

.results-summary {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--card-bg);
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

.search-section {
    margin-bottom: 3rem;
}

.search-section h2 {
    margin-bottom: 1.5rem;
    color: var(--brand-color);
}

.artists-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.artist-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
}

.artist-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.artist-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.5rem;
    color: white;
}

.artist-info h3 {
    margin: 0 0 0.25rem 0;
}

.artist-info h3 a {
    color: var(--brand-color);
    text-decoration: none;
}

.display-name {
    font-weight: 500;
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
}

.bio {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.4;
}

.releases-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.cover-placeholder {
    width: 100%;
    aspect-ratio: 1;
    background: var(--card-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.5rem;
    color: var(--text-muted);
}

.description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0.5rem 0;
    line-height: 1.4;
}

.blog-results {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.blog-card {
    padding: 1.5rem;
}

.blog-content h3 {
    margin: 0 0 0.5rem 0;
}

.blog-content h3 a {
    color: var(--brand-color);
    text-decoration: none;
}

.blog-meta {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.excerpt {
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}

.events-results {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.event-card {
    display: flex;
    gap: 1rem;
    padding: 1rem;
}

.event-flyer {
    width: 120px;
    height: 120px;
    border-radius: 0.5rem;
    overflow: hidden;
    flex-shrink: 0;
}

.event-flyer img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.flyer-placeholder {
    width: 100%;
    height: 100%;
    background: var(--card-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.5rem;
    color: var(--text-muted);
}

.event-info h3 {
    margin: 0 0 0.5rem 0;
    color: var(--brand-color);
}

.event-meta {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.event-description {
    color: var(--text-muted);
    line-height: 1.4;
    margin: 0.5rem 0;
}

.search-help {
    text-align: center;
    margin: 3rem 0;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.help-item {
    padding: 1.5rem;
    background: var(--card-bg);
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

.help-item h3 {
    margin: 0 0 0.5rem 0;
    color: var(--brand-color);
}

.help-item p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.4;
}

mark {
    background: var(--brand-color);
    color: white;
    padding: 0.1rem 0.2rem;
    border-radius: 0.2rem;
}

@media (max-width: 768px) {
    .search-input-group {
        flex-direction: column;
    }
    
    .artist-card {
        flex-direction: column;
        text-align: center;
    }
    
    .event-card {
        flex-direction: column;
    }
    
    .event-flyer {
        width: 100%;
        height: 200px;
    }
}
</style>

<?php 
try {
    include __DIR__ . '/includes/footer.php'; 
} catch (Exception $e) {
    // Footer simplificado si hay error
    ?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2024 Bahía Under. Hecho por y para la escena de Bahía Blanca.</p>
        </div>
    </footer>
    </body>
    </html>
    <?php
}
?>
