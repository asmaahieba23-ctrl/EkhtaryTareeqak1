CREATE TABLE IF NOT EXISTS stories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    title_en VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    description_en TEXT NOT NULL,
    video_path VARCHAR(255) NOT NULL,
    video_mime VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stories_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
