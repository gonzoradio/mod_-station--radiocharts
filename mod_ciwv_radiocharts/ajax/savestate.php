<?php
/**
 * AJAX endpoint: save a weekly chart snapshot to the database.
 *
 * POST JSON body: { state: [...], week_start: "YYYY-MM-DD", meta_line: "..." }
 */

ob_start();

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(__DIR__, 3));
}
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $rawData = file_get_contents('php://input');
    $data    = json_decode($rawData, true);

    if (empty($data['state'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'No state data provided']);
        exit;
    }

    $db = Factory::getDbo();
    // Use the real table prefix so the query works regardless of how the
    // Joomla application is bootstrapped in this direct-file context.
    $stateTable = '`' . $db->getPrefix() . 'ciwv_radiocharts_state`';

    // Resolve the week start date
    $saveWeek = $data['week_start'] ?? null;
    if (!$saveWeek || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saveWeek)) {
        // Fall back to extracting from the most-recently-uploaded Station Playlist
        $dataDir = JPATH_BASE . '/modules/mod_ciwv_radiocharts/data';
        require_once JPATH_BASE . '/modules/mod_ciwv_radiocharts/helper.php';
        $stationFile = ModCiwvRadiochartsHelper::getLatestFile($dataDir, 'station');
        if (!$stationFile) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Could not determine report week']);
            exit;
        }
        [$twStart] = ModCiwvRadiochartsHelper::extractWeekDatesFromStationPlaylist($stationFile);
        if (!$twStart) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Could not extract week start from Station Playlist']);
            exit;
        }
        $saveWeek = $twStart;
    }

    $metaLine  = $data['meta_line'] ?? '';
    $stateJson = json_encode($data['state']);

    $query = 'INSERT INTO ' . $stateTable
        . ' (`week_start`, `state_json`, `meta_line`, `saved_at`)'
        . ' VALUES (' . $db->quote($saveWeek) . ', '
        . $db->quote($stateJson) . ', '
        . $db->quote($metaLine) . ', NOW())'
        . ' ON DUPLICATE KEY UPDATE'
        . ' `state_json` = VALUES(`state_json`),'
        . ' `meta_line`  = VALUES(`meta_line`),'
        . ' `saved_at`   = NOW()';
    $db->setQuery($query);
    $db->execute();

    ob_clean();
    echo json_encode(['success' => true, 'week_start' => $saveWeek]);
    exit;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
