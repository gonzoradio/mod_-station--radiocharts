<?php
defined('_JEXEC') or die;
JHtml::stylesheet('media/mod_radiochartsdashboard/style.css');
?>
<?php
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

// Load dashboard.js, cache bust version
Factory::getDocument()->addScript(Uri::root() . 'media/mod_radiochartsdashboard/dashboard.js?v=20250818');
?>
<?php if (!empty($meta['report'])): ?>
  <div id="meta-line" style="margin-bottom:1em;<?= ($selectedWeek !== 'current' ? 'color:red;' : '') ?>">
    <strong><?= htmlspecialchars($meta['report']) ?></strong>
  </div>
<?php endif; ?>
<div style="display:flex; justify-content:flex-start; gap:1em; margin-bottom:1em;">
  &nbsp;&nbsp;&nbsp;&nbsp;1.&nbsp;&nbsp;<form method="post" enctype="multipart/form-data" style="display:inline;padding:10px;background-color: #F9F0F0;">
    <label><strong>Upload Station Playlist:</strong>
      <input type="file" name="playlist_csv" accept=".csv" required>
    </label>
    <button type="submit">Upload</button>
  </form>&nbsp;&nbsp;&nbsp;&nbsp;2.&nbsp;&nbsp;
  <form method="post" enctype="multipart/form-data" style="display:inline;padding:10px;background-color: #F9F0F0;">
    <label><strong>Upload National Chart:</strong>
      <input type="file" name="rolling_csv" accept=".csv" required>
    </label>
    <button type="submit">Upload</button>
  </form>&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;&nbsp;
	<form method="post" enctype="multipart/form-data" style="display:inline;padding:10px;background-color: #F9F0F0;">
  <label><strong>Upload Streaming Data:</strong>
    <input type="file" name="streaming_csv" accept=".csv" required>
  </label>
  <button type="submit">Upload</button>
</form>
</div>
<div class="radiocharts-dashboard-module">
  <?php if (!empty($meta['LW']) && !empty($meta['TW']) && !empty($meta['Station'])): ?>
    <div>
      <strong>LW:</strong> <?= htmlspecialchars($meta['LW']) ?>
      <strong>TW:</strong> <?= htmlspecialchars($meta['TW']) ?>
      <strong>Station:</strong> <?= htmlspecialchars($meta['Station']) ?>
    </div>
    <hr>
  <?php endif; ?>
<br />
<div style="display: flex; align-items: center; gap: 1em; margin-bottom: 0.75em;">
  <h3 style="margin: 0;">Combined Data</h3>
  <label for="sort-select" style="font-weight: normal; margin-left: 1em;">
    Sort by:
    <select id="sort-select" style="margin-left: 0.5em;">
  <option value="tw">TW</option>
  <!-- <option value="nw">NW</option> REMOVE THIS LINE -->
  <option value="stn_rk_tw">Stn Rk TW</option>
  <option value="artist">Artist</option>
  <option value="title">Title</option>
  <option value="year">Year</option>
  <option value="spins_tw">Spins TW</option>
  <option value="spins_delta">Spins +/-</option>
  <option value="market_shr">Market Shr (%)</option>
  <option value="first_played">First Played</option>
  <option value="atd">ATD</option>
  <option value="format_rank">Format Rank</option>
  <option value="format_peak">Format Peak</option>
  <option value="od_tw_canada">OD-TW CANADA</option>
  <option value="od_tw_market">OD-TW MARKET</option>
</select>
  </label>
  <label style="margin-left: 0.75em; font-weight: normal;">
    <input type="checkbox" id="reverse-sort" style="vertical-align: middle; margin-right: 0.2em;">
    Reverse
  </label>
  <button id="save-state" style="margin-left: 1em;">Save Data</button>
  <button id="export-csv" style="margin-left: 1.5em;">Export CSV</button>
  <div style="margin-bottom:1em; display: flex; align-items: center;">
  <label><strong>Week:</strong>
    <select id="week-select">
      <option value="current"<?= ($selectedWeek === 'current' ? ' selected' : '') ?>>Current (CSV files)</option>
      <?php if (!empty($allWeeks)): ?>
        <?php foreach ($allWeeks as $week): ?>
          <option value="<?= htmlspecialchars($week) ?>"<?= ($selectedWeek == $week ? ' selected' : '') ?>><?= htmlspecialchars($week) ?></option>
        <?php endforeach; ?>
      <?php endif; ?>
    </select>
  </label>
  <!-- Manual Add Form: Only enabled when not 'current' -->
  <div id="manual-row-add" style="display: flex; align-items: center; margin-left: 2em;">
    <label style="margin-right: 0.75em;">
      Artist: <input type="text" id="manual-artist" style="width:120px;" disabled>
    </label>
    <label style="margin-right: 0.75em;">
      Title: <input type="text" id="manual-title" style="width:140px;" disabled>
    </label>
    <label style="margin-right: 0.75em;">
      CanCon:
      <select id="manual-cancon" disabled>
        <option value="">--</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>
    </label>
    <label style="margin-right: 0.75em;">
      Year: <input type="text" id="manual-year" style="width:70px;" disabled>
    </label>
    <button id="add-manual-row" disabled>Add New</button>
  </div>
