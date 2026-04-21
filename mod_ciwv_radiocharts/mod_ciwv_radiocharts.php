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

    // Overlay TW/NW from prior week
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

    $rows = [];
    foreach ((array) $dbState as $entry) {
        $rows[] = [
            'TW'              => $entry['tw']              ?? '',
            'NW'              => $entry['nw']              ?? '',
            'Artist'          => $entry['artist']          ?? '',
            'Title'           => $entry['title']           ?? '',
            'WEEKS'           => $entry['weeks']           ?? '',
            'CAT'             => $entry['cat']             ?? '',
            'Spins TW'        => $entry['Spins TW']        ?? '',
            'Spins ATD'       => $entry['Spins ATD']       ?? '',
            '#Streams CA'     => $entry['#Streams CA']     ?? '',
            '#Streams Van'    => $entry['#Streams Van']    ?? '',
            '#Spins TW'       => $entry['#Spins TW']       ?? '',
            '#Stns TW'        => $entry['#Stns TW']        ?? '',
            'Avg Spins'       => $entry['Avg Spins']       ?? '',
            'MB Cht'          => $entry['MB Cht']          ?? '',
            'Rk'              => $entry['Rk']              ?? '',
            'Peak'            => $entry['Peak']            ?? '',
            'BB SJ Chart'     => $entry['BB SJ Chart']     ?? '',
            'Freq/Listen ATD' => $entry['Freq/Listen ATD'] ?? '',
            'Impres ATD'      => $entry['Impres ATD']      ?? '',
            'RkGreen'         => (bool) ($entry['rk_green'] ?? false),
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