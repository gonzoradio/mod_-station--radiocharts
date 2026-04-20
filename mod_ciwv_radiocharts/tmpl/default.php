<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$doc = Factory::getDocument();
$doc->addStyleSheet(Uri::root() . 'modules/mod_ciwv_radiocharts/media/style.css');
$doc->addScript(Uri::root() . 'modules/mod_ciwv_radiocharts/media/dashboard.js?v=1');

$twCats = ModCiwvRadiochartsHelper::$twCategories;
$nwCats = ModCiwvRadiochartsHelper::$nwCategories;

// Build TW/NW <option> lists once for reuse
$twOptions = '<option value=""></option>';
foreach ($twCats as $cat) {
    $twOptions .= '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
}
$nwOptions = '<option value=""></option>';
foreach ($nwCats as $cat) {
    $nwOptions .= '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
}
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
<div class="rc-upload-status"><?= htmlspecialchars($uploadResult) ?></div>
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
      <option value="tw">TW</option>
      <option value="stn_rk_tw">Stn Rk TW</option>
      <option value="artist">Artist</option>
      <option value="title">Title</option>
      <option value="year">Year</option>
      <option value="spins_tw">Spins TW</option>
      <option value="spins_delta">Spins +/-</option>
      <option value="market_shr">Market Shr (%)</option>
      <option value="first_played">First Played</option>
      <option value="atd">ATD</option>
      <option value="nat_rank">Nat Rank</option>
      <option value="nat_peak">Nat Peak</option>
      <option value="od_canada">OD-TW CANADA</option>
      <option value="od_market">OD-TW MARKET</option>
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

  <!-- Manual row add (enabled only for saved weeks) -->
  <span id="rc-manual-row-add">
    Artist: <input type="text" id="rc-manual-artist" style="width:120px;" disabled>
    Title:  <input type="text" id="rc-manual-title"  style="width:140px;" disabled>
    CanCon:
    <select id="rc-manual-cancon" disabled>
      <option value="">--</option>
      <option value="Yes">Yes</option>
      <option value="No">No</option>
    </select>
    Year: <input type="text" id="rc-manual-year" style="width:60px;" disabled>
    <button id="rc-add-manual-row" disabled>Add Row</button>
  </span>
</div>

<!-- Dashboard table -->
<div class="rc-dashboard-module">
<?php if (!empty($rows)): ?>
  <div class="rc-table-scroll">
    <table class="rc-dashboard-table">
      <thead>
        <tr>
          <th></th><th></th>
          <th colspan="2">Stn Rk</th>
          <th></th><th></th><th></th><th></th>
          <th colspan="2">Spins</th>
          <th colspan="4">Day Parts</th>
          <th>Market</th>
          <th colspan="2">Historical</th>
          <th colspan="2">Format</th>
          <th colspan="2">OD-TW</th>
        </tr>
        <tr>
          <th>TW</th>
          <th>NW</th>
          <th>TW</th>
          <th>UP</th>
          <th>Artist</th>
          <th>Title</th>
          <th>CanCon</th>
          <th>Year</th>
          <th>TW</th>
          <th>+/-</th>
          <th>AMD</th>
          <th>MID</th>
          <th>PMD</th>
          <th>EVE</th>
          <th>Shr&nbsp;(%)</th>
          <th>First Played</th>
          <th>ATD</th>
          <th>Rank</th>
          <th>Peak</th>
          <th>CANADA</th>
          <th>MARKET</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><select name="TW[]"><?= $twOptions ?></select></td>
          <td><select name="NW[]"><?= $nwOptions ?></select></td>
          <td><?= htmlspecialchars((string) ($row['Stn Rk TW'] ?? '')) ?></td>
          <td class="rc-stn-up"><?= htmlspecialchars((string) ($row['Stn Rk UP'] ?? '')) ?></td>
          <td><?= htmlspecialchars($row['Artist'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Title'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['CanCon'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Year'] ?? '') ?></td>
          <td><?= htmlspecialchars((string) ($row['Spins TW'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($row['+/-'] ?? '')) ?></td>
          <td><?= htmlspecialchars($row['AMD'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['MID'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['PMD'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['EVE'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Market Shr (%)'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['First Played'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['ATD'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Rank'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Peak'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['CANADA'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['MARKET'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="rc-no-data">No data available. Upload your CSV files above to begin.</p>
<?php endif; ?>
</div>