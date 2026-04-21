<?php
defined('_JEXEC') or die;

class ModCiwvRadiochartsHelper
{
    // Full PD category list, in display/sort order
    public static $twCategories = ['A1', 'J', 'A2', 'P', 'B', 'C', 'D', 'GOLD', 'PC2', 'PC3', 'HOLD', 'ADD', 'Q', 'OUT'];

    // "Next week" categories include question-mark variants
    public static $nwCategories = [
        'A1', 'J', 'A2', 'P', 'B', 'C', 'D', 'GOLD', 'PC2', 'PC3', 'HOLD', 'ADD', 'Q', 'OUT',
        'A1?', 'J?', 'A2?', 'P?', 'B?', 'C?', 'D?', 'GOLD?', 'PC2?', 'PC3?', 'Q?', 'OUT?',
    ];

    // CAT/CODE options (Music Master sub-category codes)
    public static $catOptions = ['1', '2', '3', 'S', 'PSG', 'G', 'F', 'GS', 'GP', 'P', 'V', 'T', 'TG', 'SP', 'TS', 'GT'];

    public static $csvNames = [
        'national_sj' => 'NationalPlaylist_SJ',
        'national_ac' => 'NationalPlaylist_AC',
        'station'     => 'StationPlaylist',
        'streaming'   => 'Streaming',
        'musicmaster' => 'MusicMasterCSV',
        'billboard'   => 'BillboardChart',
    ];

