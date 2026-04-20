<?php
defined('_JEXEC') or die;

class ModCiwvRadiochartsHelper
{
    // Full PD category list, in sort order
    public static $twCategories = ['A1', 'J', 'A2', 'P', 'B', 'C', 'D', 'GOLD', 'PC2', 'PC3', 'HOLD', 'ADD', 'Q', 'OUT'];

    // "Next week" categories include question-mark variants
    public static $nwCategories = ['A1', 'J', 'A2', 'P', 'B', 'C', 'D', 'GOLD', 'PC2', 'PC3', 'HOLD', 'ADD', 'Q', 'OUT',
                                   'A1?', 'J?', 'A2?', 'P?', 'B?', 'C?', 'D?', 'GOLD?', 'PC2?', 'PC3?', 'Q?', 'OUT?'];

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
     */
    public static function parseNationalCsv($filename)
    {
        return self::parseStationPlaylistCsv($filename);
    }

    /**
     * Luminate Streaming Station CSV: one header row, then 6 rows per song
     * (3 Activity types × 2 weeks).  Returns map:
     *   normalize(artist,title) => ['CANADA' => streams_this_week]
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
            $headers = array_map('trim', $headers);
            $idxAct   = array_search('Activity', $headers);
            $idxWeek  = array_search('Week', $headers);
            $idxQty   = array_search('Quantity', $headers);
            $idxArt   = array_search('Artist', $headers);
            $idxTit   = array_search('Title', $headers);
            if ($idxArt === false || $idxTit === false) {
                fclose($fh);
                return [];
            }
            // Collect all rows grouped by normalize key
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
                    'artist'   => $artist,
                    'title'    => $title,
                ];
            }
            fclose($fh);
            // For each key, find latest-week Streams quantity
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
     * Market column distinguishes Canada-national vs Vancouver market rows.
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
                $artist  = $row[$idxArt] ?? '';
                $title   = $row[$idxTit] ?? '';
                $key     = self::normalize($artist, $title);
                $market  = $idxMkt !== false ? trim($row[$idxMkt] ?? '') : '';
                $byKey[$key][] = [
                    'activity' => $idxAct !== false ? trim($row[$idxAct] ?? '') : '',
                    'week'     => $idxWeek !== false ? intval($row[$idxWeek] ?? 0) : 0,
                    'qty'      => $idxQty !== false ? ($row[$idxQty] ?? '') : '',
                    'market'   => $market,
                    'artist'   => $artist,
                    'title'    => $title,
                ];
            }
            fclose($fh);
            foreach ($byKey as $key => $entries) {
                $latestCanWeek   = -1; $latestCanada = '';
                $latestMktWeek   = -1; $latestMarket = '';
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
     * @param  string $dataDir  Absolute path to the data directory.
     * @return array  ['meta' => [...], 'rows' => [...]]
     */
    public static function getCombinedRows($dataDir)
    {
        $stationFile   = self::getLatestFile($dataDir, 'station');
        $nationalFile  = self::getLatestFile($dataDir, 'national');
        $strmStaFile   = self::getLatestFile($dataDir, 'streaming_station');
        $strmMktFile   = self::getLatestFile($dataDir, 'streaming_market');

        // --- Station Playlist (primary source) ---
        $playlist = $stationFile ? self::parseStationPlaylistCsv($stationFile) : [];

        // --- National chart (for Rank, Peak, national spins delta) ---
        $nationalRows = [];
        $nationalIdx  = [];
        $nationalColIdx = [];
        if ($nationalFile && is_readable($nationalFile)) {
            $handle = fopen($nationalFile, 'r');
            if ($handle) {
                // Scan for the header row that contains Rank and Artist columns
                while (($row = fgetcsv($handle)) !== false) {
                    $lookup = [];
                    foreach ($row as $i => $cell) {
                        $cellNorm = strtolower(trim(str_replace(['"', "'"], '', $cell)));
                        if (($cellNorm === 'pk' || $cellNorm === 'peak') && !isset($lookup['Peak'])) {
                            $lookup['Peak'] = $i;
                        }
                        if ($cellNorm === 'tw' && !isset($lookup['Rank'])) {
                            $lookup['Rank'] = $i;
                        }
                        if ($cellNorm === 'artist' && !isset($lookup['Artist'])) {
                            $lookup['Artist'] = $i;
                        }
                        if ($cellNorm === 'title' && !isset($lookup['Title'])) {
                            $lookup['Title'] = $i;
                        }
                        if ($cellNorm === '+/-' && !isset($lookup['+/-'])) {
                            $lookup['+/-'] = $i;
                        }
                        if (stripos($cellNorm, 'avg. station rotations') !== false && !isset($lookup['Avg. Station Rotations'])) {
                            $lookup['Avg. Station Rotations'] = $i;
                        }
                    }
                    if (isset($lookup['Rank'], $lookup['Artist'], $lookup['Title'])) {
                        $nationalColIdx = $lookup;
                        break;
                    }
                }
                if ($nationalColIdx) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $artist = $row[$nationalColIdx['Artist']] ?? '';
                        $title  = $row[$nationalColIdx['Title']] ?? '';
                        $key    = self::normalize($artist, $title);
                        $nationalIdx[$key] = $row;
                    }
                }
                fclose($handle);
            }
        }

        // --- Streaming data (prefer market CSV if available, fall back to station CSV) ---
        $streamingIdx = [];
        if ($strmMktFile) {
            $streamingIdx = self::parseLuminateMarketCsv($strmMktFile);
        } elseif ($strmStaFile) {
            $streamingIdx = self::parseLuminateCsv($strmStaFile);
        }

        // --- Report meta ---
        $reportMeta = $stationFile ? self::getReportMeta($stationFile) : '';

        // Helper: look up streaming data for an artist/title pair
        $findStreaming = function ($artist, $title) use ($streamingIdx) {
            $key = self::normalize($artist, $title);
            if (isset($streamingIdx[$key])) {
                return $streamingIdx[$key];
            }
            // Fallback: fuzzy iterate
            foreach ($streamingIdx as $sKey => $sVal) {
                [$sa, $st] = explode('|', $sKey . '|', 2);
                if (self::artistsMatch($artist, $sa) && self::titlesMatch($title, $st)) {
                    return $sVal;
                }
            }
            return [];
        };

        // Helper: search playlist row for the first key containing "Share(%)"
        $getMarketShr = function ($pl) {
            foreach ($pl as $k => $v) {
                if (stripos($k, 'Share(%)') !== false) {
                    return $v;
                }
            }
            return '';
        };

        // Helper: search playlist row for a key containing "Historical Data Since" + sub
        $getHistorical = function ($pl, $sub) {
            foreach ($pl as $k => $v) {
                if (stripos($k, 'Historical Data Since') !== false && stripos($k, $sub) !== false) {
                    return $v;
                }
            }
            return '';
        };

        $final        = [];
        $localKeysSet = [];

        foreach ($playlist as $pl) {
            $artist = $pl['Artist'] ?? '';
            $title  = $pl['Title'] ?? '';
            if ($artist === '' && $title === '') {
                continue;
            }
            $cancon          = $pl['Cancon'] ?? '';
            $year            = $pl['Year'] ?? '';
            $station_rk_lw   = $pl['Station Rank_LW'] ?? '';
            $station_rk_tw   = $pl['Station Rank_TW'] ?? '';
            $lw              = intval($station_rk_lw);
            $tw              = intval($station_rk_tw);
            $spins_tw_raw    = intval($pl['Spins_TW'] ?? 0);
            $spins_ovn       = intval($pl['Dayparts_OVN'] ?? 0);
            $spins_tw        = $spins_tw_raw - $spins_ovn;   // overnight-adjusted
            $daypart_amd     = $pl['Dayparts_AMD'] ?? '';
            $daypart_mid     = $pl['Dayparts_MID'] ?? '';
            $daypart_pmd     = $pl['Dayparts_PMD'] ?? '';
            $daypart_eve     = $pl['Dayparts_EVE'] ?? '';
            $market_shr      = $getMarketShr($pl);
            $first_played    = $getHistorical($pl, 'First Played');
            $atd             = $getHistorical($pl, 'Hist Spins');

            $key = self::normalize($artist, $title);
            $localKeysSet[$key] = true;

            // National chart lookup
            $r        = $nationalIdx[$key] ?? null;
            $natRank  = ($r && isset($nationalColIdx['Rank'])) ? ($r[$nationalColIdx['Rank']] ?? '') : '';
            $natPeak  = ($r && isset($nationalColIdx['Peak'])) ? ($r[$nationalColIdx['Peak']] ?? '') : '';
            $natDelta = ($r && isset($nationalColIdx['+/-'])) ? intval($r[$nationalColIdx['+/-']] ?? 0) : null;

            // Stn Rk UP: movement arrow + national spins threshold labels
            $labels = [];
            if ($lw > 0 && $tw > 0 && $tw < $lw) {
                $labels[] = '▲';
            }
            if ($natDelta !== null) {
                if ($natDelta >= 200) {
                    $labels[] = '+200ntl';
                } elseif ($natDelta >= 150) {
                    $labels[] = '+150ntl';
                } elseif ($natDelta >= 100) {
                    $labels[] = '+100ntl';
                } elseif ($natDelta >= 30) {
                    $labels[] = '+30ntl';
                } elseif ($natDelta <= -200) {
                    $labels[] = '-200ntl';
                } elseif ($natDelta <= -150) {
                    $labels[] = '-150ntl';
                } elseif ($natDelta <= -100) {
                    $labels[] = '-100ntl';
                } elseif ($natDelta <= -30) {
                    $labels[] = '-30ntl';
                }
            }
            $stn_rk_up = implode(' ', $labels);

            // Streaming
            $s       = $findStreaming($artist, $title);
            $canada  = $s['CANADA'] ?? '';
            $market  = $s['MARKET'] ?? '';

            $final[] = [
                'TW'             => '',
                'NW'             => '',
                'Artist'         => $artist,
                'Title'          => $title,
                'CanCon'         => $cancon,
                'Year'           => $year,
                'Stn Rk TW'      => $tw,
                'Stn Rk LW'      => $lw,
                'Stn Rk UP'      => $stn_rk_up,
                'Spins TW'       => $spins_tw,
                '+/-'            => '',
                'AMD'            => $daypart_amd,
                'MID'            => $daypart_mid,
                'PMD'            => $daypart_pmd,
                'EVE'            => $daypart_eve,
                'Market Shr (%)' => $market_shr,
                'First Played'   => $first_played,
                'ATD'            => $atd,
                'Rank'           => $natRank,
                'Peak'           => $natPeak,
                'CANADA'         => $canada,
                'MARKET'         => $market,
            ];
        }

        // Append national-chart-only songs not in the station playlist
        foreach ($nationalIdx as $key => $r) {
            if (isset($localKeysSet[$key])) {
                continue;
            }
            $artist  = $r[$nationalColIdx['Artist']] ?? '';
            $title   = $r[$nationalColIdx['Title']] ?? '';
            $natRank = $r[$nationalColIdx['Rank']] ?? '';
            $natPeak = isset($nationalColIdx['Peak']) ? ($r[$nationalColIdx['Peak']] ?? '') : '';
            $natDelta = isset($nationalColIdx['+/-']) ? ($r[$nationalColIdx['+/-']] ?? '') : '';
            $s       = $findStreaming($artist, $title);

            $final[] = [
                'TW'             => '',
                'NW'             => '',
                'Artist'         => $artist,
                'Title'          => $title,
                'CanCon'         => '',
                'Year'           => '',
                'Stn Rk TW'      => '',
                'Stn Rk LW'      => '',
                'Stn Rk UP'      => '',           // no station rank movement for national-only
                'Spins TW'       => '',
                '+/-'            => $natDelta,
                'AMD'            => '',
                'MID'            => '',
                'PMD'            => '',
                'EVE'            => '',
                'Market Shr (%)' => '',
                'First Played'   => '',
                'ATD'            => '',
                'Rank'           => $natRank,
                'Peak'           => $natPeak,
                'CANADA'         => $s['CANADA'] ?? '',
                'MARKET'         => $s['MARKET'] ?? '',
            ];
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
        // MIME type check (text/csv, text/plain, application/csv)
        $mime = $file['type'] ?? '';
        if ($mime !== '' && !in_array(strtolower($mime), ['text/csv', 'text/plain', 'application/csv'], true)) {
            return 'Error: unexpected file type ' . htmlspecialchars($mime) . '.';
        }
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
            // Protect the folder from direct browsing
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