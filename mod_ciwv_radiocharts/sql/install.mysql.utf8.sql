-- SQL install script for mod_ciwv_radiocharts
-- Creates the week-snapshot state table used by the dashboard.

CREATE TABLE IF NOT EXISTS `#__ciwv_radiocharts_state` (
    `week_start` DATE        NOT NULL,
    `state_json` LONGTEXT    NOT NULL,
    `meta_line`  TEXT        NOT NULL DEFAULT '',
    `saved_at`   DATETIME    NOT NULL,
    PRIMARY KEY (`week_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
