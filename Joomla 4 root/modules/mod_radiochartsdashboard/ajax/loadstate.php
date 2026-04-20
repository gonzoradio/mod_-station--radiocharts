<?php
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
    $query = $db->getQuery(true)
        ->select($db->quoteName('state_json'))
        ->from($db->quoteName('#__radiochartsdashboard_state'))
        ->where($db->quoteName('week_start') . ' = ' . $db->quote($weekStart));
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}