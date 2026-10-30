ALTER TABLE site_analytics_events
    MODIFY COLUMN event_type ENUM(
        'page_view',
        'assistant_message',
        'stayed_30_sec',
        'scrolled_to_end'
    ) NOT NULL;
