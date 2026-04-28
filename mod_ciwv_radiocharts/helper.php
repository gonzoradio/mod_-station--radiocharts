<?php
defined('_JEXEC') or die;

class ModCiwvRadiochartsHelper
{
    // Full PD category list, in display/sort order
    public static $twCategories = ['J', 'P', 'H', 'PC2', 'PC3', 'X2', 'X3', 'Z2', 'Z3', 'Q', 'ADD', 'OUT'];

    // "Next week" categories include question-mark variants
    public static $nwCategories = [
        'J', 'P', 'H', 'PC2', 'PC3', 'X2', 'X3', 'Z2', 'Z3', 'Q', 'ADD', 'OUT',
        'J?', 'P?', 'H?', 'PC2?', 'PC3?', 'X2?', 'X3?', 'Z2?', 'Z3?', 'Q?', 'OUT?',
    ];

    // CAT/CODE options (Music Master sub-category codes)
    public static $catOptions = ['1', '2', '3', 'S', 'PSG', 'G', 'F', 'GS', 'GP', 'P', 'V', 'T', 'TG', 'SP', 'TS', 'GT'];

    public static $csvNames = [
        'national_sj' => 'NationalPlaylist_SJ',
        'national_ac' => 'NationalPlaylist_AC',
        'streaming'   => 'Streaming',
        'station'     => 'StationPlaylist',
        'musicmaster' => 'MusicMasterCSV',
        'billboard'   => 'BillboardChart',
    ];

    // Source-group constants: identify which CSV pass contributed a row.
    // Used to ensure Station Playlist songs sort before national-only songs.
    const SRC_STATION  = 0; // Primary:    Station Playlist
    const SRC_MM_ONLY  = 1; // Secondary:  MusicMaster-only (not in Station Playlist)
    const SRC_SJ_ONLY  = 2; // Tertiary:   SJ national-only (not in station or MM)
    const SRC_AC_ONLY  = 3; // Quaternary: AC national-only (not in any above source)


