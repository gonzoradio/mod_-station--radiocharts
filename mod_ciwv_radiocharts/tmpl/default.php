<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$doc = Factory::getDocument();
$doc->addStyleSheet(Uri::root() . 'modules/mod_ciwv_radiocharts/media/style.css');
$doc->addScript(Uri::root() . 'modules/mod_ciwv_radiocharts/media/dashboard.js?v=6');

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
      <option value="spins_tw">Spins TW</option>
      <option value="spins_atd">Spins ATD</option>
      <option value="streams_ca">#Streams CA</option>
      <option value="streams_van">#Streams Van</option>
      <option value="nat_spins_tw">#Spins TW</option>
      <option value="stns_tw">#Stns TW</option>
      <option value="avg_spins">Avg Spins</option>
      <option value="mb_cht">MB Cht</option>
      <option value="rk">Rk</option>
      <option value="peak">Peak</option>
      <option value="bb_sj">BB SJ Chart</option>
      <option value="freq_atd">Freq/Listen ATD</option>
      <option value="imp_atd">Impres ATD</option>
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

</div>

<?php if ($selectedWeek !== 'current'): ?>
<!-- Row editor (visible for saved weeks only) -->
<div id="rc-row-editor">
  <div class="rc-row-editor-scroll">
    <table class="rc-row-editor-table">
      <thead>
        <tr>
          <th colspan="2">Category</th>
          <th></th><th></th><th></th><th></th><th></th>
          <th colspan="2">Spins</th>
          <th colspan="2">OD Streams</th>
          <th colspan="3">National</th>
          <th colspan="6">Chart Info</th>
        </tr>
        <tr>
          <th>TW</th>
          <th>NW</th>
          <th>CC</th>
          <th>Artist</th>
          <th>Title</th>
          <th>WEEKS</th>
          <th>CAT</th>
          <th>TW</th>
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
        <tr>
          <td><select id="re-tw"><?= $twOptionsMap[''] ?></select></td>
          <td><select id="re-nw"><?= $nwOptions ?></select></td>
          <td><input type="text" id="re-cc" style="width:35px;" placeholder="CC"></td>
          <td><input type="text" id="re-artist" style="width:120px;"></td>
          <td><input type="text" id="re-title" style="width:140px;"></td>
          <td><input type="text" id="re-weeks" style="width:40px;"></td>
          <td><select id="re-cat"><?= $catOptionsMap[''] ?></select></td>
          <td><input type="text" id="re-spins-tw" style="width:55px;"></td>
          <td><input type="text" id="re-spins-atd" style="width:65px;"></td>
          <td><input type="text" id="re-streams-ca" style="width:70px;"></td>
          <td><input type="text" id="re-streams-van" style="width:70px;"></td>
          <td><input type="text" id="re-nat-spins-tw" style="width:65px;"></td>
          <td><input type="text" id="re-stns-tw" style="width:55px;"></td>
          <td><input type="text" id="re-avg-spins" style="width:55px;"></td>
          <td><input type="text" id="re-mb-cht" style="width:55px;"></td>
          <td><input type="text" id="re-rk" style="width:40px;"></td>
          <td><input type="text" id="re-peak" style="width:40px;"></td>
          <td><input type="text" id="re-bb-sj" style="width:50px;"></td>
          <td><input type="text" id="re-freq-atd" style="width:70px;"></td>
          <td><input type="text" id="re-imp-atd" style="width:70px;"></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="rc-row-editor-actions">
    <button id="rc-add-row">Add Row</button>
    <button id="rc-update-row">Update Row</button>
    <span id="rc-editor-status"></span>
  </div>
</div>
<?php endif; ?>

<!-- Dashboard table -->
<div class="rc-dashboard-module">
<?php if (!empty($rows)): ?>
  <div class="rc-table-scroll">
    <table class="rc-dashboard-table">
      <thead>
        <tr>
          <th colspan="2">Category</th>
          <th></th><th></th><th></th><th></th><th></th>
          <th colspan="2">Spins</th>
          <th colspan="2">OD Streams</th>
          <th colspan="3">National</th>
          <th colspan="6">Chart Info</th>
        </tr>
        <tr>
          <th>TW</th>
          <th>NW</th>
          <th>CC</th>
          <th>Artist</th>
          <th>Title</th>
          <th>WEEKS</th>
          <th>CAT</th>
          <th>TW</th>
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
        <?php
          $dirClass = function($dir) {
            if ($dir === 'up')   return ' class="rc-val-up"';
            if ($dir === 'down') return ' class="rc-val-down"';
            return '';
          };
          $rkClass = '';
          if (!empty($row['RkDir'])) {
            $rkClass = ' class="rc-val-' . $row['RkDir'] . '"';
          } elseif (!empty($row['RkGreen'])) {
            $rkClass = ' class="rc-rk-up"';
          }
        ?>
        <tr data-src="<?= (int) ($row['SourceGroup'] ?? 0) ?>"><?php /* 0=StationPlaylist, 1=MMonly, 2=SJnational, 3=ACnational */ ?>
          <td><select name="TW[]" class="rc-sel-tw"><?= $twOptionsMap[$row['TW'] ?? ''] ?? $twOptionsMap[''] ?></select></td>
          <td><select name="NW[]" class="rc-sel-nw"><?= $nwOptions ?></select></td>
          <td class="rc-cc-cell"><?= !empty($row['Cancon']) ? 'CC' : '' ?></td>
          <td><?= htmlspecialchars($row['Artist'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Title'] ?? '') ?></td>
          <td><?= htmlspecialchars((string) ($row['WEEKS'] ?? '')) ?></td>
          <td><select name="CAT[]" class="rc-sel-cat"><?= $catOptionsMap[$row['CAT'] ?? ''] ?? $catOptionsMap[''] ?></select></td>
          <td<?= $dirClass($row['SpinsTwDir'] ?? '') ?>><?= htmlspecialchars((string) ($row['Spins TW'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['Spins ATD'] ?? '')) ?></td>
          <td<?= $dirClass($row['StreamsCaDir'] ?? '') ?>><?= htmlspecialchars((string) ($row['#Streams CA'] ?? '')) ?></td>
          <td<?= $dirClass($row['StreamsVanDir'] ?? '') ?>><?= htmlspecialchars((string) ($row['#Streams Van'] ?? '')) ?></td>
          <td<?= $dirClass($row['NatSpinsTwDir'] ?? '') ?>><?= htmlspecialchars((string) ($row['#Spins TW'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['#Stns TW'] ?? '')) ?></td>
          <td<?= $dirClass($row['AvgSpinsDir'] ?? '') ?>><?= htmlspecialchars((string) ($row['Avg Spins'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['MB Cht'] ?? '')) ?></td>
          <td<?= $rkClass ?>><?= htmlspecialchars((string) ($row['Rk'] ?? '')) ?></td>
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
