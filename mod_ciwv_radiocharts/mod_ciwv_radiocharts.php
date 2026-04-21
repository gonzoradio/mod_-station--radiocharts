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
$db    = Factory::getDbo();
$table = '#__ciwv_radiocharts_state';

// All saved weeks (for the week-selector dropdown)
try {
    $query = $db->getQuery(true)
        ->select($db->qn('week_start'))
        ->from($db->qn($table))
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
$getPriorWeekMap = function ($weekDate) use ($db, $table) {
    if (!$weekDate) {
        return [];
    }
    try {
        $q = $db->getQuery(true)
            ->select($db->qn('week_start'))
            ->from($db->qn($table))
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
            ->from($db->qn($table))
            ->where($db->qn('week_start') . ' = ' . $db->quote($priorDate));
        $db->setQuery($q2);
        $json = $db->loadResult();
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
if ($selectedWeek === 'current' || !in_array($selectedWeek, (array) $allWeeks, true)) {
    // Build from CSVs
    $data     = ModCiwvRadiochartsHelper::getCombinedRows($dataDir);
    $meta     = $data['meta'];
    $rows     = $data['rows'];
    $stationFile = ModCiwvRadiochartsHelper::getLatestFile($dataDir, 'station');
    [$twStart] = $stationFile
        ? ModCiwvRadiochartsHelper::extractWeekDatesFromStationPlaylist($stationFile)
        : [null];
    $weekStart = $twStart ?: date('Y-m-d');

    // Overlay TW/NW from prior week and compute +/- spins
    $priorMap = $getPriorWeekMap($weekStart);
    foreach ($rows as &$row) {
        $key = ModCiwvRadiochartsHelper::normalize($row['Artist'] ?? '', $row['Title'] ?? '');
        if (isset($priorMap[$key])) {
            $prior = $priorMap[$key];
            // Promote NW → TW if last week was ADD with a NW set
            if (($prior['tw'] ?? '') === 'ADD' && !empty($prior['nw'])) {
                $row['TW'] = $prior['nw'];
                $row['NW'] = '';            } else {
                if ($row['TW'] === '') {
                    $row['TW'] = $prior['tw'] ?? '';
                }
                $row['NW'] = '';
            }
            // Spins +/-
            if (!$isFirstWeek) {
                $spinsTW   = intval($row['Spins TW'] ?? 0);
                $spinsPrev = intval($prior['Spins TW'] ?? 0);
                $row['+/-'] = $spinsTW - $spinsPrev;
            } else {
                $row['+/-'] = 'N/A';
            }
        }
    }
    unset($row);
} else {
    // Build from saved DB snapshot
    try {
        $query = $db->getQuery(true)
            ->select([$db->qn('state_json'), $db->qn('meta_line')])
            ->from($db->qn($table))
            ->where($db->qn('week_start') . ' = ' . $db->quote($selectedWeek));
        $db->setQuery($query);
        $result   = $db->loadAssoc();
    } catch (\Exception $e) {
        $result = null;
    }
    $dbState  = isset($result['state_json']) ? json_decode($result['state_json'], true) : [];
    $metaLine = $result['meta_line'] ?? null;
    $priorMap = $getPriorWeekMap($selectedWeek);

    $rows = [];
    foreach ((array) $dbState as $entry) {
        $key       = ModCiwvRadiochartsHelper::normalize($entry['artist'] ?? '', $entry['title'] ?? '');
        $spinsTW   = intval($entry['Spins TW'] ?? 0);
        $spinsPrev = isset($priorMap[$key]) ? intval($priorMap[$key]['Spins TW'] ?? 0) : null;
        $plusminus = (!$isFirstWeek && $spinsPrev !== null && isset($priorMap[$key]))
            ? ($spinsTW - $spinsPrev) : 'N/A';
        $rows[] = [
            'TW'             => $entry['tw'] ?? '',
            'NW'             => $entry['nw'] ?? '',
            'Stn Rk TW'      => $entry['Stn Rk TW'] ?? '',
            'Stn Rk LW'      => $entry['Stn Rk LW'] ?? '',
            'Stn Rk UP'      => $entry['Stn Rk UP'] ?? '',
            'Artist'         => $entry['artist'] ?? '',
            'Title'          => $entry['title'] ?? '',
            'CanCon'         => $entry['cancon'] ?? '',
            'Year'           => $entry['year'] ?? '',
            'Spins TW'       => $entry['Spins TW'] ?? '',
            '+/-'            => $plusminus,
            'AMD'            => $entry['AMD'] ?? '',
            'MID'            => $entry['MID'] ?? '',
            'PMD'            => $entry['PMD'] ?? '',
            'EVE'            => $entry['EVE'] ?? '',
            'Market Shr (%)' => $entry['Market Shr (%)'] ?? '',
            'First Played'   => $entry['First Played'] ?? '',
            'ATD'            => $entry['ATD'] ?? '',
            'Rank'           => $entry['Rank'] ?? '',
            'Peak'           => $entry['Peak'] ?? '',
            'CANADA'         => $entry['CANADA'] ?? '',
            'MARKET'         => $entry['MARKET'] ?? '',
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