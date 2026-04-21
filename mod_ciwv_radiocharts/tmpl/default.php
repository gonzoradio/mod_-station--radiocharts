<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$doc = Factory::getDocument();
$doc->addStyleSheet(Uri::root() . 'modules/mod_ciwv_radiocharts/media/style.css');
$doc->addScript(Uri::root() . 'modules/mod_ciwv_radiocharts/media/dashboard.js?v=2');

$twCats  = ModCiwvRadiochartsHelper::$twCategories;
$nwCats  = ModCiwvRadiochartsHelper::$nwCategories;
$catOpts = ModCiwvRadiochartsHelper::$catOptions;

// Build NW <option> list once for reuse
$nwOptions = '<option value=""></option>';
foreach ($nwCats as $cat) {
    $nwOptions .= '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
}

// Pre-build option HTML maps keyed by selected value for O(1) lookup per row.
// $map[''] = unselected option list; $map[$v] = list with $v pre-selected.
$buildOptionsMap = function (array $values): array {
    $base = '<option value=""></option>';
    foreach ($values as $v) {
        $esc   = htmlspecialchars($v);
        $base .= '<option value="' . $esc . '">' . $esc . '</option>';
    }
    $map = ['' => $base];
    foreach ($values as $selected) {
        $html = '<option value=""></option>';
        foreach ($values as $v) {
            $esc  = htmlspecialchars($v);
            $sel  = ($v === $selected) ? ' selected' : '';
            $html .= '<option value="' . $esc . '"' . $sel . '>' . $esc . '</option>';
        }
        $map[$selected] = $html;
    }
    return $map;
};

$twOptionsMap  = $buildOptionsMap($twCats);
$catOptionsMap = $buildOptionsMap($catOpts);
?>
<?php if (!empty($meta['report'])): ?>
<div id="rc-meta-line"<?= ($selectedWeek !== 'current' ? ' style="color:red;"' : '') ?>>
  <strong><?= htmlspecialchars($meta['report']) ?></strong>
</div>
<?php endif; ?>

<!-- Upload forms (one per CSV source) -->
<div class="rc-upload-row">
  <?php $i = 1; foreach (ModCiwvRadiochartsHelper::$csvNames as $typeKey => $typeName): ?>
  <span class="rc-upload-num"><?= $i++ ?>.</span>
  <form method="post" enctype="multipart/form-data" class="rc-upload-form">
    <input type="hidden" name="csv_type" value="<?= htmlspecialchars($typeKey) ?>">
    <label><strong>Upload <?= htmlspecialchars($typeName) ?>:</strong>
      <input type="file" name="radiocharts_csv_file" accept=".csv" required>
    </label>
    <button type="submit">Upload</button>
  </form>
  <?php endforeach; ?>
</div>

<?php if (!empty($uploadResult)): ?>
<?php $isUploadError = (strncmp($uploadResult, 'Error:', 6) === 0); ?>
<div class="rc-upload-status<?= $isUploadError ? ' rc-upload-error' : '' ?>"><?= htmlspecialchars($uploadResult) ?></div>
<?php endif; ?>

<!-- CSV example templates -->
<div class="rc-examples">
  <strong>CSV Templates:</strong>
  <?php foreach (ModCiwvRadiochartsHelper::$csvNames as $typeName): ?>
  <a href="<?= Uri::root() ?>modules/mod_ciwv_radiocharts/data-examples/<?= htmlspecialchars($typeName) ?>_example.csv"><?= htmlspecialchars($typeName) ?></a>
  <?php endforeach; ?>
</div>

<!-- Dashboard controls -->
<div class="rc-controls">
  <label>Sort by:
    <select id="rc-sort-select">
      <option value="tw">TW Cat</option>
      <option value="artist">Artist</option>
      <option value="title">Title</option>
      <option value="weeks">WEEKS</option>
      <option value="spins_atd">Spins ATD</option>
      <option value="streams_ca">#Streams CA</option>
      <option value="streams_van">#Streams Van</option>
      <option value="spins_tw">#Spins TW</option>
      <option value="stns_tw">#Stns TW</option>
      <option value="avg_spins">Avg Spins</option>
      <option value="rk">Rk</option>
    </select>
  </label>
  <label><input type="checkbox" id="rc-reverse-sort"> Reverse</label>
  <button id="rc-save-state">Save Data</button>
  <button id="rc-export-csv">Export CSV</button>

  <label>Week:
    <select id="rc-week-select">
      <option value="current"<?= ($selectedWeek === 'current' ? ' selected' : '') ?>>Current (CSV files)</option>
      <?php foreach ((array) $allWeeks as $week): ?>
        <option value="<?= htmlspecialchars($week) ?>"<?= ($selectedWeek == $week ? ' selected' : '') ?>><?= htmlspecialchars($week) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <!-- Manual row add -->
  <span id="rc-manual-row-add">
    Artist: <input type="text" id="rc-manual-artist" style="width:120px;">
    Title:  <input type="text" id="rc-manual-title"  style="width:140px;">
    Weeks:  <input type="text" id="rc-manual-weeks"  style="width:40px;">
    <button id="rc-add-manual-row">Add Row</button>
  </span>
</div>

<!-- Dashboard table -->
<div class="rc-dashboard-module">
<?php if (!empty($rows)): ?>
  <div class="rc-table-scroll">
    <table class="rc-dashboard-table">
      <thead>
        <tr>
          <th colspan="2">Category</th>
          <th></th><th></th><th></th><th></th>
          <th>Spins</th>
          <th colspan="2">OD Streams</th>
          <th colspan="3">National</th>
          <th colspan="5">Chart Info</th>
        </tr>
        <tr>
          <th>TW</th>
          <th>NW</th>
          <th>Artist</th>
          <th>Title</th>
          <th>WEEKS</th>
          <th>CAT</th>
          <th>ATD</th>
          <th>#CA</th>
          <th>#Van</th>
          <th>#Spins TW</th>
          <th>#Stns TW</th>
          <th>Avg</th>
          <th>MB Cht</th>
          <th>Rk</th>
          <th>Peak</th>
          <th>BB SJ</th>
          <th>Freq ATD</th>
          <th>Imp ATD</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><select name="TW[]" class="rc-sel-tw"><?= $twOptionsMap[$row['TW'] ?? ''] ?? $twOptionsMap[''] ?></select></td>
          <td><select name="NW[]" class="rc-sel-nw"><?= $nwOptions ?></select></td>
          <td><?= htmlspecialchars($row['Artist'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Title'] ?? '') ?></td>
          <td><?= htmlspecialchars((string) ($row['WEEKS'] ?? '')) ?></td>
          <td><select name="CAT[]" class="rc-sel-cat"><?= $catOptionsMap[$row['CAT'] ?? ''] ?? $catOptionsMap[''] ?></select></td>
          <td><?= htmlspecialchars((string) ($row['Spins ATD'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['#Streams CA'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['#Streams Van'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['#Spins TW'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['#Stns TW'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Avg Spins'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['MB Cht'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Rk'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Peak'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['BB SJ Chart'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Freq/Listen ATD'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Impres ATD'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="rc-no-data">No data available. Upload your CSV files above to begin.</p>
<?php endif; ?>
</div>
