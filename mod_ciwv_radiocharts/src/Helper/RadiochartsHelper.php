<?php

/**
 * @package     mod_ciwv_radiocharts
 * @subpackage  Helper
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Gonzoradio\Module\CiwvRadiocharts\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

/**
 * RadiochartsHelper — core database and logic helper for mod_ciwv_radiocharts.
 *
 * Responsibilities
 * ────────────────
 * • Week-date arithmetic (getWeekDate)
 * • Fetching chart data from d6f25_ciwv_radiocharts (getChartEntries)
 * • Building previous-week position maps for delta arrows (buildPreviousPositionMap)
 * • Upserting chart rows (upsertEntry)
 * • Saving TW/NW/Code category choices made on the dashboard (saveSongCategory)
 * • Applying NW → TW on the next-week upload cycle (applyNwToTw)
 * • Song/artist canonicalization for cross-source matching (canonicalizeSongKey)
 *
 * Database
 * ────────
 * All queries use the static table name `d6f25_ciwv_radiocharts` (NOT the
 * #__ Joomla prefix) because the table is managed outside the module installer.
 *
 * @since  1.1.0
 */
class RadiochartsHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /** @var string Static table name — never use #__ prefix. */
    private const TABLE = 'd6f25_ciwv_radiocharts';

    /**
     * Valid TW/NW category codes accepted by the dashboard.
     * OUT and Q are treated as "→ none next cycle" by applyNwToTw().
     */
    public const CATEGORIES = ['ADD', 'OUT', 'A1', 'A2', 'B', 'C', 'D', 'GOLD', 'J', 'P', 'PC2', 'PC3', 'Q', 'HOLD'];

    /** Display sort order for TW/NW dropdowns. */
    public const CATEGORY_ORDER = ['A1', 'J', 'A2', 'P', 'B', 'C', 'D', 'GOLD', 'PC2', 'PC3', 'HOLD', 'ADD', 'Q', 'OUT'];

    /**
     * Valid CAT/CODE secondary codes for the dashboard.
     * These are NOT compared week-over-week; the user sets them manually.
     */
    public const CATEGORY_CODES = ['1', '2', '3', 'S', 'PSG', 'G', 'F', 'GS', 'GP', 'P', 'V', 'T', 'TG'];

    /**
     * Source identifiers and their human-readable names.
     * These match the `source` column in d6f25_ciwv_radiocharts.
     */
    public const SOURCES = [
        'mediabase_national' => 'National Playlist (Mediabase)',
        'mediabase_local'    => 'Station Playlist (Mediabase)',
        'luminate'           => 'Streaming Data — National (Luminate)',
        'luminate_market'    => 'Streaming Data — Market (Luminate)',
        'musicmaster'        => 'Music Master CSV',
        'billboard'          => 'Billboard Chart',
    ];

    /**
     * Constructor — accepts the database driver for both live and test contexts.
     *
     * In a live Joomla 4 installation the DI container injects the database via
     * setDatabase() through HelperFactory. For unit tests the constructor
     * provides a convenient injection point.
     *
     * @param   DatabaseInterface|null  $db  Optional database driver.
     *
     * @since   1.1.0
     */
    public function __construct(?DatabaseInterface $db = null)
    {
        if ($db !== null) {
            $this->setDatabase($db);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Date helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the Monday of the week that is `$offset` weeks from the current week.
     *
     * @param   int  $offset  Number of weeks to offset (0 = current, -1 = previous, etc.)
     *
     * @return  string  Date string in YYYY-MM-DD format.
     *
     * @since   1.0.0
     */
    public function getWeekDate(int $offset = 0): string
    {
        $monday = new \DateTime('monday this week');

        if ($offset !== 0) {
            $monday->modify(($offset > 0 ? '+' : '') . $offset . ' weeks');
        }

        return $monday->format('Y-m-d');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Chart data retrieval
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetches chart entries from d6f25_ciwv_radiocharts for the given week/sources.
     *
     * Returns an associative array keyed by source identifier, each containing
     * an ordered list of stdClass chart-row objects.  The returned objects include
     * the category_tw, category_nw, and category_code columns so the template can
     * render the PD / MD category dropdowns.
     *
     * @param   string    $weekDate  Week start date (YYYY-MM-DD).
     * @param   string[]  $sources   Data sources to include.
     * @param   int       $limit     Maximum rows per source.
     *
     * @return  array<string, object[]>  Entries grouped by source.
     *
     * @since   1.0.0
     */
    public function getChartEntries(string $weekDate, array $sources, int $limit = 50): array
    {
        if (empty($sources)) {
            return [];
        }

        $limit = max(1, $limit);
        $db    = $this->getDatabase();

        $columns = [
            'id', 'week_date', 'source', 'position', 'position_lw',
            'artist', 'title', 'label', 'cancon',
            'plays', 'plays_lw', 'streams', 'streams_lw',
            'streams_market', 'streams_market_lw',
            'market_spins_tw', 'market_stations_tw',
            'hist_spins', 'first_played', 'format_rank', 'release_year',
            'peak_position', 'weeks_on_chart',
            'category_tw', 'category_nw', 'category_code',
        ];

        $query = $db->getQuery(true)
            ->select($db->quoteName($columns))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('week_date') . ' = ' . $db->quote($weekDate))
            ->whereIn($db->quoteName('source'), array_map([$db, 'quote'], $sources), false)
            ->order($db->quoteName('source') . ' ASC, ' . $db->quoteName('position') . ' ASC')
            ->setLimit($limit * \count($sources));

        try {
            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            // Table may not exist yet or a DB error occurred — fail silently.
            return [];
        }

        // Group by source and honour per-source limit.
        $grouped = [];

        foreach ($rows as $row) {
            $src = $row->source;

            if (!isset($grouped[$src])) {
                $grouped[$src] = [];
            }

            if (\count($grouped[$src]) < $limit) {
                $grouped[$src][] = $row;
            }
        }

        return $grouped;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Position comparison helper (used by template)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns a lookup map of previous-week positions keyed by "source|artist|title".
     *
     * Uses canonicalizeSongKey() so the map survives minor text-casing differences
     * between weekly CSV exports.
     *
     * @param   array<string, object[]>  $previousEntries  Entries from the previous week.
     *
     * @return  array<string, int>  Composite key → previous position.
     *
     * @since   1.0.0
     */
    public static function buildPreviousPositionMap(array $previousEntries): array
    {
        $map = [];

        foreach ($previousEntries as $source => $entries) {
            foreach ($entries as $entry) {
                $key       = self::canonicalizeSongKey($source, $entry->artist, $entry->title);
                $map[$key] = (int) $entry->position;
            }
        }

        return $map;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Song canonicalization / matching
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Produces a normalised lowercase lookup key for a song.
     *
     * This key is used to match songs across CSV sources (Mediabase, Luminate,
     * Music Master) where artist names and titles may differ in:
     *   • Letter case (BRUNO MARS vs. Bruno Mars)
     *   • Punctuation (She's vs. Shes, "Title" vs. Title)
     *   • Truncation (titles cut at different character counts)
     *   • Featured artist notation (f/, feat., ft., w/, with)
     *
     * Matching strategy:
     *   1. Title match is primary — stripped of everything after the first
     *      featured-artist marker.
     *   2. Artist match is secondary / "close enough" — compared only up to the
     *      first featured-artist marker in the artist field.
     *
     * @param   string  $source  Source identifier (used as key namespace).
     * @param   string  $artist  Raw artist string from CSV.
     * @param   string  $title   Raw title string from CSV.
     *
     * @return  string  Normalised lookup key.
     *
     * @since   1.1.0
     */
    public static function canonicalizeSongKey(string $source, string $artist, string $title): string
    {
        return strtolower($source . '|' . self::canonicalizeText($artist) . '|' . self::canonicalizeText($title));
    }

    /**
     * Normalises a title or artist string for cross-source matching.
     *
     * Strips featured-artist suffixes, removes punctuation that varies between
     * sources, collapses whitespace, and lowercases the result.
     *
     * @param   string  $text  Raw string from a CSV field.
     *
     * @return  string  Normalised string suitable for loose comparison.
     *
     * @since   1.1.0
     */
    public static function canonicalizeText(string $text): string
    {
        // Remove featured-artist suffixes (f/, feat., ft., w/, with, x/) and
        // everything that follows.
        $text = preg_replace('/\s+(?:f\/|feat\.?|ft\.?|w\/|with\s|x\/)\s*.+$/iu', '', $text) ?? $text;

        // Strip common punctuation that differs between sources.
        $text = preg_replace('/["\'\.\,\!\?\(\)\[\]\{\}\&\-\/\\\\]/u', ' ', $text) ?? $text;

        // Collapse multiple spaces, trim, lowercase.
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return $text;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Category management
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Saves a TW category, NW (next-week proposal), and/or CAT/CODE for a row.
     *
     * Called from the dashboard template when the PD / MD submits category edits.
     * Unknown category values are silently ignored to prevent injection.
     *
     * @param   int          $rowId        The `id` of the d6f25_ciwv_radiocharts row.
     * @param   string|null  $categoryTw   New TW category (or null = no change).
     * @param   string|null  $categoryNw   New NW category proposal (or null = no change).
     * @param   string|null  $categoryCode New CAT/CODE (or null = no change).
     *
     * @return  bool  True on success.
     *
     * @since   1.1.0
     */
    public function saveSongCategory(
        int $rowId,
        ?string $categoryTw   = null,
        ?string $categoryNw   = null,
        ?string $categoryCode = null
    ): bool {
        $sets = [];
        $db   = $this->getDatabase();
        $now  = (new \DateTime())->format('Y-m-d H:i:s');

        if ($categoryTw !== null) {
            $safe = \in_array($categoryTw, self::CATEGORIES, true) ? $categoryTw : null;
            $sets[] = $db->quoteName('category_tw') . ' = '
                . ($safe !== null ? $db->quote($safe) : 'NULL');
        }

        if ($categoryNw !== null) {
            $safe = \in_array($categoryNw, self::CATEGORIES, true) ? $categoryNw : null;
            $sets[] = $db->quoteName('category_nw') . ' = '
                . ($safe !== null ? $db->quote($safe) : 'NULL');
        }

        if ($categoryCode !== null) {
            $safe = \in_array($categoryCode, self::CATEGORY_CODES, true) ? $categoryCode : null;
            $sets[] = $db->quoteName('category_code') . ' = '
                . ($safe !== null ? $db->quote($safe) : 'NULL');
        }

        if (empty($sets)) {
            return true; // Nothing to save.
        }

        $sets[] = $db->quoteName('updated_at') . ' = ' . $db->quote($now);

        $query = $db->getQuery(true)
            ->update($db->quoteName(self::TABLE))
            ->set($sets)
            ->where($db->quoteName('id') . ' = ' . (int) $rowId);

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * Promotes NW categories to TW on a new week's upload cycle.
     *
     * Call this BEFORE upserting the new week's chart data.  For every song in
     * the previous week:
     *   • If category_nw is set → copy it to category_tw on the NEW week's row.
     *   • OUT / Q → set category_tw = NULL on the new row (song leaves the list).
     *
     * The matching between old and new rows is done via canonicalizeSongKey()
     * so minor text differences between weekly exports are handled gracefully.
     *
     * @param   string    $newWeekDate   The new week's start date (YYYY-MM-DD).
     * @param   string    $prevWeekDate  The previous week's start date (YYYY-MM-DD).
     * @param   string[]  $sources       Which sources to process (default: both Mediabase).
     *
     * @return  int  Number of rows updated.
     *
     * @since   1.1.0
     */
    public function applyNwToTw(
        string $newWeekDate,
        string $prevWeekDate,
        array $sources = ['mediabase_national', 'mediabase_local']
    ): int {
        // Load previous week rows that have a non-null NW category.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'source', 'artist', 'title', 'category_nw', 'category_tw', 'category_code']))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('week_date') . ' = ' . $db->quote($prevWeekDate))
            ->whereIn($db->quoteName('source'), array_map([$db, 'quote'], $sources), false)
            ->where($db->quoteName('category_nw') . ' IS NOT NULL');

        try {
            $db->setQuery($query);
            $prevRows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return 0;
        }

        if (empty($prevRows)) {
            return 0;
        }

        // Build a lookup map: canonicalKey → [category_tw, category_nw, category_code]
        $carryMap = [];

        foreach ($prevRows as $row) {
            $key            = self::canonicalizeSongKey($row->source, $row->artist, $row->title);
            $carryMap[$key] = [
                'tw'   => $row->category_nw,  // NW becomes the new TW
                'code' => $row->category_code, // Preserve the code
            ];
        }

        // Load the new-week rows for the same sources.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'source', 'artist', 'title']))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('week_date') . ' = ' . $db->quote($newWeekDate))
            ->whereIn($db->quoteName('source'), array_map([$db, 'quote'], $sources), false);

        try {
            $db->setQuery($query);
            $newRows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return 0;
        }

        $updated = 0;
        $now     = (new \DateTime())->format('Y-m-d H:i:s');

        foreach ($newRows as $row) {
            $key = self::canonicalizeSongKey($row->source, $row->artist, $row->title);

            if (!isset($carryMap[$key])) {
                // New song this week — TW category starts as NULL (no designation).
                continue;
            }

            $newTw   = $carryMap[$key]['tw'];
            $newCode = $carryMap[$key]['code'];

            // OUT and Q mean "remove from rotation" — set TW to NULL.
            if (\in_array($newTw, ['OUT', 'Q'], true)) {
                $newTw = null;
            }

            $sets = [
                $db->quoteName('category_tw') . ' = ' . ($newTw !== null ? $db->quote($newTw) : 'NULL'),
                $db->quoteName('category_nw') . ' = NULL',
                $db->quoteName('category_code') . ' = ' . ($newCode !== null ? $db->quote($newCode) : 'NULL'),
                $db->quoteName('updated_at') . ' = ' . $db->quote($now),
            ];

            $upd = $db->getQuery(true)
                ->update($db->quoteName(self::TABLE))
                ->set($sets)
                ->where($db->quoteName('id') . ' = ' . (int) $row->id);

            try {
                $db->setQuery($upd)->execute();
                $updated++;
            } catch (\RuntimeException $e) {
                // Continue with remaining rows.
            }
        }

        return $updated;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Upsert
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Inserts or updates a single chart row in d6f25_ciwv_radiocharts.
     *
     * The match key is (week_date, source, position).  Any existing category_tw
     * and category_code values are preserved on update — only category_nw is
     * cleared on re-import of the same row so the PD's choices survive a CSV
     * re-upload within the same week.
     *
     * @param   string       $weekDate         Week start date (YYYY-MM-DD).
     * @param   string       $source           Source identifier.
     * @param   int          $position         Chart position this week.
     * @param   string       $artist           Artist name.
     * @param   string       $title            Track title.
     * @param   string|null  $label            Record label.
     * @param   bool         $cancon           Canadian Content flag.
     * @param   int          $plays            Spin count this week.
     * @param   int          $playsLw          Spin count last week.
     * @param   int          $streams          National stream count this week.
     * @param   int          $streamsLw        National stream count last week.
     * @param   int          $streamsMarket    Market stream count this week.
     * @param   int          $streamsMarketLw  Market stream count last week.
     * @param   int|null     $marketSpinsTw    Market spins this week.
     * @param   int|null     $marketStationsTw Market stations count.
     * @param   int|null     $histSpins        Historical ATD spins.
     * @param   string|null  $firstPlayed      Date first played (YYYY-MM-DD).
     * @param   int|null     $formatRank       Format comparison rank.
     * @param   int|null     $releaseYear      Release year.
     * @param   int|null     $peakPosition     All-time peak position.
     * @param   int|null     $weeksOnChart     Weeks on chart.
     * @param   int|null     $positionLw       Position last week.
     *
     * @return  bool  True on success.
     *
     * @since   1.0.0
     */
    public function upsertEntry(
        string $weekDate,
        string $source,
        int $position,
        string $artist,
        string $title,
        ?string $label            = null,
        bool $cancon              = false,
        int $plays                = 0,
        int $playsLw              = 0,
        int $streams              = 0,
        int $streamsLw            = 0,
        int $streamsMarket        = 0,
        int $streamsMarketLw      = 0,
        ?int $marketSpinsTw       = null,
        ?int $marketStationsTw    = null,
        ?int $histSpins           = null,
        ?string $firstPlayed      = null,
        ?int $formatRank          = null,
        ?int $releaseYear         = null,
        ?int $peakPosition        = null,
        ?int $weeksOnChart        = null,
        ?int $positionLw          = null
    ): bool {
        $db = $this->getDatabase();

        // Look for an existing record for this week / source / position.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'category_tw', 'category_code']))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('week_date') . ' = ' . $db->quote($weekDate))
            ->where($db->quoteName('source') . ' = ' . $db->quote($source))
            ->where($db->quoteName('position') . ' = ' . (int) $position);

        $db->setQuery($query);
        $existing = $db->loadObject();

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $nullOrInt  = static fn (?int $v): string    => $v !== null ? (string) (int) $v : 'NULL';
        // Arrow functions auto-capture outer variables — no 'use' keyword needed.
        $nullOrStr  = static fn (?string $v): string  => $v !== null ? $db->quote($v) : 'NULL';
        $nullOrDate = static fn (?string $v): string  => ($v !== null && $v !== '') ? $db->quote($v) : 'NULL';

        if ($existing) {
            $query = $db->getQuery(true)
                ->update($db->quoteName(self::TABLE))
                ->set([
                    $db->quoteName('artist')             . ' = ' . $db->quote($artist),
                    $db->quoteName('title')              . ' = ' . $db->quote($title),
                    $db->quoteName('label')              . ' = ' . $nullOrStr($label),
                    $db->quoteName('cancon')             . ' = ' . ($cancon ? 1 : 0),
                    $db->quoteName('plays')              . ' = ' . (int) $plays,
                    $db->quoteName('plays_lw')           . ' = ' . (int) $playsLw,
                    $db->quoteName('streams')            . ' = ' . (int) $streams,
                    $db->quoteName('streams_lw')         . ' = ' . (int) $streamsLw,
                    $db->quoteName('streams_market')     . ' = ' . (int) $streamsMarket,
                    $db->quoteName('streams_market_lw')  . ' = ' . (int) $streamsMarketLw,
                    $db->quoteName('market_spins_tw')    . ' = ' . $nullOrInt($marketSpinsTw),
                    $db->quoteName('market_stations_tw') . ' = ' . $nullOrInt($marketStationsTw),
                    $db->quoteName('hist_spins')         . ' = ' . $nullOrInt($histSpins),
                    $db->quoteName('first_played')       . ' = ' . $nullOrDate($firstPlayed),
                    $db->quoteName('format_rank')        . ' = ' . $nullOrInt($formatRank),
                    $db->quoteName('release_year')       . ' = ' . $nullOrInt($releaseYear),
                    $db->quoteName('peak_position')      . ' = ' . $nullOrInt($peakPosition),
                    $db->quoteName('weeks_on_chart')     . ' = ' . $nullOrInt($weeksOnChart),
                    $db->quoteName('position_lw')        . ' = ' . $nullOrInt($positionLw),
                    $db->quoteName('updated_at')         . ' = ' . $db->quote($now),
                    // Preserve existing category_tw and category_code — do NOT overwrite.
                ])
                ->where($db->quoteName('id') . ' = ' . (int) $existing->id);
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName(self::TABLE))
                ->columns($db->quoteName([
                    'week_date', 'source', 'position', 'position_lw',
                    'artist', 'title', 'label', 'cancon',
                    'plays', 'plays_lw', 'streams', 'streams_lw',
                    'streams_market', 'streams_market_lw',
                    'market_spins_tw', 'market_stations_tw',
                    'hist_spins', 'first_played', 'format_rank', 'release_year',
                    'peak_position', 'weeks_on_chart',
                    'created_at', 'updated_at',
                ]))
                ->values(implode(',', [
                    $db->quote($weekDate),
                    $db->quote($source),
                    (int) $position,
                    $nullOrInt($positionLw),
                    $db->quote($artist),
                    $db->quote($title),
                    $nullOrStr($label),
                    ($cancon ? 1 : 0),
                    (int) $plays,
                    (int) $playsLw,
                    (int) $streams,
                    (int) $streamsLw,
                    (int) $streamsMarket,
                    (int) $streamsMarketLw,
                    $nullOrInt($marketSpinsTw),
                    $nullOrInt($marketStationsTw),
                    $nullOrInt($histSpins),
                    $nullOrDate($firstPlayed),
                    $nullOrInt($formatRank),
                    $nullOrInt($releaseYear),
                    $nullOrInt($peakPosition),
                    $nullOrInt($weeksOnChart),
                    $db->quote($now),
                    $db->quote($now),
                ]));
        }

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }
}