</div>
	
</div>
<?php if (!empty($rows)): ?>
	<div class="radiocharts-dashboard-scroll">
  <table class="radiocharts-dashboard-module">
   <thead>
  <tr>
    <th></th>
    <th></th>
    <th>Stn Rk</th>
    <th></th>
    <th></th>
    <th></th>
    <th></th>
    <th></th>
    <th>Spins</th>
    <th></th>
    <th>Day</th>
    <th>Parts</th>
    <th></th>
    <th></th>
    <th>Market</th>
    <th>Historical</th>
    <th></th>
    <th>Format</th>
    <th></th>
    <th>OD-TW</th>
    <th></th>
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
    <th>Shr (%)</th>
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
        <!-- Interactive TW dropdown -->
        <td>
          <select name="TW[]">
            <option value=""></option>
            <option value="A1" <?= (isset($row['TW']) && $row['TW']=='A1')?'selected':'' ?>>A1</option>
            <option value="A2" <?= (isset($row['TW']) && $row['TW']=='A2')?'selected':'' ?>>A2</option>
            <option value="B" <?= (isset($row['TW']) && $row['TW']=='B')?'selected':'' ?>>B</option>
            <option value="C" <?= (isset($row['TW']) && $row['TW']=='C')?'selected':'' ?>>C</option>
            <option value="D" <?= (isset($row['TW']) && $row['TW']=='D')?'selected':'' ?>>D</option>
            <option value="ADD" <?= (isset($row['TW']) && $row['TW']=='ADD')?'selected':'' ?>>ADD</option>
            <option value="OUT" <?= (isset($row['TW']) && $row['TW']=='OUT')?'selected':'' ?>>OUT</option>
          </select>
        </td>
        <!-- Interactive NW dropdown -->
        <td>
          <select name="NW[]">
            <option value=""></option>
            <option value="A1" <?= (isset($row['NW']) && $row['NW']=='A1')?'selected':'' ?>>A1</option>
            <option value="A2" <?= (isset($row['NW']) && $row['NW']=='A2')?'selected':'' ?>>A2</option>
            <option value="B" <?= (isset($row['NW']) && $row['NW']=='B')?'selected':'' ?>>B</option>
            <option value="C" <?= (isset($row['NW']) && $row['NW']=='C')?'selected':'' ?>>C</option>
            <option value="D" <?= (isset($row['NW']) && $row['NW']=='D')?'selected':'' ?>>D</option>
            <option value="OUT" <?= (isset($row['NW']) && $row['NW']=='OUT')?'selected':'' ?>>OUT</option>
            <option value="GOLD1?" <?= (isset($row['NW']) && $row['NW']=='GOLD1?')?'selected':'' ?>>GOLD1?</option>
            <option value="A1?" <?= (isset($row['NW']) && $row['NW']=='A1?')?'selected':'' ?>>A1?</option>
            <option value="A2?" <?= (isset($row['NW']) && $row['NW']=='A2?')?'selected':'' ?>>A2?</option>
            <option value="B?" <?= (isset($row['NW']) && $row['NW']=='B?')?'selected':'' ?>>B?</option>
            <option value="C?" <?= (isset($row['NW']) && $row['NW']=='C?')?'selected':'' ?>>C?</option>
            <option value="D?" <?= (isset($row['NW']) && $row['NW']=='D?')?'selected':'' ?>>D?</option>
            <option value="OUT?" <?= (isset($row['NW']) && $row['NW']=='OUT?')?'selected':'' ?>>OUT?</option>
          </select>
        </td>
        <td><?= htmlspecialchars($row['Stn Rk TW'] ?? '') ?></td>
        <td><?= $row['Stn Rk UP'] ?? '' ?></td>
        <td><?= htmlspecialchars($row['Artist'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['Title'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['CanCon'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['Year'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['Spins TW'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['+/-'] ?? '') ?></td>
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
  <p>No data found.</p>
<?php endif; ?>
</div>