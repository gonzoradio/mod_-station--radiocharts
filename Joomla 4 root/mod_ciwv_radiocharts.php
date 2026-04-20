<?php
// No direct access
defined('_JEXEC') or die;

// Get params
$station = $params->get('station', 'CIWV');
$dataPath = JPATH_ROOT . '/' . trim($params->get('data_path', 'data'), '/');
$allowedTypes = $params->get('allowed_csv_types', ['national', 'station', 'streaming_station', 'streaming_market', 'musicmaster', 'billboard']);

// Include helper
require_once __DIR__ . '/helper.php';

// Handle uploads if POST
$uploadResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['radiocharts_csv_file'])) {
    $uploadResult = ModCiwvRadiochartsHelper::handleUpload($_FILES['radiocharts_csv_file'], $_POST['csv_type'] ?? '', $dataPath, $allowedTypes);
}

// Load dashboard data (aggregated from uploaded/processed CSVs)
$dashboardData = ModCiwvRadiochartsHelper::getDashboardData($station, $dataPath);

// Pass everything to tmpl
require JModuleHelper::getLayoutPath('mod_ciwv_radiocharts', $params->get('layout', 'default'));
?>