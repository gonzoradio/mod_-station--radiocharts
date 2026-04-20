<?php
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
use Joomla\Module\Radiochartsdashboard\Site\Helper\RadiochartsdashboardHelper;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();

        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);

        if (!empty($data['state'])) {
            $db = Factory::getDbo();

            // Accept week_start and meta_line from POST
            $saveWeek = $data['week_start'] ?? null;
            $metaLine = $data['meta_line'] ?? '';

            // Fallback to extracting from CSV if not provided
            if (!$saveWeek) {
                $base = JPATH_BASE . '/modules/mod_radiochartsdashboard/data/';
                $playlistFile = $base . 'Station Playlist.csv';
                if (!class_exists('Joomla\Module\Radiochartsdashboard\Site\Helper\RadiochartsdashboardHelper')) {
                    require_once JPATH_BASE . '/modules/mod_radiochartsdashboard/Helper/RadiochartsdashboardHelper.php';
                }
                list($twStart, $lwStart) = RadiochartsdashboardHelper::extractWeekDatesFromStationPlaylist($playlistFile);
                if (!$twStart) {
                    echo json_encode(['success' => false, 'error' => 'Could not determine report week from Station Playlist.']);
                    exit;
                }
                $saveWeek = $twStart;
            }

            $stateJson = json_encode($data['state']);
            // Use '#__' prefix so Joomla replaces it with the configured DB prefix.
            $query = 'INSERT INTO ' . $db->quoteName('#__radiochartsdashboard_state')
                . ' (' . $db->quoteName('week_start') . ', ' . $db->quoteName('state_json') . ', ' . $db->quoteName('meta_line') . ', ' . $db->quoteName('saved_at') . ')'
                . ' VALUES (' . $db->quote($saveWeek) . ', ' . $db->quote($stateJson) . ', ' . $db->quote($metaLine) . ', NOW())'
                . ' ON DUPLICATE KEY UPDATE '
                . $db->quoteName('state_json') . ' = VALUES(' . $db->quoteName('state_json') . '), '
                . $db->quoteName('meta_line')  . ' = VALUES(' . $db->quoteName('meta_line')  . '), '
                . $db->quoteName('saved_at')   . ' = NOW()';
            $db->setQuery($query);
            $db->execute();

            echo json_encode(['success' => true, 'week_start' => $saveWeek]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'No state data found']);
        exit;
    }

    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}