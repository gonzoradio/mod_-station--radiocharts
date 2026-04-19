-- CIWV Radiocharts: install.mysql.utf8.sql
-- Creates the shared chart-data table used by all four import plugins.
-- Each row represents one track's chart position for a given week and data source.

CREATE TABLE IF NOT EXISTS `#__ciwv_radiocharts` (
    `id`              INT           NOT NULL AUTO_INCREMENT,
    `week_date`       DATE          NOT NULL                COMMENT 'Week start date (Monday)',
    `source`          VARCHAR(50)   NOT NULL                COMMENT 'Data source: mediabase_national | mediabase_local | luminate | musicmaster',
    `position`        INT           NOT NULL DEFAULT 0      COMMENT 'Chart position this week',
    `artist`          VARCHAR(255)  NOT NULL,
    `title`           VARCHAR(255)  NOT NULL,
    `label`           VARCHAR(255)  DEFAULT NULL            COMMENT 'Record label',
    `plays`           INT           NOT NULL DEFAULT 0      COMMENT 'Spin / play count (Mediabase & Music Master)',
    `streams`         BIGINT        NOT NULL DEFAULT 0      COMMENT 'Stream count (Luminate)',
    `peak_position`   INT           DEFAULT NULL            COMMENT 'All-time peak chart position',
    `weeks_on_chart`  INT           DEFAULT NULL            COMMENT 'Consecutive weeks charting',
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_week_source_position` (`week_date`, `source`, `position`),
    KEY `idx_week_date`  (`week_date`),
    KEY `idx_source`     (`source`),
    KEY `idx_artist`     (`artist`(100)),
    KEY `idx_title`      (`title`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
