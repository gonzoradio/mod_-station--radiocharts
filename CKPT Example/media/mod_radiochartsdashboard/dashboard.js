document.addEventListener('DOMContentLoaded', function () {
  const sortSelect = document.getElementById('sort-select');
  const reverseSort = document.getElementById('reverse-sort');
	const selectedWeek = window.RCDASHBOARD_WEEK_START;
	
	
  if (!sortSelect) return;

  // Week selector logic
  const weekSelect = document.getElementById('week-select');
  if (weekSelect) {
    weekSelect.addEventListener('change', function () {
      const value = weekSelect.value;
      if (value === 'current') {
        location.href = location.pathname + '?week_start=current';
      } else {
        location.href = location.pathname + '?week_start=' + encodeURIComponent(value);
      }
    });
  }

  // Custom order for TW dropdown
  const twOrder = ['A1', 'A2', 'B', 'C', 'D', 'OUT', 'ADD', ''];

  // Sorting logic
  function sortTable() {
    const columnMap = {
      tw: 0,
      stn_rk_tw: 2,
      artist: 4,
      title: 5,
      year: 7,
      spins_tw: 8,
      spins_delta: 9,
      market_shr: 14,
      first_played: 15,
      atd: 16,
      format_rank: 17,
      format_peak: 18,
      od_tw_canada: 19,
      od_tw_market: 20
    };
    let colIndex = columnMap[sortSelect.value];
    const table = document.querySelector('.radiocharts-dashboard-module table')
      || document.querySelector('table.radiocharts-dashboard-module');
    if (!table) return;
    const tbody = table.tBodies[0];
    if (!tbody) return;
    const rows = Array.from(tbody.rows);

    if (sortSelect.value === "tw") {
      // Custom sort order for TW
      rows.sort((a, b) => {
        const valA = a.cells[0]?.querySelector('select')?.value || '';
        const valB = b.cells[0]?.querySelector('select')?.value || '';
        const idxA = twOrder.indexOf(valA);
        const idxB = twOrder.indexOf(valB);
        return (idxA === -1 ? twOrder.length : idxA) - (idxB === -1 ? twOrder.length : idxB);
      });
    } else if (sortSelect.value === "stn_rk_tw") {
      rows.sort((a, b) => {
        const cellA = a.cells[2]?.textContent.trim() ?? '';
        const cellB = b.cells[2]?.textContent.trim() ?? '';
        const numA = parseInt(cellA) || 0;
        const numB = parseInt(cellB) || 0;
        if (numA === 0 && numB === 0) return 0;
        if (numA === 0) return 1;
        if (numB === 0) return -1;
        return numA - numB;
      });
    } else {
      // Default sorting for other columns
      rows.sort((a, b) => {
        let colA = a.cells[colIndex]?.textContent.trim() ?? '';
        let colB = b.cells[colIndex]?.textContent.trim() ?? '';
        if (!isNaN(colA) && !isNaN(colB) && colA !== '' && colB !== '') {
          return parseFloat(colA) - parseFloat(colB);
        }
        if (colA.match(/^\d{4}-\d{2}-\d{2}$/) && colB.match(/^\d{4}-\d{2}-\d{2}$/)) {
          return new Date(colA) - new Date(colB);
        }
        return colA.localeCompare(colB, undefined, {numeric: true, sensitivity: 'base'});
      });
    }

    if (reverseSort && reverseSort.checked) {
      rows.reverse();
    }
    rows.forEach(row => tbody.appendChild(row));
  }

  // On page load, set sort to TW unless all TW values are blank; otherwise fallback to Stn Rk TW
  function initialSort() {
    const table = document.querySelector('.radiocharts-dashboard-module table')
      || document.querySelector('table.radiocharts-dashboard-module');
    if (!table) return;
    const tbody = table.tBodies[0];
    if (!tbody) return;
    const rows = Array.from(tbody.rows);
    // Check if any TW values are set
    const hasTWValue = rows.some(row => {
      const val = row.cells[0]?.querySelector('select')?.value || '';
      return val !== '';
    });
    if (hasTWValue) {
      sortSelect.value = 'tw';
    } else {
      sortSelect.value = 'stn_rk_tw';
    }
    sortTable();
  }

  sortSelect.addEventListener('change', sortTable);
  if (reverseSort) reverseSort.addEventListener('change', sortTable);

  // Initial sort on page load
  initialSort();

  // ---- LOAD STATE FROM DB AND POPULATE TW/NW ----

  // Normalization function: must match PHP's helper!
  function normalizeKey(artist, title) {
    let a = artist.trim().toLowerCase();
    let t = title.trim().toLowerCase();
    if (typeof a.normalize === 'function') {
      a = a.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    a = a.replace(/\s+&\s+|\s+and\s+| feat\. | ft\. | featuring |\s+x\s+| X /gi, ',');
    a = a.replace(/([a-z])x([a-z])/gi, '$1,$2');
    a = a.replace(/[\"'().\[\],!?-]/g, '');
    t = t.replace(/[\"'().\[\],!?-]/g, '');
    t = t.replace(/\.\.\.$/, '');
    a = a.replace(/\s*,\s*/g, ',').replace(/\s+/g, ' ').replace(/^,|,$/g, '');
    a = a.trim();
    t = t.trim();
    return a + '|' + t;
  }

  const weekStart = window.RCDASHBOARD_WEEK_START;
  fetch('/modules/mod_radiochartsdashboard/ajax/loadstate.php?week_start=' + encodeURIComponent(weekStart))
    .then(response => response.json())
    .then(savedState => {
      const stateMap = {};
      if (Array.isArray(savedState)) {
        savedState.forEach(entry => {
          const key = normalizeKey(entry.artist ?? '', entry.title ?? '');
          stateMap[key] = entry;
        });
      }
      const rows = document.querySelectorAll('.radiocharts-dashboard-module tbody tr');
      rows.forEach(row => {
        const artist = row.cells[4]?.textContent.trim() || '';
        const title = row.cells[5]?.textContent.trim() || '';
        const key = normalizeKey(artist, title);
        if (stateMap[key]) {
          const twSelect = row.querySelector('select[name="TW[]"]');
          const nwSelect = row.querySelector('select[name="NW[]"]');
          if (twSelect) twSelect.value = stateMap[key].tw || '';
          if (nwSelect) nwSelect.value = stateMap[key].nw || '';
        }
      });
    });

  // SAVE DATA BUTTON (existing logic unchanged)
  document.getElementById('save-state')?.addEventListener('click', function () {
  const table = document.querySelector('.radiocharts-dashboard-module table');
  if (!table) return;
  const tbody = table.tBodies[0];
  if (!tbody) return;

  // Save all columns
  const stateRows = Array.from(tbody.rows).map(row => ({
    tw: row.querySelector('select[name="TW[]"]')?.value ?? '',
    nw: row.querySelector('select[name="NW[]"]')?.value ?? '',
    'Stn Rk TW': row.cells[2]?.textContent.trim() ?? '',
			'Stn Rk LW': row.cells[3]?.textContent.trim() ?? '',
    'Stn Rk UP': row.cells[3]?.innerHTML.trim() ?? '',
    artist: row.cells[4]?.textContent.trim() ?? '',
    title: row.cells[5]?.textContent.trim() ?? '',
    cancon: row.cells[6]?.textContent.trim() ?? '',
    year: row.cells[7]?.textContent.trim() ?? '',
    'Spins TW': row.cells[8]?.textContent.trim() ?? '',
    '+/-': row.cells[9]?.textContent.trim() ?? '',
    AMD: row.cells[10]?.textContent.trim() ?? '',
    MID: row.cells[11]?.textContent.trim() ?? '',
    PMD: row.cells[12]?.textContent.trim() ?? '',
    EVE: row.cells[13]?.textContent.trim() ?? '',
    'Market Shr (%)': row.cells[14]?.textContent.trim() ?? '',
    'First Played': row.cells[15]?.textContent.trim() ?? '',
    ATD: row.cells[16]?.textContent.trim() ?? '',
    Rank: row.cells[17]?.textContent.trim() ?? '',
    Peak: row.cells[18]?.textContent.trim() ?? '',
    CANADA: row.cells[19]?.textContent.trim() ?? '',
    MARKET: row.cells[20]?.textContent.trim() ?? ''
  }));

  // Get week and meta line
  const selectedWeek = window.RCDASHBOARD_WEEK_START;
  var metaLineElem = document.querySelector('#meta-line');
  var metaLine = metaLineElem ? metaLineElem.textContent : '';

  fetch('/modules/mod_radiochartsdashboard/ajax/savestate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      state: stateRows,
      week_start: selectedWeek,
      meta_line: metaLine
    })
  })
  .then(res => res.json())
  .then(data => {
    alert('Data saved for week: ' + (data.week_start || 'unknown'));
  })
  .catch(() => alert('Failed to save data!'));
});
  // CSV EXPORT (existing logic unchanged)
  document.getElementById('export-csv')?.addEventListener('click', function () {
    const table = document.querySelector('.radiocharts-dashboard-module table')
      || document.querySelector('table.radiocharts-dashboard-module');
    if (!table) return;
    const tbody = table.tBodies[0];
    if (!tbody) return;

    function getTWorNWValue(row, col) {
      const cell = row.cells[col];
      if (!cell) return '';
      const select = cell.querySelector('select');
      return select ? select.value : cell.textContent.trim();
    }

    // Only include rows where TW or NW is set (including OUT)
    const exportRows = Array.from(tbody.rows).filter(row => {
      const tw = row.querySelector('select[name="TW[]"]')?.value;
      const nw = row.querySelector('select[name="NW[]"]')?.value;
      return (tw && tw !== '') || (nw && nw !== '');
    });

    // Build groups as before, but only with filtered rows
    const allRows = exportRows.map(row => ({
      row,
      tw: getTWorNWValue(row, 0),
      nw: getTWorNWValue(row, 1)
    }));

    const groups = { A1: [], A2: [], B: [], C: [], D: [] };
    const addsByNW = { A1: [], A2: [], B: [], C: [], D: [] };
    const leftovers = [];

    for (const item of allRows) {
      if (item.tw === 'ADD' && addsByNW[item.nw]) {
        addsByNW[item.nw].push(item.row);
      } else if (groups[item.tw]) {
        groups[item.tw].push(item.row);
      } else {
        leftovers.push(item.row);
      }
    }

    const finalRows = [];
    for (const key of ['A1', 'A2', 'B', 'C', 'D']) {
      finalRows.push(...groups[key]);
      finalRows.push(...addsByNW[key]);
    }
    finalRows.push(...leftovers);

    const headerRow1 = [
      '', '', 'Stn Rk', '', '', '', '', '', 'Spins', '',
      'Day Parts', '', '', '', 'Market', 'Historical', '', 'Format', '', 'OD-STREAMS-TW', ''
    ];
    const headerRow2 = [
      'TW', 'NW', 'TW', 'UP', 'Artist', 'Title', 'CanCon', 'Year', 'TW', '+/-',
      'AMD', 'MID', 'PMD', 'EVE', 'Shr (%)', 'First Played', 'ATD', 'Rank', 'Peak', 'CANADA', 'MARKET'
    ];

    function escapeCSV(val) {
      if (val == null) return '';
      val = val.toString().replace(/"/g, '""');
      if (val.match(/("|,|\n)/)) return `"${val}"`;
      return val;
    }

    const csvLines = [];
    csvLines.push(headerRow1.map(escapeCSV).join(','));
    csvLines.push(headerRow2.map(escapeCSV).join(','));

    for (const row of finalRows) {
      const cells = Array.from(row.cells).map((cell, idx) => {
        if (idx === 0 || idx === 1) {
          const select = cell.querySelector('select');
          if (select && select.selectedIndex >= 0) {
            return select.options[select.selectedIndex].text.trim();
          }
        }
        if (idx === 3) {
          // Export whatever is in the cell (the span will produce ▲, blue or red)
          return cell.textContent.trim();
        }
        return cell.textContent.trim();
      });
      csvLines.push(cells.map(escapeCSV).join(','));
    }

    // Save state on export as well (so TW/NW changes are kept)
    const stateRows = Array.from(tbody.rows).map(row => ({
      artist: row.cells[4]?.textContent.trim(),
      title: row.cells[5]?.textContent.trim(),
      spins_tw: row.cells[8]?.textContent.trim(),
      tw: row.querySelector('select[name="TW[]"]')?.value,
      nw: row.querySelector('select[name="NW[]"]')?.value
    }));

    fetch('/modules/mod_radiochartsdashboard/ajax/savestate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          state: stateRows
        })
      })
      .then(res => res.json())
      .then(data => {
        const BOM = '\uFEFF';
        const csvContent = BOM + csvLines.join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'playlist_export.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        }, 100);
      })
      .catch(err => {
        const BOM = '\uFEFF';
        const csvContent = BOM + csvLines.join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'playlist_export.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        }, 100);
      });
  });
// --- Manual Row Add UI logic ---
// Enable/disable manual row add fields based on selected week
function enableManualRowAdd(enable) {
  document.getElementById('manual-artist').disabled = !enable;
  document.getElementById('manual-title').disabled = !enable;
  document.getElementById('manual-cancon').disabled = !enable;
  document.getElementById('manual-year').disabled = !enable;
  document.getElementById('add-manual-row').disabled = !enable;
}

// On load, set state
enableManualRowAdd(window.RCDASHBOARD_SELECTED_WEEK !== 'current');

// Add manual row to table
document.getElementById('add-manual-row')?.addEventListener('click', function () {
  const artist = document.getElementById('manual-artist').value.trim();
  const title = document.getElementById('manual-title').value.trim();
  const cancon = document.getElementById('manual-cancon').value;
  const year = document.getElementById('manual-year').value.trim();

  if (!artist || !title) {
    alert('Artist and Title are required.');
    return;
  }
  if (year && !/^\d{4}$/.test(year)) {
    alert('Year should be 4 digits (YYYY).');
    return;
  }

  const table = document.querySelector('.radiocharts-dashboard-module table');
  if (!table) return;
  const tbody = table.tBodies[0];
  if (!tbody) return;

  // Create new row (structure matches your table)
  const tr = document.createElement('tr');
  // TW dropdown
  tr.innerHTML += `<td><select name="TW[]">
    <option value=""></option>
    <option value="A1">A1</option>
    <option value="A2">A2</option>
    <option value="B">B</option>
    <option value="C">C</option>
    <option value="D">D</option>
    <option value="ADD">ADD</option>
    <option value="OUT">OUT</option>
  </select></td>`;
  // NW dropdown
  tr.innerHTML += `<td><select name="NW[]">
    <option value=""></option>
    <option value="A1">A1</option>
    <option value="A2">A2</option>
    <option value="B">B</option>
    <option value="C">C</option>
    <option value="D">D</option>
    <option value="OUT">OUT</option>
    <option value="GOLD1?">GOLD1?</option>
    <option value="A1?">A1?</option>
    <option value="A2?">A2?</option>
    <option value="B?">B?</option>
    <option value="C?">C?</option>
    <option value="D?">D?</option>
    <option value="OUT?">OUT?</option>
  </select></td>`;
  // Stn Rk TW (blank), Stn Rk UP (blank)
  tr.innerHTML += `<td></td><td></td>`;
  // Artist, Title, CanCon, Year
  tr.innerHTML += `<td>${artist}</td>`;
  tr.innerHTML += `<td>${title}</td>`;
  tr.innerHTML += `<td>${cancon}</td>`;
  tr.innerHTML += `<td>${year}</td>`;
  // Spins TW, +/-, AMD, MID, PMD, EVE, Market Shr (%), First Played, ATD, Rank, Peak, CANADA, MARKET
  for (let i = 0; i < 13; i++) tr.innerHTML += `<td></td>`;
  tbody.appendChild(tr);

  // Reset fields
  document.getElementById('manual-artist').value = '';
  document.getElementById('manual-title').value = '';
  document.getElementById('manual-cancon').value = '';
  document.getElementById('manual-year').value = '';
});
});