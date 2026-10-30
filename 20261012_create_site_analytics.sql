CREATE TABLE IF NOT EXISTS site_analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_type ENUM('page_view', 'assistant_message', 'stayed_30_sec', 'scrolled_to_end') NOT NULL,
    page_path VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_analytics_type_created (event_type, created_at),
    KEY idx_site_analytics_visitor_type (visitor_hash, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
