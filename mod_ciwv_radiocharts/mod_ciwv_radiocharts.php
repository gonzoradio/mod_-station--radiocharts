<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/helper.php';

// ── Configuration ────────────────────────────────────────────────────────────
$station      = $params->get('station', 'CIWV');
$dataDir      = JPATH_BASE . '/modules/mod_ciwv_radiocharts/data';
$customPath   = trim((string) $params->get('data_path', ''), '/');
if ($customPath !== '') {
    $dataDir = JPATH_ROOT . '/' . $customPath;
}
$allowedTypes = (array) $params->get('allowed_csv_types', array_keys(ModCiwvRadiochartsHelper::$csvNames));
// Map legacy option values saved before the csvNames refactor.
// Old 'national' → new 'national_sj' + 'national_ac'; old 'streaming_station' removed;
// old 'streaming_market' → new 'streaming'.
$legacyMap = ['national' => ['national_sj', 'national_ac'], 'streaming_station' => [], 'streaming_market' => ['streaming']];
foreach ($legacyMap as $oldKey => $newKeys) {
    if (in_array($oldKey, $allowedTypes, true)) {
        $allowedTypes = array_merge(array_diff($allowedTypes, [$oldKey]), $newKeys);
    }
}

// ── Handle upload ─────────────────────────────────────────────────────────────
$uploadResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['radiocharts_csv_file']['name'])) {
    $uploadResult = ModCiwvRadiochartsHelper::handleUpload(
        $_FILES['radiocharts_csv_file'],
        $_POST['csv_type'] ?? '',
        $dataDir,
        $allowedTypes
    );
}

// ── DB & week-state setup ─────────────────────────────────────────────────────
$db         = Factory::getDbo();
// Always use the real table prefix via getPrefix() to avoid #__ substitution
// issues in direct-file ajax contexts (per project convention).
$stateTable = $db->getPrefix() . 'ciwv_radiocharts_state';

// All saved weeks (for the week-selector dropdown)
try {
    $query = $db->getQuery(true)
        ->select($db->qn('week_start'))
        ->from($db->qn($stateTable))
        ->order($db->qn('week_start') . ' DESC');
    $db->setQuery($query);
    $allWeeks = $db->loadColumn();
} catch (\Exception $e) {
    $allWeeks = [];
}

$selectedWeek = $_GET['week_start'] ?? 'current';
$oldestWeek   = $allWeeks ? min($allWeeks) : null;
$isFirstWeek  = ($selectedWeek !== 'current' && $selectedWeek === $oldestWeek);

// Helper: load prior-week map from DB
$getPriorWeekMap = function ($weekDate) use ($db, $stateTable) {
    if (!$weekDate) {
        return [];
    }
    try {
        $q = $db->getQuery(true)
            ->select($db->qn('week_start'))
            ->from($db->qn($stateTable))
            ->where($db->qn('week_start') . ' < ' . $db->quote($weekDate))
            ->order($db->qn('week_start') . ' DESC')
            ->setLimit(1);
        $db->setQuery($q);
        $priorDate = $db->loadResult();
        if (!$priorDate) {
            return [];
        }
        $q2 = $db->getQuery(true)
            ->select($db->qn('state_json'))
            ->from($db->qn($stateTable))
            ->where($db->qn('week_start') . ' = ' . $db->quote($priorDate));
        $db->setQuery($q2);
        $json  = $db->loadResult();
        $prior = $json ? json_decode($json, true) : [];
        $map   = [];
        foreach ((array) $prior as $entry) {
            $key       = ModCiwvRadiochartsHelper::normalize($entry['artist'] ?? '', $entry['title'] ?? '');
            $map[$key] = $entry;
        }
        return $map;
    } catch (\Exception $e) {
        return [];
    }
};

// ── Build dashboard data ──────────────────────────────────────────────────────