    public static function normalize($artist, $title)
    {
        $a = mb_strtolower(trim($artist));
        $t = mb_strtolower(trim($title));
        $a = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $a);
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        $a = str_replace('"', '', $a);
        $t = str_replace('"', '', $t);
        // Strip featured-artist suffix BEFORE converting separators, so that "f/"
        // (featuring via slash) is caught before "/" is turned into ",".
        // Period after ft/feat is optional to handle "FT" as well as "FT.".
        $a = preg_replace('/\s*(feat\.?|ft\.?|featuring|f\/)\s*.+$/i', '', $a);
        $a = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $a);
        $a = preg_replace('/\s+/', ' ', $a);
        $t = preg_replace('/\s*(f\/|feat\.?|ft\.?|featuring)\s*.+$/i', '', $t);
        $t = preg_replace('/\.\.\.$/', '', $t);
        $t = preg_replace('/[\"\'\(\)\[\]\.,!?\-]/', '', $t);
        $a = trim($a, " ,");
        $t = trim($t);
        return $a . '|' . $t;
    }

    public static function artistsMatch($a1, $a2)
    {
        $norm = function ($s) {
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($s));
            // Strip featured-artist suffix BEFORE converting separators, so that
            // "f/" is caught before "/" is turned into ",".
            // Period after ft/feat is optional to handle "FT" as well as "FT.".
            $s = preg_replace('/\s*(feat\.?|ft\.?|featuring|f\/)\s*.+$/i', '', $s);
            $s = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $s);
            return array_unique(array_filter(array_map('trim', explode(',', $s))));
        };
        $arr1 = $norm($a1);
        $arr2 = $norm($a2);
        if (empty($arr1) || empty($arr2)) {
            return false;
        }
        return count(array_intersect($arr1, $arr2)) > 0;
    }

    public static function titlesMatch($t1, $t2)
    {
        $norm = function ($s) {
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($s));
            // Period after ft/feat is optional to handle "FT" as well as "FT.".
            $s = preg_replace('/\s*(f\/|feat\.?|ft\.?|featuring).*/i', '', $s);
            $s = trim(rtrim($s, " .,'\"!?-"));
            return preg_replace('/[\"\'\(\)\[\]\.,!?\-]/', '', $s);
        };
        $n1 = $norm($t1);
        $n2 = $norm($t2);
        if (stripos($n1, $n2) === 0 || stripos($n2, $n1) === 0) {
            return true;
        }
        if (levenshtein($n1, $n2) <= 2 && abs(strlen($n1) - strlen($n2)) <= 2) {
            return true;
        }
        return $n1 === $n2;
    }

    /**
     * Loose artist match based on the primary artist's last name (last significant
     * word, ignoring honorific suffixes such as JR/SR/II/III).  Returns true when
     * both surnames are identical or differ by at most one edit, providing a
     * reliable safety-check for cases where the full-name comparison fails due to
     * initials (GM vs GABRIEL MARK) or minor typos (BOYNTON vs BOYTON).
     *
     * Intentionally ignores featured artists so that
     * "CORY WONG" matches "CORY WONG/STEPHEN DAY", etc.
     */
    public static function surnameMatch($a1, $a2)
    {
        $surname = function ($s) {
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($s)));
            // Strip featured-artist clause first
            $s = preg_replace('/\s*(feat\.?|ft\.?|featuring|f\/)\s*.+$/i', '', $s);
            // Take only the primary artist (segment before first / or ,)
            $parts = preg_split('/\s*[\/,]\s*/', $s, 2);
            $primary = trim($parts[0] ?? $s);
            $words = preg_split('/\s+/', $primary);
            $words = array_values(array_filter($words));
            // Drop trailing honorific suffixes (jr, sr, ii, iii, iv)
            $honorifics = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv'];
            while (!empty($words) && in_array(rtrim(end($words), '.'), $honorifics, true)) {
                array_pop($words);
            }
            if (empty($words)) {
                return '';
            }
            return rtrim(end($words), '.');
        };
        $s1 = $surname($a1);
        $s2 = $surname($a2);
        // Require at least 3 chars to avoid spurious matches on initials
        if (strlen($s1) < 3 || strlen($s2) < 3) {
            return false;
        }
        return levenshtein($s1, $s2) <= 1;
    }

    // ── CSV parsers ──────────────────────────────────────────────────────────

    /**
     * Station Playlist CSV: skip rows 1–3, then read 2-row composite header.
     * Returns array of associative rows keyed by "Section_Sub" composite names.
     */
    public static function parseStationPlaylistCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $f = fopen($filename, 'r');
        if (!$f) {
            return [];
        }
        fgetcsv($f); fgetcsv($f); fgetcsv($f);
        $sectionHeader = fgetcsv($f);
        $subHeader     = fgetcsv($f);
        $keys    = [];
        $section = '';
        foreach ($sectionHeader as $i => $sect) {
            $sect = trim($sect, " \t\n\r\0\x0B\"");
            if ($sect !== '') {
                $section = $sect;
            }
            $sub = trim($subHeader[$i] ?? '', " \t\n\r\0\x0B\"");
            if ($section && $sub) {
                $keys[] = $section . '_' . $sub;
            } elseif ($section && !$sub) {
                $keys[] = $section;
            } elseif (!$section && $sub) {
                $keys[] = $sub;
            } else {
                $keys[] = 'col_' . $i;
            }
        }
        $rows = [];
        while (($data = fgetcsv($f)) !== false) {
            if (count(array_filter($data)) === 0) {
                continue;
            }
            $row = [];
            foreach ($keys as $i => $k) {
                $row[$k] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($f);
        return $rows;
    }

    /**
     * National Playlist CSV: same composite-header structure as Station Playlist.
     * Returns keyed rows; useful fields:
     *   Rank_TW, Artist, Title, Cancon, Spins_TW, Stations_On, Avg. Station Rotations_TW
     */
    public static function parseNationalCsv($filename)
    {
        return self::parseStationPlaylistCsv($filename);
    }

    /**
     * National Playlist – Smooth Jazz AC CSV parser.
     *
     * Uses the same composite-header structure but overrides Spins_TW and
     * Stations_On with positional column reads to handle files where the
     * column label is "Stns" instead of "Stations", or where columns shift.
     *
     * Column positions (1-indexed, per Mediabase SJ export format):
     *   col  9 (index 8) = Spins TW
     *   col 22 (index 21) = Stations On
     */
    public static function parseNationalSjCsv($filename)
    {
        return self::parseNationalCsvWithPositions($filename, 8, 21);
    }

    /**
     * Internal helper: parse a national CSV with composite headers, then
     * override Spins_TW and Stations_On from specific zero-based column indices.
     */
    private static function parseNationalCsvWithPositions($filename, $spinsCol, $stationsCol)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $f = fopen($filename, 'r');
        if (!$f) {
            return [];
        }
        fgetcsv($f); fgetcsv($f); fgetcsv($f);
        $sectionHeader = fgetcsv($f);
        $subHeader     = fgetcsv($f);
        $keys    = [];
        $section = '';
        foreach ($sectionHeader as $i => $sect) {
            $sect = trim($sect, " \t\n\r\0\x0B\"");
            if ($sect !== '') {
                $section = $sect;
            }
            $sub = trim($subHeader[$i] ?? '', " \t\n\r\0\x0B\"");
            if ($section && $sub) {
                $keys[] = $section . '_' . $sub;
            } elseif ($section && !$sub) {
                $keys[] = $section;
            } elseif (!$section && $sub) {
                $keys[] = $sub;
            } else {
                $keys[] = 'col_' . $i;
            }
        }
        $rows = [];
        while (($data = fgetcsv($f)) !== false) {
            if (count(array_filter($data)) === 0) {
                continue;
            }
            $row = [];
            foreach ($keys as $i => $k) {
                $row[$k] = $data[$i] ?? '';
            }
            // Positional overrides for reliable extraction regardless of header label
            $row['Spins_TW']    = trim($data[$spinsCol] ?? '');
            $row['Stations_On'] = trim($data[$stationsCol] ?? '');
            $rows[] = $row;
        }
        fclose($f);
        return $rows;
    }

    /**
     * Music Master CSV parser.
     *
     * Returns an associative array keyed by normalize(artist, title), each value:
     *   [
     *     'artist'   => string,  // original artist text
     *     'title'    => string,  // original title text
     *     'weeks'    => string,  // WKS column value
     *     'tw_cat'   => string,  // mapped dashboard TW category (e.g. 'J', 'PC2')
     *     'cat_code' => string,  // code/sub-category (col 5, e.g. '2', 'GT', 'S')
     *     'spins'    => string,  // SPINS column value
     *   ]
     *
     * The CSV has multiple sections separated by blank rows and repeating
     * "CAT.,ARTIST,TITLE,WKS,,[code],SPINS,," header rows.
     * Song rows: col0=category, col1=artist, col2=title, col3=weeks, col4=code, col5=spins.
     */
    public static function parseMusicMasterCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $result = [];
        $fh     = fopen($filename, 'r');
        if (!$fh) {
            return [];
        }
        // Skip first row (metadata, e.g. "PLAYLIST WEEK OF …")
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            // Skip empty rows
            if (count(array_filter(array_map('trim', $row))) === 0) {
                continue;
            }
            $col0 = trim($row[0] ?? '');
            $col1 = trim($row[1] ?? '');
            $col2 = trim($row[2] ?? '');
            // Skip header rows (CAT.,ARTIST,TITLE…) and section labels
            if (strcasecmp($col0, 'CAT.') === 0) {
                continue;
            }
            // Skip pure section label rows (artist and title are both empty)
            if ($col1 === '' && $col2 === '') {
                continue;
            }
            $artist   = $col1;
            $title    = $col2;
            $weeks    = trim($row[3] ?? '');
            $catCode  = strtoupper(trim($row[4] ?? ''));
            $spins    = trim($row[5] ?? '');
            // Map MusicMaster category code to module TW category
            $twCat = self::normaliseMmCategory($col0);
            $key   = self::normalize($artist, $title);
            // Only add if artist and title are non-empty
            if ($artist !== '' && $title !== '') {
                $result[$key] = [
                    'artist'   => $artist,
                    'title'    => $title,
                    'weeks'    => $weeks,
                    'tw_cat'   => $twCat,
                    'cat_code' => $catCode,
                    'spins'    => $spins,
                ];
            }
        }
        fclose($fh);
        return $result;
    }

    /**
     * Map a Music Master category abbreviation to the dashboard TW category.
     */
    private static function normaliseMmCategory($mmCat)
    {
        $mm = strtoupper(trim($mmCat));
        $map = [
            'J'    => 'J',
            'P'    => 'P',
            'H'    => 'H',
            'PC2'  => 'PC2',
            'PC3'  => 'PC3',
            'X2'   => 'X2',
            'X3'   => 'X3',
            'Z2'   => 'Z2',
            'Z3'   => 'Z3',
            'Q'    => 'Q',
            'ADD'  => 'ADD',
            'OUT'  => 'OUT',
        ];
        return $map[$mm] ?? '';
    }

    /**
     * Luminate Streaming CSV (new format): 2 header rows, then one row per song.
     *
     * Column positions (1-indexed per data-column-definitions.md):
     *   1:1  (index 0)  – Title
     *   1:2  (index 1)  – Artist
     *   1:14 (index 13) – National Streams TW           → CANADA
     *   1:15 (index 14) – % Change (national, vs LW)    → CANADA_PCT  (used for StreamsCaDir)
     *   1:18 (index 17) – Local Market Streams TW       → MARKET
     *   1:19 (index 18) – % Change - Market (vs LW)     → MARKET_PCT  (used for StreamsVanDir)
     *
     * Returns map: normalize(artist, title) =>
     *   ['CANADA' => streams_tw, 'CANADA_PCT' => pct_change, 'MARKET' => mkt_streams_tw, 'MARKET_PCT' => mkt_pct_change]
     */
    public static function parseStreamingCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $fh = fopen($filename, 'r');
        if (!$fh) {
            return [];
        }
        // Skip the two header rows
        fgetcsv($fh);
        fgetcsv($fh);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $title  = trim($row[0] ?? '');
            $artist = trim($row[1] ?? '');
            if ($title === '' && $artist === '') {
                continue;
            }
            $canada    = trim($row[13] ?? '');
            $canadaPct = trim($row[14] ?? ''); // % Change national (1:15)
            $market    = trim($row[17] ?? '');
            $marketPct = trim($row[18] ?? ''); // % Change market (1:19)
            $key        = self::normalize($artist, $title);
            $rows[$key] = [
                'CANADA'     => $canada,
                'CANADA_PCT' => $canadaPct,
                'MARKET'     => $market,
                'MARKET_PCT' => $marketPct,
            ];
        }
        fclose($fh);
        return $rows;
    }

    /**
     * Billboard Chart CSV parser.
     *
     * Format: one header row (Rank, Song, Artist, Last Week, Peak Pos, Weeks on Chart),
     * then one row per song.
     *
     * Returns map: normalize(artist, song) => ['rank' => rank_value]
     */
    public static function parseBillboardCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $fh = fopen($filename, 'r');
        if (!$fh) {
            return [];
        }
        // Skip header row (Rank, Song, Artist, Last Week, Peak Pos, Weeks on Chart)
        if (fgetcsv($fh) === false) {
            fclose($fh);
            return [];
        }

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $rank   = trim($row[0] ?? ''); // col 1:1 – Rank
            $song   = trim($row[1] ?? ''); // col 1:2 – Song
            $artist = trim($row[2] ?? ''); // col 1:3 – Artist
            if ($song === '' && $artist === '') {
                continue;
            }
            $key        = self::normalize($artist, $song);
            $rows[$key] = ['rank' => $rank];
        }
        fclose($fh);
        return $rows;
    }

    /**
     * Luminate Streaming Station CSV: one header row, then 6 rows per song
     * (Airplay Spins CW/LW, Airplay Audience CW/LW, Streams CW/LW).
     * Returns map: normalize(artist,title) => ['CANADA' => streams_this_week]
     *
     * @deprecated Use parseStreamingCsv() with the new Streaming CSV format instead.
     */
    public static function parseLuminateCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $rows = [];
        if (($fh = fopen($filename, 'r')) !== false) {
            $headers = fgetcsv($fh);
            if (!$headers) {
                fclose($fh);
                return [];
            }
            $headers  = array_map('trim', $headers);
            $idxAct   = array_search('Activity', $headers);
            $idxWeek  = array_search('Week', $headers);
            $idxQty   = array_search('Quantity', $headers);
            $idxArt   = array_search('Artist', $headers);
            $idxTit   = array_search('Title', $headers);
            if ($idxArt === false || $idxTit === false) {
                fclose($fh);
                return [];
            }
            $byKey = [];
            while (($row = fgetcsv($fh)) !== false) {
                if (count(array_filter($row)) === 0) {
                    continue;
                }
                $artist = $row[$idxArt] ?? '';
                $title  = $row[$idxTit] ?? '';
                $key    = self::normalize($artist, $title);
                $byKey[$key][] = [
                    'activity' => $idxAct !== false ? ($row[$idxAct] ?? '') : '',
                    'week'     => $idxWeek !== false ? intval($row[$idxWeek] ?? 0) : 0,
                    'qty'      => $idxQty !== false ? ($row[$idxQty] ?? '') : '',
                ];
            }
            fclose($fh);
            foreach ($byKey as $key => $entries) {
                $latestWeek   = -1;
                $latestStreams = '';
                foreach ($entries as $e) {
                    if (strcasecmp(trim($e['activity']), 'Streams') === 0 && $e['week'] > $latestWeek) {
                        $latestWeek   = $e['week'];
                        $latestStreams = $e['qty'];
                    }
                }
                $rows[$key] = ['CANADA' => $latestStreams];
            }
        }
        return $rows;
    }

    /**
     * Luminate Streaming Market CSV: one header row, then 8 rows per song.
     * Empty Market column = Canada national; non-empty Market = local market (e.g. Vancouver, BC).
     * Returns map: normalize(artist,title) => ['CANADA' => ..., 'MARKET' => ...]
     *
     * @deprecated Use parseStreamingCsv() with the new Streaming CSV format instead.
     */
    public static function parseLuminateMarketCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $rows = [];
        if (($fh = fopen($filename, 'r')) !== false) {
            $headers = fgetcsv($fh);
            if (!$headers) {
                fclose($fh);
                return [];
            }
            $headers   = array_map('trim', $headers);
            $idxMkt    = array_search('Market', $headers);
            $idxAct    = array_search('Activity', $headers);
            $idxWeek   = array_search('Week', $headers);
            $idxQty    = array_search('Quantity', $headers);
            $idxArt    = array_search('Artist', $headers);
            $idxTit    = array_search('Title', $headers);
            if ($idxArt === false || $idxTit === false) {
                fclose($fh);
                return [];
            }
            $byKey = [];
            while (($row = fgetcsv($fh)) !== false) {
                if (count(array_filter($row)) === 0) {
                    continue;
                }
                $artist = $row[$idxArt] ?? '';
                $title  = $row[$idxTit] ?? '';
                $key    = self::normalize($artist, $title);
                $market = $idxMkt !== false ? trim($row[$idxMkt] ?? '') : '';
                $byKey[$key][] = [
                    'activity' => $idxAct !== false ? trim($row[$idxAct] ?? '') : '',
                    'week'     => $idxWeek !== false ? intval($row[$idxWeek] ?? 0) : 0,
                    'qty'      => $idxQty !== false ? ($row[$idxQty] ?? '') : '',
                    'market'   => $market,
                ];
            }
            fclose($fh);
            foreach ($byKey as $key => $entries) {
                $latestCanWeek = -1; $latestCanada = '';
                $latestMktWeek = -1; $latestMarket = '';
                foreach ($entries as $e) {
                    if (strcasecmp($e['activity'], 'Streams') !== 0) {
                        continue;
                    }
                    if ($e['market'] === '' && $e['week'] > $latestCanWeek) {
                        $latestCanWeek = $e['week'];
                        $latestCanada  = $e['qty'];
                    }
                    if ($e['market'] !== '' && $e['week'] > $latestMktWeek) {
                        $latestMktWeek = $e['week'];
                        $latestMarket  = $e['qty'];
                    }
                }
                $rows[$key] = ['CANADA' => $latestCanada, 'MARKET' => $latestMarket];
            }
        }
        return $rows;
    }

    /**
     * Generic CSV parser: auto-detects first row with enough columns as the header.
     */
    public static function parseCsv($filename)
    {
        if (!is_readable($filename)) {
            return [];
        }
        $rows    = [];
        $headers = [];
        if (($fh = fopen($filename, 'r')) !== false) {
            while (($data = fgetcsv($fh)) !== false) {
                while (count($data) && $data[count($data) - 1] === '') {
                    array_pop($data);
                }
                if (!$headers) {
                    $tmp = array_map('trim', $data);
                    if (count(array_filter($tmp)) < 2) {
                        continue;
                    }
                    $headers = $tmp;
                    continue;
                }
                $row = [];
                foreach ($headers as $i => $col) {
                    $row[trim($col)] = $data[$i] ?? '';
                }
                $rows[] = $row;
            }
            fclose($fh);
        }
        return $rows;
    }

    // ── Week / meta helpers ──────────────────────────────────────────────────

    /**
     * Extract TW and LW week-start dates (YYYY-MM-DD) from Station Playlist header line 2.
     * Returns [twStart, lwStart] or [null, null] on failure.
     */
    public static function extractWeekDatesFromStationPlaylist($filename)
    {
        if (!is_readable($filename)) {
            return [null, null];
        }
        $f = fopen($filename, 'r');
        if (!$f) {
            return [null, null];
        }
        fgets($f);
        $header = fgets($f);
        fclose($f);
        if (!$header) {
            return [null, null];
        }
        $header = trim($header, "\" \t\n\r\0\x0B");
        // Try "LW: MM/DD/YYYY - MM/DD/YYYY  TW: MM/DD/YYYY - MM/DD/YYYY"
        $re = '/LW:\s*(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})\s+TW:\s*(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/i';
        if (preg_match($re, $header, $m)) {
            return ["{$m[9]}-{$m[7]}-{$m[8]}", "{$m[3]}-{$m[1]}-{$m[2]}"];
        }
        // Fallback: grab first 4 dates
        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $header, $matches) && count($matches[0]) >= 4) {
            $lw_start = "{$matches[3][0]}-{$matches[1][0]}-{$matches[2][0]}";
            $tw_start = "{$matches[3][2]}-{$matches[1][2]}-{$matches[2][2]}";
            return [$tw_start, $lw_start];
        }
        return [null, null];
    }

    /**
     * Return the report meta line from Station Playlist row 2.
     */
    public static function getReportMeta($filename)
    {
        if (!is_readable($filename)) {
            return '';
        }
        $f = fopen($filename, 'r');
        if (!$f) {
            return '';
        }
        fgetcsv($f);
        $metaRow = fgetcsv($f);
        fclose($f);
        $metaLine = trim(implode(' ', (array) $metaRow), "\" \t\n\r\0\x0B");
        return preg_replace('/\s+/', ' ', $metaLine);
    }

    // ── File helpers ─────────────────────────────────────────────────────────

    /**
     * Return the path to the most-recently-uploaded file of a given type,
     * or null if none exists.
     */
    public static function getLatestFile($dataDir, $type)
    {
        if (!isset(self::$csvNames[$type])) {
            return null;
        }
        $prefix = self::$csvNames[$type];
        $files  = glob($dataDir . '/' . $prefix . '_*.csv');
        if (!$files) {
            return null;
        }
        natsort($files);
        return end($files);
    }

    // ── CanCon lookup ────────────────────────────────────────────────────────

    /**
     * Build a set of normalize(artist, title) keys for all songs marked as
     * Canadian Content in the current national CSV files.
     *
     * Used to populate the CC column for saved-week DB snapshots that predate
     * the addition of the `cancon` field to the saved state.
     *
     * @param  string $dataDir  Absolute path to the data directory.
     * @return array  Map of normalize(artist, title) => true for CanCon songs.
     */
    public static function getCanconLookup($dataDir)
    {
        $lookup = [];

        // AC CSV: explicit 'Cancon' column (composite key 'Cancon' at index 7).
        $acFile = self::getLatestFile($dataDir, 'national_ac');
        if ($acFile) {
            foreach (self::parseNationalCsv($acFile) as $row) {
                if (trim($row['Cancon'] ?? '') === 'Yes') {
                    $key = self::normalize($row['Artist'] ?? '', $row['Title'] ?? '');
                    if ($key !== '|') {
                        $lookup[$key] = true;
                    }
                }
            }
        }

        // NOTE: The SJ CSV (NationalPlaylist_SJ) does NOT have a CanCon column.
        // Composite key 'Rank' at index 3 in the SJ CSV is the "up TW" rank-movement
        // indicator ('Yes' = song improved in rank this week), not a CanCon flag.

        // Station Playlist: explicit 'Cancon' column (composite key 'Cancon' at index 4).
        // This covers station-played songs that may not appear on any national chart.
        $stationFile = self::getLatestFile($dataDir, 'station');
        if ($stationFile) {
            foreach (self::parseStationPlaylistCsv($stationFile) as $row) {
                if (trim($row['Cancon'] ?? '') === 'Yes') {
                    $key = self::normalize($row['Artist'] ?? '', $row['Title'] ?? '');
                    if ($key !== '|') {
                        $lookup[$key] = true;
                    }
                }
            }
        }

        return $lookup;
    }

    // ── Main data merge ──────────────────────────────────────────────────────

    /**
     * Build the combined dashboard rows from whichever CSVs are available.
     *
     * Column set matches data/final-output-example.csv:
     *   TW, NW, Artist, Title, WEEKS, CAT, Spins TW (MusicMaster),
     *   Spins ATD, #Streams CA, #Streams Van, #Spins TW, #Stns TW, Avg Spins,
     *   MB Cht, Rk, Peak, BB SJ Chart, Freq/Listen ATD, Impres ATD
     *
     * MB Cht values:
     *   'SJAC'  – Rk/Peak sourced from the Smooth Jazz AC national chart
     *   'CANAC' – song found on the Mainstream AC national chart only
     *   ''      – no national chart match
     *
     * RkGreen is true when the SJ national chart reports the song moved up
     * this week (col_3 === 'Yes' in the SJ composite-header CSV), indicating
     * the Rk cell should be styled green in the dashboard.
     *
     * @param  string $dataDir  Absolute path to the data directory.
     * @return array  ['meta' => [...], 'rows' => [...]]
     */
    public static function getCombinedRows($dataDir)
    {
        $stationFile    = self::getLatestFile($dataDir, 'station');
        $nationalSjFile = self::getLatestFile($dataDir, 'national_sj');
        $nationalAcFile = self::getLatestFile($dataDir, 'national_ac');
        $strmFile       = self::getLatestFile($dataDir, 'streaming');
        $mmFile         = self::getLatestFile($dataDir, 'musicmaster');
        $bbFile         = self::getLatestFile($dataDir, 'billboard');

        // --- Station Playlist (primary source: Spins ATD, Format Rank, song list) ---
        $playlist = $stationFile ? self::parseStationPlaylistCsv($stationFile) : [];

        // --- National Playlist – Smooth Jazz AC (MB Cht = SJAC, Rk, Peak, RkGreen) ---
        $nationalSjRows = $nationalSjFile ? self::parseNationalSjCsv($nationalSjFile) : [];
        $nationalSjIdx  = [];
        foreach ($nationalSjRows as $nr) {
            $artist = $nr['Artist'] ?? '';
            $title  = $nr['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $key               = self::normalize($artist, $title);
            $nationalSjIdx[$key] = $nr;
        }

        // --- National Playlist – Mainstream AC (MB Cht = CANAC, #Spins TW, #Stns TW) ---
        $nationalAcRows = $nationalAcFile ? self::parseNationalCsv($nationalAcFile) : [];

        // --- Music Master (TW category, WEEKS, CAT code, station Spins TW) ---
        $mmData = $mmFile ? self::parseMusicMasterCsv($mmFile) : [];

        // --- Streaming data (new Streaming CSV: both CA national and local market) ---
        $streamingIdx = $strmFile ? self::parseStreamingCsv($strmFile) : [];

        // --- Billboard Chart (BB SJ chart rank for matching songs) ---
        $billboardIdx = $bbFile ? self::parseBillboardCsv($bbFile) : [];

        // --- Report meta ---
        $reportMeta = $stationFile ? self::getReportMeta($stationFile) : '';

        // Helper: fuzzy-lookup streaming data by artist/title
        $findStreaming = function ($artist, $title) use ($streamingIdx) {
            $key = self::normalize($artist, $title);
            if (isset($streamingIdx[$key])) {
                return $streamingIdx[$key];
            }
            foreach ($streamingIdx as $sKey => $sVal) {
                $parts = explode('|', $sKey, 2);
                $sa    = $parts[0] ?? '';
                $st    = $parts[1] ?? '';
                if (self::titlesMatch($title, $st)
                    && (self::artistsMatch($artist, $sa) || self::surnameMatch($artist, $sa))) {
                    return $sVal;
                }
            }
            return [];
        };

        // Helper: fuzzy-lookup Billboard data by artist/title
        $findBillboard = function ($artist, $title) use ($billboardIdx) {
            $key = self::normalize($artist, $title);
            if (isset($billboardIdx[$key])) {
                return $billboardIdx[$key];
            }
            foreach ($billboardIdx as $bKey => $bVal) {
                $parts   = explode('|', $bKey, 2);
                $bArtist = $parts[0] ?? '';
                $bTitle  = $parts[1] ?? '';
                if (self::titlesMatch($title, $bTitle)
                    && (self::artistsMatch($artist, $bArtist) || self::surnameMatch($artist, $bArtist))) {
                    return $bVal;
                }
            }
            return null;
        };

        // Helper: search station-playlist row for a key containing "Historical Data Since" + sub
        $getHistorical = function ($pl, $sub) {
            foreach ($pl as $k => $v) {
                if (stripos($k, 'Historical Data Since') !== false && stripos($k, $sub) !== false) {
                    return $v;
                }
            }
            return '';
        };

        // Helper: search station-playlist row for the Format Comparison Rank key
        $getFormatRank = function ($pl) {
            foreach ($pl as $k => $v) {
                if (stripos($k, 'Format Comparison') !== false && stripos($k, 'Rank') !== false) {
                    return $v;
                }
            }
            return '';
        };

        // Helper: build a single output row
        $buildRow = function ($artist, $title, $pl, $mm, $natSj, $natAc, $s, $bb, $sourceGroup = self::SRC_STATION) use ($getHistorical, $getFormatRank) {
            // Streaming values and % Change direction (cols 13-14, 17-18 in new Streaming CSV)
            $streamsCa    = $s['CANADA'] ?? '';
            $streamsVan   = $s['MARKET'] ?? '';
            $streamsCaDir = '';
            $streamsVanDir = '';

            // Derive streaming direction directly from the CSV % Change columns.
            // A positive % change means streams went UP; negative means DOWN.
            $pctCaDelta = str_replace(',', '', trim((string) ($s['CANADA_PCT'] ?? '')));
            if (is_numeric($pctCaDelta) && (float) $pctCaDelta != 0) {
                $streamsCaDir = ((float) $pctCaDelta > 0) ? 'up' : 'down';
            }
            $pctMktDelta = str_replace(',', '', trim((string) ($s['MARKET_PCT'] ?? '')));
            if (is_numeric($pctMktDelta) && (float) $pctMktDelta != 0) {
                $streamsVanDir = ((float) $pctMktDelta > 0) ? 'up' : 'down';
            }

            // National spins/stations: SJ chart takes priority (SJAC songs), AC is fallback (CANAC songs)
            $natSpinsTW = '';
            $natStnsOn  = '';
            if ($natSj) {
                $natSpinsTW = $natSj['Spins_TW'] ?? '';
                $natStnsOn  = $natSj['Stations_On'] ?? '';
            } elseif ($natAc) {
                $natSpinsTW = $natAc['Spins_TW'] ?? '';
                $natStnsOn  = $natAc['Stations_On'] ?? '';
            }

            // Avg Spins = #Spins TW / #Stns TW (rounded to nearest whole spin)
            $avgSpins = '';
            $spinsNum = abs(intval(str_replace(',', '', $natSpinsTW)));
            $stnsNum  = abs(intval(str_replace(',', '', $natStnsOn)));
            if ($spinsNum > 0 && $stnsNum > 0) {
                $avgSpins = (string) round($spinsNum / $stnsNum);
            }

            // Music Master
            $weeks    = $mm ? $mm['weeks']    : '';
            $twCat    = $mm ? $mm['tw_cat']   : '';
            $catCode  = $mm ? $mm['cat_code'] : '';
            $spinsTw  = $mm ? $mm['spins']    : ''; // station Spins TW from MusicMaster

            // Spins ATD = station's all-time spins from Station Playlist "Hist Spins"
            $spinsAtd = $pl ? $getHistorical($pl, 'Hist Spins') : '';

            // CanCon detection:
            // - AC CSV: explicit 'Cancon' column (composite key 'Cancon' at index 7). ✓
            // - SJ CSV: NO CanCon column. Composite key 'Rank' at index 3 is the "up TW"
            //   rank-movement marker ('Yes' = song improved rank this week), NOT CanCon.
            // - Station Playlist: explicit 'Cancon' column (composite key 'Cancon' at index 4).
            // - MusicMaster TW categories PC2 and PC3 indicate CanCon.
            $isCancon = false;
            if ($natAc && trim($natAc['Cancon'] ?? '') === 'Yes') {
                $isCancon = true;
            }
            if (!$isCancon && $pl && trim($pl['Cancon'] ?? '') === 'Yes') {
                $isCancon = true;
            }
            if (!$isCancon && in_array($twCat, ['PC2', 'PC3'], true)) {
                $isCancon = true;
            }

            // Week-over-week direction helper: compares two numeric strings.
            // Returns 'up', 'down', or '' (no data / no change).
            $wowDir = function ($tw, $lw, $higherIsBetter = true) {
                $t = str_replace(',', '', trim((string) $tw));
                $l = str_replace(',', '', trim((string) $lw));
                if ($t === '' || $l === '' || !is_numeric($t) || !is_numeric($l)) {
                    return '';
                }
                $tf = (float) $t;
                $lf = (float) $l;
                if ($tf == $lf) {
                    return '';
                }
                return ($tf > $lf) === $higherIsBetter ? 'up' : 'down';
            };

            // Helper: convert a CSV +/- delta string to a direction indicator.
            $dirFromDelta = function ($delta) {
                $v = str_replace(',', '', trim((string) $delta));
                if (!is_numeric($v) || (float) $v == 0) {
                    return '';
                }
                return ((float) $v > 0) ? 'up' : 'down';
            };

            // Station Playlist spins direction: composite key 'Spins_+/-'
            // (user-facing col 10 / 0-based index 9 in the sub-header row).
            $spinsTwDir = $pl ? $dirFromDelta($pl['Spins_+/-'] ?? '') : '';

            // National spins direction: derived from each chart's 'Spins_+/-' composite key.
            // SJ: col 11 (0-based index 10); AC: col 12 (0-based index 11).
            // Both produce the composite key 'Spins_+/-' after the 2-row header parse.
            // When a song appears on both charts the deltas are combined for a total.
            $natSpinsDir      = '';
            $combinedNatDelta = 0.0;
            $hasNatDelta      = false;
            foreach ([$natSj, $natAc] as $nat) {
                if (!$nat) {
                    continue;
                }
                $v = str_replace(',', '', trim($nat['Spins_+/-'] ?? ''));
                if (is_numeric($v)) {
                    $combinedNatDelta += (float) $v;
                    $hasNatDelta = true;
                }
            }
            if ($hasNatDelta && $combinedNatDelta != 0) {
                $natSpinsDir = $combinedNatDelta > 0 ? 'up' : 'down';
            }

            // CSV-derived direction flags from national chart LW vs TW columns.
            // Composite header keys: Rank_TW / Rank_LW,
            //   Avg. Station Rotations_TW / Avg. Station Rotations_LW
            $rkDir       = '';
            $avgSpinsDir = '';
            $natForDir   = $natSj ?: $natAc;
            if ($natForDir) {
                $rkDir       = $wowDir($natForDir['Rank_TW'] ?? '',                         $natForDir['Rank_LW'] ?? '',                         false); // lower rank = better
                $avgSpinsDir = $wowDir($natForDir['Avg. Station Rotations_TW'] ?? '', $natForDir['Avg. Station Rotations_LW'] ?? '', true);
            }

            // SJ national data – provides MB Cht = SJAC, Rk (SJ format rank), Peak (PK)
            // AC national data – provides MB Cht = CANAC fallback, or Rank_TW as Rk fallback
            // Station playlist Format Comparison Rank is the last Rk fallback.
            $mbCht = '';
            $rk    = '';
            $peak  = '';

            // RkGreen: use the explicit "up TW" column (composite key 'Rank' at index 3)
            // as the primary source — the national CSV sets this to 'Yes' when the song
            // improved in rank this week.  Fall back to the computed $rkDir when the
            // column is absent (e.g. older saved states).
            $rkGreen = $natForDir && trim($natForDir['Rank'] ?? '') === 'Yes';
            if (!$rkGreen) {
                $rkGreen = ($rkDir === 'up');
            }

            if ($natSj) {
                $mbCht   = 'SJAC';
                // SJ chart rank = "Format By Format Rank_Smooth Jazz" column; fall back to Rank_TW
                $sjRk    = $natSj['Format By Format Rank_Smooth Jazz'] ?? '';
                $rk      = ($sjRk !== '' && $sjRk !== '-') ? $sjRk : ($natSj['Rank_TW'] ?? '');
                // Peak = PK column (all-time peak rank on the SJ chart)
                $peak    = $natSj['PK'] ?? '';
            } elseif ($natAc) {
                $mbCht = 'CANAC';
                // Use station format rank as primary Rk, AC national rank as fallback
                $formatRk = $pl ? $getFormatRank($pl) : '';
                $rk       = ($formatRk !== '') ? $formatRk : ($natAc['Rank_TW'] ?? '');
                $peak     = $natAc['PK'] ?? '';
            } else {
                // No national data – use station format comparison rank if available
                $rk = $pl ? $getFormatRank($pl) : '';
            }

            return [
                'TW'              => $twCat,
                'NW'              => '',
                'Cancon'          => $isCancon,
                'Artist'          => $artist,
                'Title'           => $title,
                'WEEKS'           => $weeks,
                'CAT'             => $catCode,
                'Spins TW'        => $spinsTw,
                'SpinsTwDir'      => $spinsTwDir,
                'Spins ATD'       => $spinsAtd,
                '#Streams CA'     => $streamsCa,
                'StreamsCaDir'    => $streamsCaDir,
                '#Streams Van'    => $streamsVan,
                'StreamsVanDir'   => $streamsVanDir,
                '#Spins TW'       => $natSpinsTW,
                'NatSpinsTwDir'   => $natSpinsDir,
                '#Stns TW'        => $natStnsOn,
                'Avg Spins'       => $avgSpins,
                'AvgSpinsDir'     => $avgSpinsDir,
                'MB Cht'          => $mbCht,
                'Rk'              => $rk,
                'RkDir'           => $rkDir,
                'Peak'            => $peak,
                'BB SJ Chart'     => $bb ? ($bb['rank'] ?? '') : '',
                'Freq/Listen ATD' => '',
                'Impres ATD'      => '',
                'RkGreen'         => $rkGreen,
                'SourceGroup'     => $sourceGroup,
            ];
        };

        // ── Unified song slots ───────────────────────────────────────────────────
        //
        // Each entry: ['artist', 'title', 'pl', 'mm', 'natSj', 'natAc', 'src']
        // Built in four passes so every song reaches the output exactly once with
        // the richest available data attached.
        //
        // Pass 1a – Seed from SJ national (forms the national+luminate base).
        // Pass 1b – Fuzzy-merge AC national: augment matching SJ entries or add AC-only.
        // Pass 2  – Merge Station Playlist: enrich matching national entries or prepend new.
        // Pass 3  – Overlay MusicMaster: overlay onto any matching entry or prepend new.
        //
        // Final row order:  station-prepend → MM-prepend → national base.
        // JS re-sorts by TW category with SourceGroup as the tiebreaker, so station
        // songs (SRC_STATION=0) always appear above national-only songs within a category.

        $songs    = [];  // key => [artist, title, pl, mm, natSj, natAc, src]
        $songKeys = [];  // insertion-ordered keys (national base order)

        // Helper: find the key in $songs that fuzzy-matches (artist, title).
        $findSongKey = function ($artist, $title) use (&$songs) {
            $key = self::normalize($artist, $title);
            if (isset($songs[$key])) {
                return $key;
            }
            foreach ($songs as $k => $entry) {
                if (self::titlesMatch($title, $entry['title'])
                    && (self::artistsMatch($artist, $entry['artist']) || self::surnameMatch($artist, $entry['artist']))) {
                    return $k;
                }
            }
            return null;
        };

        // --- Pass 1a: SJ national rows form the initial national base ---
        foreach ($nationalSjIdx as $key => $sjRow) {
            $artist = trim($sjRow['Artist'] ?? '');
            $title  = trim($sjRow['Title'] ?? '');
            if ($artist === '' && $title === '') {
                continue;
            }
            $songs[$key] = [
                'artist' => $artist,
                'title'  => $title,
                'pl'     => null,
                'mm'     => null,
                'natSj'  => $sjRow,
                'natAc'  => null,
                'src'    => self::SRC_SJ_ONLY,
            ];
            $songKeys[] = $key;
        }

        // --- Pass 1b: Merge AC national rows into the national base ---
        foreach ($nationalAcRows as $acRow) {
            $artist = trim($acRow['Artist'] ?? '');
            $title  = trim($acRow['Title'] ?? '');
            if ($artist === '' && $title === '') {
                continue;
            }
            $matchedKey = $findSongKey($artist, $title);
            if ($matchedKey !== null) {
                // Augment existing SJ entry with AC data
                $songs[$matchedKey]['natAc'] = $acRow;
            } else {
                // AC-only song (not on the SJ chart)
                $acKey = self::normalize($artist, $title);
                $songs[$acKey] = [
                    'artist' => $artist,
                    'title'  => $title,
                    'pl'     => null,
                    'mm'     => null,
                    'natSj'  => null,
                    'natAc'  => $acRow,
                    'src'    => self::SRC_AC_ONLY,
                ];
                $songKeys[] = $acKey;
            }
        }

        // --- Pass 2: Merge Station Playlist songs ---
        // Songs matching an existing national entry enrich that entry and adopt
        // the station's artist/title spelling as the canonical name.
        // Station-only songs (no national match) are collected for prepending.
        $stationPrependKeys = [];

        foreach ($playlist as $plRow) {
            $artist = trim($plRow['Artist'] ?? '');
            $title  = trim($plRow['Title'] ?? '');
            if ($artist === '' && $title === '') {
                continue;
            }
            $matchedKey = $findSongKey($artist, $title);
            if ($matchedKey !== null) {
                $songs[$matchedKey]['pl']     = $plRow;
                $songs[$matchedKey]['src']    = self::SRC_STATION;
                $songs[$matchedKey]['artist'] = $artist;
                $songs[$matchedKey]['title']  = $title;
            } else {
                $stKey = self::normalize($artist, $title);
                if (!isset($songs[$stKey])) {
                    $songs[$stKey] = [
                        'artist' => $artist,
                        'title'  => $title,
                        'pl'     => $plRow,
                        'mm'     => null,
                        'natSj'  => null,
                        'natAc'  => null,
                        'src'    => self::SRC_STATION,
                    ];
                    $stationPrependKeys[] = $stKey;
                }
            }
        }

        // --- Pass 3: Overlay MusicMaster data ---
        // MM data (TW category, weeks, code, spins) is overlaid on any existing entry.
        // MM-only songs (not in national or station) are collected for prepending after
        // station-only songs.
        $mmPrependKeys = [];

        foreach ($mmData as $mmKey => $mm) {
            $matchedKey = $findSongKey($mm['artist'], $mm['title']);
            if ($matchedKey !== null) {
                $songs[$matchedKey]['mm'] = $mm;
            } else {
                if (!isset($songs[$mmKey])) {
                    $songs[$mmKey] = [
                        'artist' => $mm['artist'],
                        'title'  => $mm['title'],
                        'pl'     => null,
                        'mm'     => $mm,
                        'natSj'  => null,
                        'natAc'  => null,
                        'src'    => self::SRC_MM_ONLY,
                    ];
                    $mmPrependKeys[] = $mmKey;
                }
            }
        }

        // --- Build final rows ---
        // Row order: station-only prepend → MM-only prepend → national base.
        // National entries enriched by station/MM data retain their national position
        // here but are promoted to the top of each category by JS via SourceGroup.
        $orderedKeys = array_merge($stationPrependKeys, $mmPrependKeys, $songKeys);

        $final = [];
        foreach ($orderedKeys as $key) {
            // Defensive: skip if a key was registered but the entry was later
            // overwritten via exact-key collision during AC or station merging.
            if (!isset($songs[$key])) {
                continue;
            }
            $entry = $songs[$key];
            $s     = $findStreaming($entry['artist'], $entry['title']);
            $bb    = $findBillboard($entry['artist'], $entry['title']);
            $final[] = $buildRow(
                $entry['artist'],
                $entry['title'],
                $entry['pl'],
                $entry['mm'],
                $entry['natSj'],
                $entry['natAc'],
                $s,
                $bb,
                $entry['src']
            );
        }

        return [
            'meta' => ['report' => $reportMeta],
            'rows' => $final,
        ];
    }

    // ── File upload handler ──────────────────────────────────────────────────

    /**
     * Handle a CSV upload, saving it to $dataDir with a timestamped filename.
     * Returns a status message string.
     */
    public static function handleUpload($file, $type, $dataDir, $allowedTypes)
    {
        if (!in_array($type, $allowedTypes, true)) {
            return 'Error: upload type not allowed.';
        }
        if (!isset(self::$csvNames[$type])) {
            return 'Error: unknown CSV type.';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Error: file upload failed (code ' . ($file['error'] ?? '?') . ').';
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return 'Error: only CSV files are accepted.';
        }
        // Accept common CSV MIME types including what Windows/Excel sends
        $mime         = $file['type'] ?? '';
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/vnd.ms-excel',
        ];
        if ($mime !== '' && !in_array(strtolower($mime), $allowedMimes, true)) {
            return 'Error: unexpected file type ' . htmlspecialchars($mime) . '.';
        }
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
            file_put_contents($dataDir . '/index.html', '<html><body></body></html>');
        }
        $prefix = self::$csvNames[$type];
        $dest   = $dataDir . '/' . $prefix . '_' . date('Ymd_His') . '.csv';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return 'Error: could not save uploaded file.';
        }
        return 'Uploaded: ' . basename($dest);
    }
}
