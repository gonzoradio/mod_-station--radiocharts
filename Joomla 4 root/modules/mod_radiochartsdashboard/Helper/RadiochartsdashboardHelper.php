<?php
namespace Joomla\Module\Radiochartsdashboard\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Module\Radiochartsdashboard\Site\Helper\LuminateHelper;
use Normalizer;

class RadiochartsdashboardHelper {
  // Improved normalization for fuzzy matching
  public static function normalize($artist, $title) {
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

  public static function artistsMatch($a1, $a2) {
    $n1 = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($a1));
    $n2 = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($a2));
    $n1 = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $n1);
    $n2 = preg_replace('/(\s*&\s*|\s*and\s*|,|\s*x\s*|\s*\/\s*)/i', ',', $n2);
    $n1 = preg_replace('/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i', '', $n1);
    $n2 = preg_replace('/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i', '', $n2);
    $arr1 = array_unique(array_filter(array_map('trim', explode(',', $n1))));
    $arr2 = array_unique(array_filter(array_map('trim', explode(',', $n2))));
    if (empty($arr1) || empty($arr2)) return false;
    $overlap = array_intersect($arr1, $arr2);
    return count($overlap) > 0;
  }

  public static function titlesMatch($t1, $t2) {
    $n1 = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($t1));
    $n2 = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($t2));
    $n1 = preg_replace('/\s*(f\/|feat\.|ft\.|featuring).*/i', '', $n1);
    $n2 = preg_replace('/\s*(f\/|feat\.|ft\.|featuring).*/i', '', $n2);
    $n1 = trim(rtrim($n1, " .,'\"!?-"));
    $n2 = trim(rtrim($n2, " .,'\"!?-"));
    $n1 = preg_replace('/[\"\'\(\)\[\]\.,!?\-]/', '', $n1);
    $n2 = preg_replace('/[\"\'\(\)\[\]\.,!?\-]/', '', $n2);
    if (stripos($n1, $n2) === 0 || stripos($n2, $n1) === 0) return true;
    if (levenshtein($n1, $n2) <= 2) return true;
    return $n1 == $n2;
  }

  public static function parseCsvWithDynamicColumns($filename, &$artistColIdx = null, &$titleColIdx = null) {
    if (!is_readable($filename)) return [];
    $rows = [];
    $fieldsByName = [];
    if (($handle = fopen($filename, 'r')) !== false) {
      while (($data = fgetcsv($handle)) !== false) {
        if (count(array_filter($data)) == 0) continue;
        if (empty($fieldsByName)) {
          $maybeHeader = array_map('trim', $data);
          if (in_array('Artist', $maybeHeader) && in_array('Title', $maybeHeader)) {
            $fieldsByName = $maybeHeader;
            $artistColIdx = array_search('Artist', $fieldsByName);
            $titleColIdx = array_search('Title', $fieldsByName);
            if ($artistColIdx === false) $artistColIdx = 4;
            if ($titleColIdx === false) $titleColIdx = 5;
            continue;
          }
          continue;
        }
        $row = [];
        foreach ($fieldsByName as $i => $col) {
          $row[$col] = isset($data[$i]) ? $data[$i] : '';
        }
        $row['__raw'] = $data;
        $rows[] = $row;
      }
      fclose($handle);
    }
    return $rows;
  }

  public static function parseStationPlaylistCsv($filename) {
    if (!is_readable($filename)) return [];
    $f = fopen($filename, 'r');
    if (!$f) return [];
    fgetcsv($f); fgetcsv($f); fgetcsv($f);
    $sectionHeader = fgetcsv($f);
    $subHeader = fgetcsv($f);
    $keys = [];
    $section = '';
    foreach ($sectionHeader as $i => $sect) {
      $sect = trim($sect, " \t\n\r\0\x0B\"");
      if ($sect !== '') $section = $sect;
      $sub = trim($subHeader[$i] ?? '', " \t\n\r\0\x0B\"");
      if ($section && $sub) $keys[] = $section . '_' . $sub;
      elseif ($section && !$sub) $keys[] = $section;
      elseif (!$section && $sub) $keys[] = $sub;
      else $keys[] = 'col_' . $i;
    }
    $rows = [];
    while (($data = fgetcsv($f)) !== false) {
      if (count(array_filter($data)) === 0) continue;
      $row = [];
      foreach ($keys as $i => $k) {
        $row[$k] = $data[$i] ?? '';
      }
      $rows[] = $row;
    }
    fclose($f);
    return $rows;
  }

  public static function parseCsv($filename) {
    if (!is_readable($filename)) return [];
    $rows = [];
    if (($handle = fopen($filename, 'r')) !== false) {
      $headers = [];
      while (($data = fgetcsv($handle)) !== false) {
        while (count($data) && $data[count($data)-1] === '') array_pop($data);
        if (!$headers) {
          $tmp = array_map('trim', $data);
          if (count(array_filter($tmp)) < 2) continue;
          $headers = $tmp;
          continue;
        }
        $row = [];
        foreach ($headers as $i => $col) {
          $col = trim($col);
          $row[$col] = isset($data[$i]) ? $data[$i] : '';
        }
        $rows[] = $row;
      }
      fclose($handle);
    }
    return $rows;
  }

  public static function indexByNormKey($rows, $artistCol, $titleCol) {
    $index = [];
    foreach ($rows as $row) {
      $artist = '';
      $title = '';
      if (isset($row[$artistCol])) $artist = $row[$artistCol];
      if (isset($row[$titleCol])) $title = $row[$titleCol];
      if ($artist === '' && isset($row[4])) $artist = $row[4];
      if ($title === '' && isset($row[5])) $title = $row[5];
      $key = self::normalize($artist ?? '', $title ?? '');
      $index[$key] = $row;
    }
    return $index;
  }

  public static function extractWeekDatesFromStationPlaylist($filename) {
    if (!is_readable($filename)) return [null, null];
    $f = fopen($filename, 'r');
    if (!$f) return [null, null];
    fgets($f);
    $header = fgets($f);
    fclose($f);
    if (!$header) return [null, null];
    $header = trim($header, "\" \t\n\r\0\x0B");
    $re = '/LW:\s*(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})\s+TW:\s*(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/i';
    if (preg_match($re, $header, $m)) {
      $lw_start = "{$m[3]}-{$m[1]}-{$m[2]}";
      $tw_start = "{$m[9]}-{$m[7]}-{$m[8]}";
      return [$tw_start, $lw_start];
    }
    if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $header, $matches) && count($matches[0]) >= 4) {
      $lw_start = "{$matches[3][0]}-{$matches[1][0]}-{$matches[2][0]}";
      $tw_start = "{$matches[3][2]}-{$matches[1][2]}-{$matches[2][2]}";
      return [$tw_start, $lw_start];
    }
    error_log("Station Playlist A2 could not extract week dates: $header");
    return [null, null];
  }

  public static function findPriorWeek($selectedWeek, $db, $table = 'd6f21_radiochartsdashboard_state') {
    $query = $db->getQuery(true)
      ->select('week_start')
      ->from($db->qn($table))
      ->where($db->qn('week_start') . ' < ' . $db->quote($selectedWeek))
      ->order($db->qn('week_start') . ' DESC')
      ->setLimit(1);
    $db->setQuery($query);
    return $db->loadResult() ?: null;
  }

  public static function getReportMeta($filename) {
    if (!is_readable($filename)) return '';
    $f = fopen($filename, 'r');
    if (!$f) return '';
    fgetcsv($f);
    $metaRow = fgetcsv($f);
    fclose($f);
    $metaLine = trim(implode(' ', $metaRow), "\" \t\n\r\0\x0B");
    $metaLine = preg_replace('/\s+/', ' ', $metaLine);
    return $metaLine;
  }

  public static function getCurrentAndLastWeekDates($filename) {
    $metaLine = self::getReportMeta($filename);
    $matches = [];
    preg_match('/TW:\s*([A-Za-z]{3}) (\d{1,2}) - ([A-Za-z]{3}) (\d{1,2})\s*LW:\s*([A-Za-z]{3}) (\d{1,2}) - ([A-Za-z]{3}) (\d{1,2})/', $metaLine, $matches);
    if ($matches) {
      $year = date('Y');
      $twStart = date('Y-m-d', strtotime("$year-{$matches[1]}-{$matches[2]}"));
      $twEnd = date('Y-m-d', strtotime("$year-{$matches[3]}-{$matches[4]}"));
      $lwStart = date('Y-m-d', strtotime("$year-{$matches[5]}-{$matches[6]}"));
      $lwEnd = date('Y-m-d', strtotime("$year-{$matches[7]}-{$matches[8]}"));
      return [$twStart, $twEnd, $lwStart, $lwEnd];
    }
    $twStart = date('Y-m-d', strtotime('monday this week'));
    $twEnd = date('Y-m-d', strtotime('sunday this week'));
    $lwStart = date('Y-m-d', strtotime('monday last week'));
    $lwEnd = date('Y-m-d', strtotime('sunday last week'));
    return [$twStart, $twEnd, $lwStart, $lwEnd];
  }

  public static function getCombinedRows() {
    $base = JPATH_BASE . '/modules/mod_radiochartsdashboard/data/';
    $playlistFile = $base . 'Station Playlist.csv';
    $rollingFile = $base . 'Published Chart.csv';
    $streamingFile = $base . 'Streaming Metrics.csv';

    $playlist = self::parseStationPlaylistCsv($playlistFile);
    $hasRolling = is_readable($rollingFile);
    $rollingIdx = [];
    $colIdx = [];
    if ($hasRolling) {
      $handle = fopen($rollingFile, 'r');
      if ($handle) {
        $header = [];
        while (($row = fgetcsv($handle)) !== false) {
          $lookup = [];
          foreach ($row as $i => $cell) {
            $cellNorm = strtolower(trim(str_replace(['"', "'"], '', $cell)));
            if (($cellNorm === 'pk' || $cellNorm === 'peak') && !isset($lookup['Peak'])) $lookup['Peak'] = $i;
            if ($cellNorm === 'rank' && !isset($lookup['Rank'])) $lookup['Rank'] = $i;
            if ($cellNorm === 'artist' && !isset($lookup['Artist'])) $lookup['Artist'] = $i;
            if ($cellNorm === 'title' && !isset($lookup['Title'])) $lookup['Title'] = $i;
            if ($cellNorm === '+/-' && !isset($lookup['+/-'])) $lookup['+/-'] = $i;
            if (stripos($cellNorm, 'avg. station rotations') !== false && !isset($lookup['Avg. Station Rotations'])) $lookup['Avg. Station Rotations'] = $i;
          }
          if (isset($lookup['Rank'], $lookup['Artist'], $lookup['Title'])) {
            $colIdx = $lookup;
            $header = $row;
            break;
          }
        }
        if ($header) {
          while (($row = fgetcsv($handle)) !== false) {
            $artist = isset($row[$colIdx['Artist']]) ? $row[$colIdx['Artist']] : '';
            $title = isset($row[$colIdx['Title']]) ? $row[$colIdx['Title']] : '';
            $key = self::normalize($artist, $title);
            $rollingIdx[$key] = $row;
          }
        }
        fclose($handle);
      }
    }

    $streaming = self::parseCsv($streamingFile);
    $streamingIdx = self::indexByNormKey($streaming, 'Artist', 'Title');
    $reportMeta = self::getReportMeta($playlistFile);
    list($twStart, $twEnd, $lwStart, $lwEnd) = $hasRolling
      ? self::getCurrentAndLastWeekDates($rollingFile)
      : [null, null, null, null];

    $db = Factory::getDbo();
    $query = $db->getQuery(true)
      ->select('state_json')
      ->from($db->quoteName('d6f21_radiochartsdashboard_state'))
      ->where('week_start = ' . $db->quote($lwStart));
    $db->setQuery($query);
    $savedStateJson = $db->loadResult();
    $savedState = $savedStateJson ? json_decode($savedStateJson, true) : [];

    $savedMap = [];
    foreach ($savedState as $entry) {
      $key = self::normalize($entry['artist'] ?? '', $entry['title'] ?? '');
      $savedMap[$key] = $entry;
    }

    $final = [];
    $localKeysSet = []; // <-- to track local playlist songs

    foreach ($playlist as $pl) {
      $artist = $pl['Artist'] ?? '';
      $title = $pl['Title'] ?? '';
      $cancon = $pl['Cancon'] ?? '';
      $year = $pl['Year'] ?? '';
      $station_rk_lw = $pl['Station Rank_LW'] ?? '';
      $station_rk_tw = $pl['Station Rank_TW'] ?? '';
      $lw = intval($station_rk_lw);
      $tw = intval($station_rk_tw);

      $key = self::normalize($artist, $title);
      $localKeysSet[$key] = true; // <-- record this key for later

      $r = $hasRolling ? ($rollingIdx[$key] ?? null) : null;

      $s = $streamingIdx[$key] ?? null;
      if (!$s) {
        foreach ($streaming as $streamRow) {
          if (
            self::artistsMatch($artist, $streamRow['Artist'] ?? '') &&
            self::titlesMatch($title, $streamRow['Title'] ?? '')
          ) {
            $s = $streamRow;
            break;
          }
        }
      }
      if (!$s && substr($title, -3) === '...') {
        $needle = self::normalize($artist, preg_replace('/\.\.\.$/', '', $title));
        foreach ($streamingIdx as $sKey => $streamRow) {
          if (strpos($sKey, $needle) === 0) {
            $s = $streamRow;
            break;
          }
        }
      }
      if (!$s) $s = [];

      // --- Stn Rk UP Logic ---
      $labels = [];
      if ($lw > 0 && $tw > 0 && $tw < $lw) {
        $labels[] = '<span class="red-up">▲</span>';
      }
      if ($r !== null && isset($r[11])) { // index 11 is Spins +/-
        $spins_delta = intval($r[11]);
        // Positive thresholds
        if ($spins_delta >= 200) {
          $labels[] = '+200ntl';
        } elseif ($spins_delta >= 150) {
          $labels[] = '+150ntl';
        } elseif ($spins_delta >= 100) {
          $labels[] = '+100ntl';
        } elseif ($spins_delta >= 30) {
          $labels[] = '+30ntl';
        }
        // Negative thresholds (in red)
        elseif ($spins_delta <= -200) {
          $labels[] = '<span style="color:red;">-200ntl</span>';
        } elseif ($spins_delta <= -150) {
          $labels[] = '<span style="color:red;">-150ntl</span>';
        } elseif ($spins_delta <= -100) {
          $labels[] = '<span style="color:red;">-100ntl</span>';
        } elseif ($spins_delta <= -30) {
          $labels[] = '<span style="color:red;">-30ntl</span>';
        }
      }
      $stn_rk_up = implode(' ', $labels);

      $spins_tw = intval($pl['Spins_TW'] ?? 0);
      $spins_ovn_tw = intval($pl['Dayparts_OVN'] ?? 0);
      $spins_tw_adj = $spins_tw - $spins_ovn_tw;
      $prev = $savedMap[$key] ?? null;
      $spins_prev = intval($prev['Spins TW'] ?? 0);
      $spins_diff = $spins_tw_adj - $spins_prev;

      $daypart_amd = $pl['Dayparts_AMD'] ?? '';
      $daypart_mid = $pl['Dayparts_MID'] ?? '';
      $daypart_pmd = $pl['Dayparts_PMD'] ?? '';
      $daypart_eve = $pl['Dayparts_EVE'] ?? '';
      $market_shr = '';
      foreach ($pl as $k => $v) {
        if (stripos($k, 'Share(%)') !== false) {
          $market_shr = $v;
          break;
        }
      }
      $first_played = $pl['Historical Data Since: 09/22/2007_First Played'] ?? '';
      $atd = $pl['Historical Data Since: 09/22/2007_Hist Spins'] ?? '';

      $odStreamsCanada = isset($s['CANADA']) ? $s['CANADA'] : 'N/A';
      $odStreamsMarket = isset($s['PETE']) ? $s['PETE'] : 'N/A';

      $tw_val = '';
      $nw_val = '';
      $prev = $savedMap[$key] ?? null;
      if ($prev) {
        $nw = $prev['nw'] ?? '';
        if ($nw !== '' && substr($nw, -1) !== '?') {
          $tw_val = $nw;
        } else {
          $tw_val = $prev['tw'] ?? '';
        }
      }
      $nw_val = '';

      $rank = ($r && isset($colIdx['Rank'])) ? $r[$colIdx['Rank']] ?? '' : '';
      $peak = ($r && isset($colIdx['Peak'])) ? ($r[$colIdx['Peak']] ?? '') : '';
      $format_peak = ($peak !== '') ? $peak : '';

      $final[] = [
        'TW' => $tw_val,
        'NW' => $nw_val,
        'Artist' => $artist,
        'Title' => $title,
        'CanCon' => $cancon,
        'Year' => $year,
        'Stn Rk TW' => $tw,
        'Stn Rk LW' => $lw,
        'Stn Rk UP' => $stn_rk_up,
        'Spins TW' => $spins_tw_adj,
        '+/-' => $spins_diff,
        'AMD' => $daypart_amd,
        'MID' => $daypart_mid,
        'PMD' => $daypart_pmd,
        'EVE' => $daypart_eve,
        'Market Shr (%)' => $market_shr,
        'First Played' => $first_played,
        'ATD' => $atd,
        'Rank' => $rank,
        'Peak' => $peak,
        'Format Peak' => $format_peak,
        'CANADA' => $odStreamsCanada,
        'MARKET' => $odStreamsMarket,
        'PETERBRO' => $s['PETE'] ?? $s['PETERBRO'] ?? $s['PETE'] ?? ''
      ];
    }

    // ---- ADD UNMATCHED NATIONAL (ROLLING) CHART SONGS TO BOTTOM ----
    foreach ($rollingIdx as $key => $r) {
      if (isset($localKeysSet[$key])) continue; // skip if already included from playlist

      // Try to find streaming data
      $artist = $r[$colIdx['Artist']] ?? '';
      $title  = $r[$colIdx['Title']] ?? '';
      $s = $streamingIdx[$key] ?? null;
      if (!$s) {
        foreach ($streaming as $streamRow) {
          if (
            self::artistsMatch($artist, $streamRow['Artist'] ?? '') &&
            self::titlesMatch($title, $streamRow['Title'] ?? '')
          ) {
            $s = $streamRow;
            break;
          }
        }
      }
      if (!$s) $s = [];

      $final[] = [
        'TW' => '', // no local info
        'NW' => '',
        'Artist' => $artist,
        'Title' => $title,
        'CanCon' => '',
        'Year' => '',
        'Stn Rk TW' => '',
        'Stn Rk LW' => '',
        // Show national spins delta or blank in "Stn Rk UP"
        'Stn Rk UP' => isset($r[$colIdx['+/-']]) ? $r[$colIdx['+/-']] : '',
        'Spins TW' => '',
        '+/-' => isset($r[$colIdx['+/-']]) ? $r[$colIdx['+/-']] : '',
        'AMD' => '',
        'MID' => '',
        'PMD' => '',
        'EVE' => '',
        'Market Shr (%)' => '',
        'First Played' => '',
        'ATD' => '',
        'Rank' => isset($r[$colIdx['Rank']]) ? $r[$colIdx['Rank']] : '',
        'Peak' => isset($r[$colIdx['Peak']]) ? $r[$colIdx['Peak']] : '',
        'Format Peak' => isset($r[$colIdx['Peak']]) ? $r[$colIdx['Peak']] : '',
        'CANADA' => $s['CANADA'] ?? 'N/A',
        'MARKET' => $s['PETE'] ?? $s['PETERBRO'] ?? $s['PETE'] ?? ''
      ];
    }

    return [
      'meta' => [
        'report' => $reportMeta
      ],
      'rows' => $final
    ];
  }
}