// Helper: compute week-over-week direction for a numeric field.
// Returns 'up', 'down', or '' (no data / no change / non-numeric).
$compareDir = function ($curr, $priorVal, $higherIsBetter = true) {
    $c = str_replace(',', '', trim((string) $curr));
    $p = str_replace(',', '', trim((string) $priorVal));
    if ($c === '' || $p === '' || !is_numeric($c) || !is_numeric($p)) {
        return '';
    }
    $cf = (float) $c;
    $pf = (float) $p;
    if ($cf == $pf) {
        return '';
    }
    return ($cf > $pf) === $higherIsBetter ? 'up' : 'down';
};

// CanCon lookup from current national CSVs (used as a fallback for saved weeks
// that predate the `cancon` field being stored in the state JSON).
$canconLookup = ModCiwvRadiochartsHelper::getCanconLookup($dataDir);

if ($selectedWeek === 'current' || !in_array($selectedWeek, (array) $allWeeks, true)) {
    // Build from CSVs
    $data      = ModCiwvRadiochartsHelper::getCombinedRows($dataDir);
    $meta      = $data['meta'];
    $rows      = $data['rows'];
    $stationFile = ModCiwvRadiochartsHelper::getLatestFile($dataDir, 'station');
    [$twStart]   = $stationFile
        ? ModCiwvRadiochartsHelper::extractWeekDatesFromStationPlaylist($stationFile)
        : [null];
    $weekStart = $twStart ?: date('Y-m-d');

    // Overlay TW/NW from prior week and apply DB-based WoW direction flags
    $priorMap = $getPriorWeekMap($weekStart);
    foreach ($rows as &$row) {
        $key = ModCiwvRadiochartsHelper::normalize($row['Artist'] ?? '', $row['Title'] ?? '');
        if (isset($priorMap[$key])) {
            $prior = $priorMap[$key];
            // Promote NW → TW if last week was ADD with a NW set
            if (($prior['tw'] ?? '') === 'ADD' && !empty($prior['nw'])) {
                $row['TW'] = $prior['nw'];
                $row['NW'] = '';
            } else {
                if ($row['TW'] === '') {
                    $row['TW'] = $prior['tw'] ?? '';
                }
                $row['NW'] = '';
            }
            // Station spins TW and streaming: no LW column in those CSVs, use DB prior
            $row['SpinsTwDir']   = $compareDir($row['Spins TW'],    $prior['Spins TW']    ?? '');
            $row['StreamsCaDir'] = $compareDir($row['#Streams CA'],  $prior['#Streams CA'] ?? '');
            $row['StreamsVanDir']= $compareDir($row['#Streams Van'], $prior['#Streams Van']?? '');
            // Fill in DB-based directions for national fields not covered by CSV LW data
            if (($row['NatSpinsTwDir'] ?? '') === '') {
                $row['NatSpinsTwDir'] = $compareDir($row['#Spins TW'], $prior['#Spins TW'] ?? '');
            }
            if (($row['AvgSpinsDir'] ?? '') === '') {
                $row['AvgSpinsDir'] = $compareDir($row['Avg Spins'], $prior['Avg Spins'] ?? '');
            }
            if (($row['RkDir'] ?? '') === '') {
                $row['RkDir'] = $compareDir($row['Rk'], $prior['Rk'] ?? '', false);
            }
        }
    }
    unset($row);
} else {
    // Build from saved DB snapshot
    try {
        $query = $db->getQuery(true)
            ->select([$db->qn('state_json'), $db->qn('meta_line')])
            ->from($db->qn($stateTable))
            ->where($db->qn('week_start') . ' = ' . $db->quote($selectedWeek));
        $db->setQuery($query);
        $result = $db->loadAssoc();
    } catch (\Exception $e) {
        $result = null;
    }
    $dbState  = isset($result['state_json']) ? json_decode($result['state_json'], true) : [];
    $metaLine = $result['meta_line'] ?? null;

    // Prior week map for WoW direction comparison in saved-week view
    $priorMap = $getPriorWeekMap($selectedWeek);

    $rows = [];
    foreach ((array) $dbState as $entry) {
        $artist = $entry['artist'] ?? '';
        $title  = $entry['title']  ?? '';
        $key    = ModCiwvRadiochartsHelper::normalize($artist, $title);

        // CanCon: use stored flag (new saves), then fall back to current national CSV lookup
        $isCancon = !empty($entry['cancon']) || isset($canconLookup[$key]);

        // WoW direction flags from DB prior week comparison
        $spinsTwDir    = '';
        $streamsCaDir  = '';
        $streamsVanDir = '';
        $natSpinsDir   = '';
        $avgSpinsDir   = '';
        $rkDir         = '';
        if (isset($priorMap[$key])) {
            $prior         = $priorMap[$key];
            $spinsTwDir    = $compareDir($entry['Spins TW']    ?? '', $prior['Spins TW']    ?? '');
            $streamsCaDir  = $compareDir($entry['#Streams CA']  ?? '', $prior['#Streams CA'] ?? '');
            $streamsVanDir = $compareDir($entry['#Streams Van'] ?? '', $prior['#Streams Van']?? '');
            $natSpinsDir   = $compareDir($entry['#Spins TW']   ?? '', $prior['#Spins TW']   ?? '');
            $avgSpinsDir   = $compareDir($entry['Avg Spins']   ?? '', $prior['Avg Spins']   ?? '');
            $rkDir         = $compareDir($entry['Rk']          ?? '', $prior['Rk']          ?? '', false);
        }

        $rkGreen = (bool) ($entry['rk_green'] ?? false);
        // If direction data is available, prefer it over the legacy rk_green flag
        if ($rkDir === '') {
            $rkDir = $rkGreen ? 'up' : '';
        }

        $rows[] = [
            'TW'              => $entry['tw']              ?? '',
            'NW'              => $entry['nw']              ?? '',
            'Cancon'          => $isCancon,
            'Artist'          => $artist,
            'Title'           => $title,
            'WEEKS'           => $entry['weeks']           ?? '',
            'CAT'             => $entry['cat']             ?? '',
            'Spins TW'        => $entry['Spins TW']        ?? '',
            'SpinsTwDir'      => $spinsTwDir,
            'Spins ATD'       => $entry['Spins ATD']       ?? '',
            '#Streams CA'     => $entry['#Streams CA']     ?? '',
            'StreamsCaDir'    => $streamsCaDir,
            '#Streams Van'    => $entry['#Streams Van']    ?? '',
            'StreamsVanDir'   => $streamsVanDir,
            '#Spins TW'       => $entry['#Spins TW']       ?? '',
            'NatSpinsTwDir'   => $natSpinsDir,
            '#Stns TW'        => $entry['#Stns TW']        ?? '',
            'Avg Spins'       => $entry['Avg Spins']       ?? '',
            'AvgSpinsDir'     => $avgSpinsDir,
            'MB Cht'          => $entry['MB Cht']          ?? '',
            'Rk'              => $entry['Rk']              ?? '',
            'RkDir'           => $rkDir,
            'Peak'            => $entry['Peak']            ?? '',
            'BB SJ Chart'     => $entry['BB SJ Chart']     ?? '',
            'Freq/Listen ATD' => $entry['Freq/Listen ATD'] ?? '',
            'Impres ATD'      => $entry['Impres ATD']      ?? '',
            'RkGreen'         => $rkGreen,
        ];
    }
    $meta      = ['report' => $metaLine ?: ('Saved week: ' . $selectedWeek)];
    $weekStart = $selectedWeek;
}

// Inject JS context variables via Joomla's safe mechanism
$doc = Factory::getDocument();
$doc->addScriptOptions('rciwv', [
    'weekStart'    => $weekStart,
    'weeks'        => $allWeeks,
    'selectedWeek' => $selectedWeek,
]);

require ModuleHelper::getLayoutPath('mod_ciwv_radiocharts', $params->get('layout', 'default'));