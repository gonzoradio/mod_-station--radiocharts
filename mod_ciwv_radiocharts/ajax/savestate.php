<?php
/**
 * AJAX endpoint: save a weekly chart snapshot to the database.
 *
 * POST JSON body: { state: [...], week_start: "YYYY-MM-DD", meta_line: "..." }
 */

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(__DIR__, 3));
}
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Initialise the site application so the session (and therefore the logged-in
// user) is available when we call Factory::getUser() below.  Without this,
// a raw-file bootstrap only loads the autoloader and Factory::getUser() falls
// back to a guest object, causing the 403 "Authentication required" branch.
try {
    Factory::getApplication('site');
} catch (\Throwable $ignore) {
    // If the application is already running or cannot be created in this
    // context, carry on – the auth check below will catch any real problem.
}

header('Content-Type: application/json; charset=utf-8');

ob_start();

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

    // Auth check – require a logged-in user
    $user = Factory::getUser();
    if (!$user->id) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // Use VALUES() alias syntax for broad MySQL 5.7 / 8.x compatibility.
    // The "INSERT … AS new_row … ON DUPLICATE KEY UPDATE … = new_row.col"
    // form requires MySQL ≥ 8.0.19 and fails on older servers.
    $query = 'INSERT INTO ' . $db->quoteName('#__ciwv_radiocharts_state')
        . ' (' . $db->quoteName('week_start') . ', '
        . $db->quoteName('state_json') . ', '
        . $db->quoteName('meta_line') . ', '
        . $db->quoteName('saved_at') . ')'
        . ' VALUES (' . $db->quote($saveWeek) . ', '
        . $db->quote($stateJson) . ', '
        . $db->quote($metaLine) . ', NOW())'
        . ' ON DUPLICATE KEY UPDATE '
        . $db->quoteName('state_json') . ' = VALUES(' . $db->quoteName('state_json') . '), '
        . $db->quoteName('meta_line')  . ' = VALUES(' . $db->quoteName('meta_line')  . '), '
        . $db->quoteName('saved_at')   . ' = NOW()';
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
