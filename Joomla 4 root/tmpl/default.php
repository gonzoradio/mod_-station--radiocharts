<?php
// Dashboard UI
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;

echo '<h2>Radio Charts Dashboard</h2>';

// Upload form with CSV source picker
echo '<form method="POST" enctype="multipart/form-data">';
echo 'Select CSV type: <select name="csv_type" required>';
foreach (ModCiwvRadiochartsHelper::$csvNames as $key => $label) {
    echo '<option value="'.htmlspecialchars($key).'">'.htmlspecialchars($label).'</option>';
}
echo '</select> ';
echo '<input type="file" name="radiocharts_csv_file" required accept=".csv" />';
echo '<input type="submit" value="Upload" />';
echo "</form>";

// Upload result feedback
if (!empty($uploadResult)) echo "<div class='upload-status'>$uploadResult</div>";

// Example CSV info
echo '<h4>Example CSV Templates:</h4><ul>';
foreach (ModCiwvRadiochartsHelper::$csvNames as $label) {
    echo '<li><a href="'.JUri::root().'modules/mod_ciwv_radiocharts/data-examples/'.$label.'_example.csv">'.$label.'_example.csv</a></li>';
}
echo '</ul>';

// Station selector
echo '<form method="GET"><label>Station: <input type="text" name="station" value="'.htmlspecialchars($station).'"></label><input type="submit" value="Switch"></form>';

// Dashboard output
echo '<div class="dashboard-table">';
foreach ($dashboardData as $type => $table) {
    echo '<h3>'.htmlspecialchars(ModCiwvRadiochartsHelper::$csvNames[$type]).'</h3>';
    if (empty($table)) { echo "<p>No data uploaded for this type yet.</p>"; } else {
        echo '<table border="1"><tr>';
        foreach (($table[0] ?? []) as $col) echo '<th>'.htmlspecialchars($col).'</th>';
        echo '</tr>';
        foreach (array_slice($table, 1) as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>'.htmlspecialchars($cell).'</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
echo '</div>';