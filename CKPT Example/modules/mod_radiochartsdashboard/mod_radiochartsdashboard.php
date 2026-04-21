<?php
defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;
use Joomla\Module\Radiochartsdashboard\Site\Helper\RadiochartsdashboardHelper;

$dataDir = __DIR__ . '/data/';
$db = Factory::getDbo();
$table = 'd6f21_radiochartsdashboard_state';

// Handle file uploads (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['playlist_csv']) && $_FILES['playlist_csv']['error'] === UPLOAD_ERR_OK) {
        $target = $dataDir . 'Station Playlist.csv';
        move_uploaded_file($_FILES['playlist_csv']['tmp_name'], $target);
    }
    if (isset($_FILES['rolling_csv']) && $_FILES['rolling_csv']['error'] === UPLOAD_ERR_OK) {
        $target = $dataDir . 'Published Chart.csv';
        move_uploaded_file($_FILES['rolling_csv']['tmp_name'], $target);
    }
    if (isset($_FILES['streaming_csv']) && $_FILES['streaming_csv']['error'] === UPLOAD_ERR_OK) {
        $target = $dataDir . 'Streaming Metrics.csv';
        move_uploaded_file($_FILES['streaming_csv']['tmp_name'], $target);
    }
}

// Fetch all available weeks for the dropdown
$query = $db->getQuery(true)
    ->select('week_start')
    ->from($db->qn($table))
    ->order('week_start DESC');
$db->setQuery($query);
$allWeeks = $db->loadColumn();

// Determine which week to display
$selectedWeek = $_GET['week_start'] ?? 'current';

// Find the oldest week in the database
$oldestWeek = $allWeeks ? min($allWeeks) : null;
$isFirstWeek = ($selectedWeek !== 'current' && $selectedWeek === $oldestWeek);

$csvExists = is_readable($dataDir . 'Station Playlist.csv');

// Function: get prior week mapping
function getPriorWeekMap($selectedWeek, $db, $table) {
    $query = $db->getQuery(true)
        ->select('week_start')
        ->from($db->qn($table))
        ->where($db->qn('week_start') . ' < ' . $db->quote($selectedWeek))
        ->order($db->qn('week_start') . ' DESC')
        ->setLimit(1);
    $db->setQuery($query);
    $priorWeekDate = $db->loadResult();
    if (!$priorWeekDate) return [];
    $priorQuery = $db->getQuery(true)
        ->select('state_json')
        ->from($db->qn($table))
        ->where($db->qn('week_start') . ' = ' . $db->quote($priorWeekDate));
    $db->setQuery($priorQuery);
    $priorStateJson = $db->loadResult();
    $priorDbState = $priorStateJson ? json_decode($priorStateJson, true) : [];
    $priorMap = [];
    foreach ($priorDbState as $entry) {
        $key = RadiochartsdashboardHelper::normalize($entry['artist'] ?? '', $entry['title'] ?? '');
        $priorMap[$key] = $entry;
    }
    return $priorMap;
}

