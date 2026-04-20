<?php
/**
 * AJAX endpoint: load a saved weekly chart snapshot from the database.
 *
 * GET ?week_start=YYYY-MM-DD
 * Returns the raw state_json array or [] if not found.
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

header('Content-Type: application/json; charset=utf-8');

try {
    $weekStart = $_GET['week_start'] ?? '';
    if (!$weekStart || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
        echo json_encode([]);
        exit;
    }

    // Auth check – require a logged-in user
    $user = Factory::getUser();
    if (!$user->id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    $db    = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select($db->qn('state_json'))
        ->from($db->qn('#__ciwv_radiocharts_state'))
        ->where($db->qn('week_start') . ' = ' . $db->quote($weekStart));
    $db->setQuery($query);
    $state = $db->loadResult();

    if ($state) {
        echo $state; // already valid JSON
    } else {
        echo json_encode([]);
    }
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
