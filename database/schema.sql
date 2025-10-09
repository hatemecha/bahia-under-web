CREATE DATABASE IF NOT EXISTS bahia_under
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bahia_under;

-- =====================================================
-- TABLA DE USUARIOS
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username         VARCHAR(30) NOT NULL,
  email            VARCHAR(255) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  role             ENUM('admin','mod','artist','user') NOT NULL DEFAULT 'user',
  artist_verified_at DATETIME NULL,
  status           ENUM('active','banned','pending') NOT NULL DEFAULT 'active',
  display_name     VARCHAR(80) NULL,
  bio              TEXT NULL,
  avatar_path      VARCHAR(255) NULL,
  links_json       JSON NULL,
  brand_color      VARCHAR(7) NULL,
  failed_login_attempts INT DEFAULT 0,
  last_failed_login DATETIME NULL,
  accepted_terms_at DATETIME NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  INDEX idx_users_email_username (email, username),
  INDEX idx_users_status (status),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE TOKENS "RECORDARME"
-- =====================================================
CREATE TABLE IF NOT EXISTS user_tokens (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  user_agent   VARCHAR(255) NULL,
  ip           VARCHAR(45) NULL,
  expires_at   DATETIME NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_tokens_user (user_id),
  INDEX idx_tokens_exp (expires_at),
  INDEX idx_user_tokens_hash (token_hash),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE LANZAMIENTOS
-- =====================================================
CREATE TABLE IF NOT EXISTS releases (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  artist_id         BIGINT UNSIGNED NOT NULL,
  title             VARCHAR(140) NOT NULL,
  slug              VARCHAR(180) NOT NULL,
  type              ENUM('single','ep','album') NOT NULL DEFAULT 'single',
  description       TEXT NULL,
  genre             VARCHAR(60) NULL,
  tags_csv          TEXT NULL,
  release_date      DATE NULL,
  cover_path        VARCHAR(255) NULL,
  download_enabled  TINYINT(1) NOT NULL DEFAULT 1,
  status            ENUM('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
  reviewed_by       BIGINT UNSIGNED NULL,
  reviewed_at       DATETIME NULL,
  review_notes      TEXT NULL,
  zip_generated_at  DATETIME NULL,
  zip_size_bytes    BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_releases_slug (slug),
  INDEX idx_releases_artist (artist_id),
  INDEX idx_releases_status (status),
  INDEX idx_releases_created (created_at DESC),
  INDEX idx_releases_status_created (status, created_at DESC),
  CONSTRAINT fk_releases_artist FOREIGN KEY (artist_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_releases_reviewedby FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE PISTAS
-- =====================================================
CREATE TABLE IF NOT EXISTS tracks (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  release_id    BIGINT UNSIGNED NOT NULL,
  track_no      SMALLINT UNSIGNED NOT NULL,
  title         VARCHAR(140) NOT NULL,
  audio_path    VARCHAR(255) NOT NULL,
  audio_mime    VARCHAR(100) NOT NULL,
  duration_ms   INT UNSIGNED NULL,
  lyrics        TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_track_order (release_id, track_no),
  INDEX idx_tracks_release (release_id),
  CONSTRAINT fk_tracks_release FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE COLABORADORES POR PISTA
-- =====================================================
CREATE TABLE IF NOT EXISTS track_collab_users (
  track_id   BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  role       VARCHAR(40) NULL DEFAULT 'feat',
  PRIMARY KEY (track_id, user_id),
  CONSTRAINT fk_tcu_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tcu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE EVENTOS
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(140) NOT NULL,
  description  TEXT NULL,
  event_dt     DATETIME NOT NULL,
  location     VARCHAR(180) NULL,
  place_name   VARCHAR(180) NULL,
  place_address VARCHAR(255) NULL,
  place_lat    DECIMAL(10,7) NULL,
  place_lng    DECIMAL(10,7) NULL,
  maps_url     VARCHAR(255) NULL,
  flyer_path   VARCHAR(255) NULL,
  status       ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  created_by   BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_events_dt (event_dt),
  INDEX idx_events_status (status),
  INDEX idx_events_status_dt (status, event_dt DESC),
  CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE BLOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS blogs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL,
  content      LONGTEXT NOT NULL,
  excerpt      TEXT NULL,
  status       ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
  featured     BOOLEAN NOT NULL DEFAULT FALSE,
  author_id    BIGINT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blogs_slug (slug),
  INDEX idx_blogs_status (status),
  INDEX idx_blogs_author (author_id),
  INDEX idx_blogs_published (published_at DESC),
  INDEX idx_blogs_featured (featured, published_at DESC),
  INDEX idx_blogs_status_published (status, published_at DESC),
  FULLTEXT INDEX ft_blogs_search (title, content, excerpt),
  CONSTRAINT fk_blogs_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE COMENTARIOS EN LANZAMIENTOS
-- =====================================================
CREATE TABLE IF NOT EXISTS release_comments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  release_id   BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  content      TEXT NOT NULL,
  parent_id    BIGINT UNSIGNED NULL,
  status       ENUM('active', 'hidden', 'deleted') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_release_comments_release (release_id),
  INDEX idx_release_comments_user (user_id),
  INDEX idx_release_comments_parent (parent_id),
  INDEX idx_release_comments_status (status),
  INDEX idx_release_comments_created (created_at DESC),
  CONSTRAINT fk_release_comments_release FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE,
  CONSTRAINT fk_release_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_release_comments_parent FOREIGN KEY (parent_id) REFERENCES release_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE COMENTARIOS EN BLOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS blog_comments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  blog_id      BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  content      TEXT NOT NULL,
  parent_id    BIGINT UNSIGNED NULL,
  status       ENUM('active', 'hidden', 'deleted') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_blog_comments_blog (blog_id),
  INDEX idx_blog_comments_user (user_id),
  INDEX idx_blog_comments_parent (parent_id),
  INDEX idx_blog_comments_status (status),
  INDEX idx_blog_comments_created (created_at DESC),
  CONSTRAINT fk_blog_comments_blog FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_comments_parent FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA DE LOGS DE SEGURIDAD
-- =====================================================
CREATE TABLE IF NOT EXISTS security_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  user_id INT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  event_type VARCHAR(100) NOT NULL,
  event_details JSON NULL,
  INDEX idx_security_logs_timestamp (timestamp),
  INDEX idx_security_logs_user_id (user_id),
  INDEX idx_security_logs_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



