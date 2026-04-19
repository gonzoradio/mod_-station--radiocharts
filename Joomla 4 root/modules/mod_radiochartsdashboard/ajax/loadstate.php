<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(__DIR__, 3));
}
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

header('Content-Type: application/json');

try {
    $db = Factory::getDbo();
    $weekStart = $_GET['week_start'] ?? '';
    if (!$weekStart) {
        echo json_encode([]);
        exit;
    }
    // FIXED TABLE NAME BELOW
    $query = $db->getQuery(true)
        ->select('state_json')
        ->from('d6f21_radiochartsdashboard_state') // <-- fix here!
        ->where('week_start = ' . $db->quote($weekStart));
    $db->setQuery($query);
    $state = $db->loadResult();

    if ($state) {
        echo $state; // already JSON
    } else {
        echo json_encode([]);
    }
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
    exit;
}