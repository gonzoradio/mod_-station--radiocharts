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
        'national'          => 'NationalPlaylist',
        'station'           => 'StationPlaylist',
        'streaming_station' => 'StreamingDataStation',
        'streaming_market'  => 'StreamingDataMarket',
        'musicmaster'       => 'MusicMasterCSV',
        'billboard'         => 'BillboardChart',
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
        $a = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $a);
        $a = preg_replace('/\s+/', ' ', $a);
        $a = preg_replace('/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i', '', $a);
        $t = preg_replace('/\s*(f\/|feat\.|ft\.|featuring)\s*.+$/i', '', $t);
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
            $s = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $s);
            $s = preg_replace('/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i', '', $s);
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
            $s = preg_replace('/\s*(f\/|feat\.|ft\.|featuring).*/i', '', $s);
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
     * Music Master CSV parser.
     *
     * Returns an array keyed by normalize(artist, title), each entry:
     *   ['artist', 'title', 'weeks', 'tw_cat', 'cat_code', 'spins']
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
            $catCode  = trim($row[4] ?? '');
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
     * Luminate Streaming Station CSV: one header row, then 6 rows per song
     * (Airplay Spins CW/LW, Airplay Audience CW/LW, Streams CW/LW).
     * Returns map: normalize(artist,title) => ['CANADA' => streams_this_week]
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
     *   TW, NW, Artist, Title, WEEKS, CAT, Spins ATD,
     *   #Streams CA, #Streams Van, #Spins TW, #Stns TW, Avg Spins,
     *   MB Cht, Rk, Peak, BB SJ Chart, Freq/Listen ATD, Impres ATD
     *
     * @param  string $dataDir  Absolute path to the data directory.
     * @return array  ['meta' => [...], 'rows' => [...]]
     */
    public static function getCombinedRows($dataDir)
    {
        $stationFile   = self::getLatestFile($dataDir, 'station');
        $nationalFile  = self::getLatestFile($dataDir, 'national');
        $strmMktFile   = self::getLatestFile($dataDir, 'streaming_market');
        $strmStaFile   = self::getLatestFile($dataDir, 'streaming_station');
        $mmFile        = self::getLatestFile($dataDir, 'musicmaster');

        // --- Station Playlist (primary source: Spins ATD, Format Rank, song list) ---
        $playlist = $stationFile ? self::parseStationPlaylistCsv($stationFile) : [];

        // --- National Playlist (national spins, station count, national rank) ---
        $nationalRows = $nationalFile ? self::parseNationalCsv($nationalFile) : [];
        $nationalIdx  = [];
        foreach ($nationalRows as $nr) {
            $artist = $nr['Artist'] ?? '';
            $title  = $nr['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $key              = self::normalize($artist, $title);
            $nationalIdx[$key] = $nr;
        }

        // --- Music Master (TW category, WEEKS, CAT code) ---
        $mmData = $mmFile ? self::parseMusicMasterCsv($mmFile) : [];

        // --- Streaming data (prefer market CSV for both CA and Vancouver) ---
        $streamingIdx = [];
        if ($strmMktFile) {
            $streamingIdx = self::parseLuminateMarketCsv($strmMktFile);
        } elseif ($strmStaFile) {
            $streamingIdx = self::parseLuminateCsv($strmStaFile);
        }

        // --- Report meta ---
        $reportMeta = $stationFile ? self::getReportMeta($stationFile) : '';

        // Helper: fuzzy-lookup streaming data by artist/title
        $findStreaming = function ($artist, $title) use ($streamingIdx) {
            $key = self::normalize($artist, $title);
            if (isset($streamingIdx[$key])) {
                return $streamingIdx[$key];
            }
            foreach ($streamingIdx as $sKey => $sVal) {
                [$sa, $st] = explode('|', $sKey . '|', 2);
                if (self::artistsMatch($artist, $sa) && self::titlesMatch($title, $st)) {
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
                if (self::artistsMatch($artist, $mVal['artist']) && self::titlesMatch($title, $mVal['title'])) {
                    return $mVal;
                }
            }
            return null;
        };

        // Helper: fuzzy-lookup national data by artist/title
        $findNational = function ($artist, $title) use ($nationalIdx) {
            $key = self::normalize($artist, $title);
            if (isset($nationalIdx[$key])) {
                return $nationalIdx[$key];
            }
            foreach ($nationalIdx as $nKey => $nVal) {
                $nArtist = $nVal['Artist'] ?? '';
                $nTitle  = $nVal['Title'] ?? '';
                if (self::artistsMatch($artist, $nArtist) && self::titlesMatch($title, $nTitle)) {
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
        $buildRow = function ($artist, $title, $pl, $mm, $nat, $s) use ($getHistorical, $getFormatRank) {
            // Streaming
            $streamsCa  = $s['CANADA'] ?? '';
            $streamsVan = $s['MARKET'] ?? '';

            // National data
            $natSpinsTW = '';
            $natStnsOn  = '';
            $natRk      = '';
            $natPeak    = '';
            if ($nat) {
                // Spins_TW key from composite header parsing
                $natSpinsTW = $nat['Spins_TW'] ?? '';
                // Stations_On from composite header
                $natStnsOn  = $nat['Stations_On'] ?? '';
                // Rank_TW = national chart position this week
                $natRk      = $nat['Rank_TW'] ?? '';
                // No reliable Peak source yet – leave blank
            }

            // Avg Spins = #Spins TW / #Stns TW (station-level average)
            $avgSpins = '';
            $spinsNum = intval(str_replace(',', '', $natSpinsTW));
            $stnsNum  = intval(str_replace(',', '', $natStnsOn));
            if ($spinsNum > 0 && $stnsNum > 0) {
                $avgSpins = (string) round($spinsNum / $stnsNum);
            }

            // Music Master
            $weeks   = $mm ? $mm['weeks']    : '';
            $twCat   = $mm ? $mm['tw_cat']   : '';
            $catCode = $mm ? $mm['cat_code'] : '';

            // Spins ATD = station's all-time spins from Station Playlist "Hist Spins"
            $spinsAtd = $pl ? $getHistorical($pl, 'Hist Spins') : '';

            // Format Comparison Rank (station's format chart position)
            $formatRk = $pl ? $getFormatRank($pl) : '';
            // Use national rank if format rank is unavailable
            $rk = ($formatRk !== '') ? $formatRk : $natRk;

            return [
                'TW'             => $twCat,
                'NW'             => '',
                'Artist'         => $artist,
                'Title'          => $title,
                'WEEKS'          => $weeks,
                'CAT'            => $catCode,
                'Spins ATD'      => $spinsAtd,
                '#Streams CA'    => $streamsCa,
                '#Streams Van'   => $streamsVan,
                '#Spins TW'      => $natSpinsTW,
                '#Stns TW'       => $natStnsOn,
                'Avg Spins'      => $avgSpins,
                'MB Cht'         => '',
                'Rk'             => $rk,
                'Peak'           => $natPeak,
                'BB SJ Chart'    => '',
                'Freq/Listen ATD'=> '',
                'Impres ATD'     => '',
            ];
        };

        $final        = [];
        $localKeysSet = [];

        // --- Primary: Station Playlist songs ---
        foreach ($playlist as $pl) {
            $artist = $pl['Artist'] ?? '';
            $title  = $pl['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $key              = self::normalize($artist, $title);
            $localKeysSet[$key] = true;

            $mm  = $findMM($artist, $title);
            $nat = $findNational($artist, $title);
            $s   = $findStreaming($artist, $title);

            $final[] = $buildRow($artist, $title, $pl, $mm, $nat, $s);
        }

        // --- Secondary: MusicMaster-only songs not in Station Playlist ---
        foreach ($mmData as $key => $mm) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist          = $mm['artist'];
            $title           = $mm['title'];
            $localKeysSet[$key] = true;
            $nat = $findNational($artist, $title);
            $s   = $findStreaming($artist, $title);
            $final[] = $buildRow($artist, $title, null, $mm, $nat, $s);
        }

        // --- Tertiary: National-only songs (not in station playlist or MusicMaster) ---
        foreach ($nationalIdx as $key => $nat) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist          = $nat['Artist'] ?? '';
            $title           = $nat['Title'] ?? '';
            $localKeysSet[$key] = true;
            $s = $findStreaming($artist, $title);
            $final[] = $buildRow($artist, $title, null, null, $nat, $s);
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