    // ── Normalisation / fuzzy match ──────────────────────────────────────────

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
     * PC is mapped to PC2 by default; the user can override in the dashboard.
     */
    private static function normaliseMmCategory($mmCat)
    {
        $mm = strtoupper(trim($mmCat));
        $map = [
            'J'    => 'J',
            'A1'   => 'A1',
            'A2'   => 'A2',
            'P'    => 'P',
            'B'    => 'B',
            'C'    => 'C',
            'D'    => 'D',
            'GOLD' => 'GOLD',
            'PC'   => 'PC2',
            'PC2'  => 'PC2',
            'PC3'  => 'PC3',
            'HOLD' => 'HOLD',
            'ADD'  => 'ADD',
            'Q'    => 'Q',
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
     *   1:14 (index 13) – National Streams TW  → stored as CANADA
     *   1:18 (index 17) – Local Market Streams TW → stored as MARKET
     *
     * Columns 1:15 (% Change national) and 1:19 (% Change market) are present in
     * the file but not extracted; only the stream counts are needed for the dashboard.
     *
     * Returns map: normalize(artist, title) => ['CANADA' => streams_tw, 'MARKET' => market_streams_tw]
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
            $canada = trim($row[13] ?? '');
            $market = trim($row[17] ?? '');
            $key    = self::normalize($artist, $title);
            $rows[$key] = ['CANADA' => $canada, 'MARKET' => $market];
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
        $stationFile  = self::getLatestFile($dataDir, 'station');
        $nationalSjFile = self::getLatestFile($dataDir, 'national_sj');
        $nationalAcFile = self::getLatestFile($dataDir, 'national_ac');
        $strmFile     = self::getLatestFile($dataDir, 'streaming');
        $mmFile       = self::getLatestFile($dataDir, 'musicmaster');

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
        $nationalAcIdx  = [];
        foreach ($nationalAcRows as $nr) {
            $artist = $nr['Artist'] ?? '';
            $title  = $nr['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $key               = self::normalize($artist, $title);
            $nationalAcIdx[$key] = $nr;
        }

        // --- Music Master (TW category, WEEKS, CAT code, station Spins TW) ---
        $mmData = $mmFile ? self::parseMusicMasterCsv($mmFile) : [];

        // --- Streaming data (new Streaming CSV: both CA national and local market) ---
        $streamingIdx = $strmFile ? self::parseStreamingCsv($strmFile) : [];

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

        // Helper: fuzzy-lookup MusicMaster data by artist/title
        $findMM = function ($artist, $title) use ($mmData) {
            $key = self::normalize($artist, $title);
            if (isset($mmData[$key])) {
                return $mmData[$key];
            }
            foreach ($mmData as $mKey => $mVal) {
                if (self::titlesMatch($title, $mVal['title'])
                    && (self::artistsMatch($artist, $mVal['artist']) || self::surnameMatch($artist, $mVal['artist']))) {
                    return $mVal;
                }
            }
            return null;
        };

        // Helper: fuzzy-lookup SJ national data by artist/title
        $findNationalSj = function ($artist, $title) use ($nationalSjIdx) {
            $key = self::normalize($artist, $title);
            if (isset($nationalSjIdx[$key])) {
                return $nationalSjIdx[$key];
            }
            foreach ($nationalSjIdx as $nKey => $nVal) {
                $nArtist = $nVal['Artist'] ?? '';
                $nTitle  = $nVal['Title'] ?? '';
                if (self::titlesMatch($title, $nTitle)
                    && (self::artistsMatch($artist, $nArtist) || self::surnameMatch($artist, $nArtist))) {
                    return $nVal;
                }
            }
            return null;
        };

        // Helper: fuzzy-lookup AC national data by artist/title
        $findNationalAc = function ($artist, $title) use ($nationalAcIdx) {
            $key = self::normalize($artist, $title);
            if (isset($nationalAcIdx[$key])) {
                return $nationalAcIdx[$key];
            }
            foreach ($nationalAcIdx as $nKey => $nVal) {
                $nArtist = $nVal['Artist'] ?? '';
                $nTitle  = $nVal['Title'] ?? '';
                if (self::titlesMatch($title, $nTitle)
                    && (self::artistsMatch($artist, $nArtist) || self::surnameMatch($artist, $nArtist))) {
                    return $nVal;
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
        $buildRow = function ($artist, $title, $pl, $mm, $natSj, $natAc, $s) use ($getHistorical, $getFormatRank) {
            // Streaming
            $streamsCa  = $s['CANADA'] ?? '';
            $streamsVan = $s['MARKET'] ?? '';

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

            // SJ national data – provides MB Cht = SJAC, Rk (SJ format rank), Peak (PK), RkGreen
            // AC national data – provides MB Cht = CANAC fallback, or Rank_TW as Rk fallback
            // Station playlist Format Comparison Rank is the last Rk fallback.
            $mbCht   = '';
            $rk      = '';
            $peak    = '';
            $rkGreen = false;

            if ($natSj) {
                $mbCht   = 'SJAC';
                // SJ chart rank = "Format By Format Rank_Smooth Jazz" column; fall back to Rank_TW
                $sjRk    = $natSj['Format By Format Rank_Smooth Jazz'] ?? '';
                $rk      = ($sjRk !== '' && $sjRk !== '-') ? $sjRk : ($natSj['Rank_TW'] ?? '');
                // Peak = PK column (all-time peak rank on the SJ chart)
                $peak    = $natSj['PK'] ?? '';
                // col_3 is the "up TW" flag column (no section/sub header in the composite CSV)
                $rkGreen = (trim($natSj['col_3'] ?? '') === 'Yes');
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
                'Artist'          => $artist,
                'Title'           => $title,
                'WEEKS'           => $weeks,
                'CAT'             => $catCode,
                'Spins TW'        => $spinsTw,
                'Spins ATD'       => $spinsAtd,
                '#Streams CA'     => $streamsCa,
                '#Streams Van'    => $streamsVan,
                '#Spins TW'       => $natSpinsTW,
                '#Stns TW'        => $natStnsOn,
                'Avg Spins'       => $avgSpins,
                'MB Cht'          => $mbCht,
                'Rk'              => $rk,
                'Peak'            => $peak,
                'BB SJ Chart'     => '',
                'Freq/Listen ATD' => '',
                'Impres ATD'      => '',
                'RkGreen'         => $rkGreen,
            ];
        };

        $final        = [];
        $localKeysSet = [];
        // Tracks [artist, title] of every row already added, used for fuzzy
        // cross-source deduplication in the secondary and tertiary passes.
        $localSongs   = [];

        // --- Primary: Station Playlist songs ---
        foreach ($playlist as $pl) {
            $artist = $pl['Artist'] ?? '';
            $title  = $pl['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $key              = self::normalize($artist, $title);
            $localKeysSet[$key] = true;
            $localSongs[]       = [$artist, $title];

            $mm    = $findMM($artist, $title);
            $natSj = $findNationalSj($artist, $title);
            $natAc = $findNationalAc($artist, $title);
            $s     = $findStreaming($artist, $title);

            $final[] = $buildRow($artist, $title, $pl, $mm, $natSj, $natAc, $s);
        }

        // --- Secondary: MusicMaster-only songs not in Station Playlist ---
        foreach ($mmData as $key => $mm) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist = $mm['artist'];
            $title  = $mm['title'];
            // Fuzzy dedup: skip if already added under a different artist/title variant
            $dup = false;
            foreach ($localSongs as [$la, $lt]) {
                if (self::titlesMatch($title, $lt)
                    && (self::artistsMatch($artist, $la) || self::surnameMatch($artist, $la))) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $localKeysSet[$key] = true;
            $localSongs[]       = [$artist, $title];
            $natSj = $findNationalSj($artist, $title);
            $natAc = $findNationalAc($artist, $title);
            $s     = $findStreaming($artist, $title);
            $final[] = $buildRow($artist, $title, null, $mm, $natSj, $natAc, $s);
        }

        // --- Tertiary: SJ national-only songs (not in station playlist or MusicMaster) ---
        foreach ($nationalSjIdx as $key => $natSj) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist = $natSj['Artist'] ?? '';
            $title  = $natSj['Title'] ?? '';
            $dup = false;
            foreach ($localSongs as [$la, $lt]) {
                if (self::titlesMatch($title, $lt)
                    && (self::artistsMatch($artist, $la) || self::surnameMatch($artist, $la))) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $localKeysSet[$key] = true;
            $localSongs[]       = [$artist, $title];
            $natAc = $findNationalAc($artist, $title);
            $s     = $findStreaming($artist, $title);
            $final[] = $buildRow($artist, $title, null, null, $natSj, $natAc, $s);
        }

        // --- Quaternary: AC national-only songs (not already added) ---
        foreach ($nationalAcIdx as $key => $natAc) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist = $natAc['Artist'] ?? '';
            $title  = $natAc['Title'] ?? '';
            $dup = false;
            foreach ($localSongs as [$la, $lt]) {
                if (self::titlesMatch($title, $lt)
                    && (self::artistsMatch($artist, $la) || self::surnameMatch($artist, $la))) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $localKeysSet[$key] = true;
            $localSongs[]       = [$artist, $title];
            $s = $findStreaming($artist, $title);
            $final[] = $buildRow($artist, $title, null, null, null, $natAc, $s);
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