if ($selectedWeek === 'current' || !in_array($selectedWeek, $allWeeks)) {
    // --- Build from current CSVs ---
    $data = RadiochartsdashboardHelper::getCombinedRows();
    $meta = $data['meta'];
    $rows = $data['rows'];
    list($twStart, $lwStart) = RadiochartsdashboardHelper::extractWeekDatesFromStationPlaylist($dataDir . 'Station Playlist.csv');
    $weekStart = $twStart ?: date('Y-m-d');

    // Overlay TW/NW and calculate +/- using prior week DB if available
    $priorMap = getPriorWeekMap($weekStart, $db, $table);
    foreach ($rows as &$row) {
        $key = RadiochartsdashboardHelper::normalize($row['Artist'] ?? '', $row['Title'] ?? '');

        if (isset($priorMap[$key])) {
            $priorTW = $priorMap[$key]['tw'] ?? '';
            $priorNW = $priorMap[$key]['nw'] ?? '';
            // Promotion logic: if last week was ADD with NW, promote NW to TW
            if ($priorTW === 'ADD' && $priorNW) {
                $row['TW'] = $priorNW;
                $row['NW'] = '';
            } else {
                if (empty($row['TW'])) $row['TW'] = $priorTW;
                $row['NW'] = '';
            }
        }

        // Spins +/- calculation: N/A if first week, else compare to prior
        if ($isFirstWeek) {
            $row['+/-'] = 'N/A';
        } else {
            $spins_tw = intval($row['Spins TW'] ?? 0);
            $spins_prev = isset($priorMap[$key]) ? intval($priorMap[$key]['Spins TW'] ?? 0) : null;
            $row['+/-'] = (isset($priorMap[$key]) && $spins_prev !== null) ? ($spins_tw - $spins_prev) : 'N/A';
        }
    }
    unset($row);

    // If not current, overlay meta line from DB if available
    if ($selectedWeek !== 'current') {
        $query = $db->getQuery(true)
            ->select(['meta_line'])
            ->from($db->qn($table))
            ->where($db->qn('week_start') . ' = ' . $db->quote($selectedWeek));
        $db->setQuery($query);
        $result = $db->loadAssoc();
        $metaLine = $result['meta_line'] ?? null;
        // Always use the saved meta_line if available
        if ($metaLine) {
            $meta = ['report' => $metaLine];
        } else {
            $meta = ['report' => 'Saved week: ' . $selectedWeek];
        }
        $weekStart = $selectedWeek;
    }
} else {
    // --- Build from DB only ---
    $query = $db->getQuery(true)
        ->select(['state_json', 'meta_line'])
        ->from($db->qn($table))
        ->where($db->qn('week_start') . ' = ' . $db->quote($selectedWeek));
    $db->setQuery($query);
    $result = $db->loadAssoc();
    $stateJson = $result['state_json'] ?? null;
    $metaLine = $result['meta_line'] ?? null;
    $dbState = $stateJson ? json_decode($stateJson, true) : [];
    // Determine if this is the oldest week in DB
    $isFirstWeek = ($selectedWeek === $oldestWeek);

    $priorMap = getPriorWeekMap($selectedWeek, $db, $table);

    $rows = [];
    foreach ($dbState as $entry) {
        $key = RadiochartsdashboardHelper::normalize($entry['artist'] ?? '', $entry['title'] ?? '');
        $spins_tw = isset($entry['Spins TW']) ? intval($entry['Spins TW']) : null;
        $spins_prev = isset($priorMap[$key]) ? intval($priorMap[$key]['Spins TW'] ?? 0) : null;
        $plusminus = (!$isFirstWeek && isset($priorMap[$key]) && $spins_prev !== null) ? ($spins_tw - $spins_prev) : 'N/A';
        $rows[] = [
            'TW' => $entry['tw'] ?? '',
            'NW' => $entry['nw'] ?? '',
            'Stn Rk TW' => $entry['Stn Rk TW'] ?? '',
			'Stn Rk LW' => $entry['Stn Rk LW'] ?? '',
            'Stn Rk UP' => $entry['Stn Rk UP'] ?? '',
            'Artist' => $entry['artist'] ?? '',
            'Title' => $entry['title'] ?? '',
            'CanCon' => $entry['cancon'] ?? '',
            'Year' => $entry['year'] ?? '',
            'Spins TW' => $entry['Spins TW'] ?? '',
            '+/-' => $plusminus,
            'AMD' => $entry['AMD'] ?? '',
            'MID' => $entry['MID'] ?? '',
            'PMD' => $entry['PMD'] ?? '',
            'EVE' => $entry['EVE'] ?? '',
            'Market Shr (%)' => $entry['Market Shr (%)'] ?? '',
            'First Played' => $entry['First Played'] ?? '',
            'ATD' => $entry['ATD'] ?? '',
            'Rank' => $entry['Rank'] ?? '',
            'Peak' => $entry['Peak'] ?? '',
            'CANADA' => $entry['CANADA'] ?? '',
            'MARKET' => $entry['MARKET'] ?? '',
        ];
    }
    // Always use the saved meta_line if available
    if ($metaLine) {
        $meta = ['report' => $metaLine];
    } else {
        $meta = ['report' => 'Saved week: ' . $selectedWeek];
    }
    $weekStart = $selectedWeek;
}

// Output the JS variables for frontend usage
echo '<script>window.RCDASHBOARD_WEEK_START = ' . json_encode($weekStart) . ';</script>';
echo '<script>window.RCDASHBOARD_WEEKS = ' . json_encode($allWeeks) . ';</script>';
echo '<script>window.RCDASHBOARD_SELECTED_WEEK = ' . json_encode($selectedWeek) . ';</script>';

require ModuleHelper::getLayoutPath('mod_radiochartsdashboard', $params->get('layout', 'default'));