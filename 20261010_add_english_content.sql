ALTER TABLE articles
    ADD COLUMN title_en VARCHAR(200) NULL,
    ADD COLUMN summary_en TEXT NULL,
    ADD COLUMN content_en LONGTEXT NULL;

ALTER TABLE achievements
    ADD COLUMN title_en VARCHAR(200) NULL,
    ADD COLUMN description_en TEXT NULL;
