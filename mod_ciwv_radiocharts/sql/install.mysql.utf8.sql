-- CIWV Radiocharts: install.mysql.utf8.sql
-- Run this script MANUALLY in your database. The module does NOT execute it
-- automatically. Table name is intentionally static (d6f25_ciwv_radiocharts).
--
-- Supported source identifiers (source column):
--   mediabase_national  — Mediabase Published 7-Day National Chart
--   mediabase_local     — Mediabase Station Playlist (local / station data)
--   luminate            — Luminate Streaming Data (national/Canada)
--   luminate_market     — Luminate Streaming Data (market, e.g. Vancouver)
--   musicmaster         — Music Master internal CSV export
--   billboard           — Billboard Chart

CREATE TABLE IF NOT EXISTS `d6f25_ciwv_radiocharts` (
    `id`                 INT           NOT NULL AUTO_INCREMENT,
    `week_date`          DATE          NOT NULL                  COMMENT 'Week start date (Monday, YYYY-MM-DD)',
    `source`             VARCHAR(50)   NOT NULL                  COMMENT 'Data source identifier — see list above',
    `position`           INT           NOT NULL DEFAULT 0        COMMENT 'Chart position this week (TW)',
    `position_lw`        INT           DEFAULT NULL              COMMENT 'Chart position last week (LW)',
    `artist`             VARCHAR(255)  NOT NULL,
    `title`              VARCHAR(255)  NOT NULL,
    `label`              VARCHAR(255)  DEFAULT NULL              COMMENT 'Record label',
    `cancon`             TINYINT(1)    NOT NULL DEFAULT 0        COMMENT '1 = Canadian Content',
    `plays`              INT           NOT NULL DEFAULT 0        COMMENT 'Spin / play count this week (Mediabase / MusicMaster)',
    `plays_lw`           INT           NOT NULL DEFAULT 0        COMMENT 'Spin / play count last week',
    `streams`            BIGINT        NOT NULL DEFAULT 0        COMMENT 'Stream count this week (Luminate)',
    `streams_lw`         BIGINT        NOT NULL DEFAULT 0        COMMENT 'Stream count last week (Luminate)',
    `streams_market`     BIGINT        NOT NULL DEFAULT 0        COMMENT 'Market-level stream count this week (Luminate market)',
    `streams_market_lw`  BIGINT        NOT NULL DEFAULT 0        COMMENT 'Market-level stream count last week',
    `market_spins_tw`    INT           DEFAULT NULL              COMMENT 'Spins in the station market this week',
    `market_stations_tw` INT           DEFAULT NULL              COMMENT 'Number of stations in market airing the song',
    `hist_spins`         INT           DEFAULT NULL              COMMENT 'Historical total spins (ATD) from Station Playlist',
    `first_played`       DATE          DEFAULT NULL              COMMENT 'Date first played by the station',
    `format_rank`        INT           DEFAULT NULL              COMMENT 'Format comparison rank from Station Playlist',
    `release_year`       SMALLINT      DEFAULT NULL              COMMENT 'Year the track was released',
    `peak_position`      INT           DEFAULT NULL              COMMENT 'All-time peak chart position',
    `weeks_on_chart`     INT           DEFAULT NULL              COMMENT 'Consecutive weeks charting',
    -- Category columns: set manually by Music Director / PD on the dashboard
    `category_tw`        VARCHAR(10)   DEFAULT NULL              COMMENT 'TW category (ADD|OUT|A1|A2|B|C|D|GOLD|J|P|PC2|PC3|Q|HOLD)',
    `category_nw`        VARCHAR(10)   DEFAULT NULL              COMMENT 'NW (Next Week) proposed category — applied to category_tw on next upload',
    `category_code`      VARCHAR(10)   DEFAULT NULL              COMMENT 'CAT/CODE secondary code (1|2|3|S|PSG|G|F|GS|GP|P|V|T|TG)',
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_week_source_position` (`week_date`, `source`, `position`),
    KEY `idx_week_date`  (`week_date`),
    KEY `idx_source`     (`source`),
    KEY `idx_artist`     (`artist`(100)),
    KEY `idx_title`      (`title`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
