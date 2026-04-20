/* mod_ciwv_radiocharts – dashboard.js */
document.addEventListener('DOMContentLoaded', function () {

  // ── Column index map (must match tmpl/default.php column order) ──────────
  const COL = {
    tw:          0,
    nw:          1,
    stn_rk_tw:   2,
    stn_rk_up:   3,
    artist:      4,
    title:       5,
    cancon:      6,
    year:        7,
    spins_tw:    8,
    spins_delta: 9,
    amd:         10,
    mid:         11,
    pmd:         12,
    eve:         13,
    market_shr:  14,
    first_played:15,
    atd:         16,
    nat_rank:    17,
    nat_peak:    18,
    od_canada:   19,
    od_market:   20
  };

  // Custom sort order for TW category
  const TW_ORDER = ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT',''];

  // ── Helpers ────────────────────────────────────────────────────────────────

  function getTable() {
    return document.querySelector('.rc-dashboard-table');
  }

  function getTBody() {
    const t = getTable();
    return t ? t.tBodies[0] : null;
  }

  // Match PHP's normalize()
  function normalizeKey(artist, title) {
    let a = artist.trim().toLowerCase();
    let t = title.trim().toLowerCase();
    if (typeof a.normalize === 'function') {
      a = a.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    a = a.replace(/\s*&\s*|\s+and\s+/gi, ',');
    a = a.replace(/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i, '');
    t = t.replace(/\s*(f\/|feat\.|ft\.|featuring)\s*.+$/i, '');
    t = t.replace(/\.\.\.$/, '');
    a = a.replace(/[\"'()\[\].,!?\-]/g, '').replace(/\s+/g, ' ').replace(/^,|,$/g, '').trim();
    t = t.replace(/[\"'()\[\].,!?\-]/g, '').trim();
    return a + '|' + t;
  }

  function selectValue(row, col) {
    const cell = row.cells[col];
    if (!cell) return '';
    const sel = cell.querySelector('select');
    return sel ? (sel.value || '') : cell.textContent.trim();
  }

  // ── Week selector ──────────────────────────────────────────────────────────
  const weekSelect = document.getElementById('rc-week-select');
  if (weekSelect) {
    weekSelect.addEventListener('change', function () {
      const v = weekSelect.value;
      location.href = location.pathname + '?week_start=' + encodeURIComponent(v);
    });
  }

  // Read module options injected via Joomla.addScriptOptions
  const rcOpts      = (typeof Joomla !== 'undefined' && Joomla.getOptions) ? (Joomla.getOptions('rciwv') || {}) : {};
  const weekStart   = rcOpts.weekStart    || window.RCIWV_WEEK_START    || '';
  const selectedWeek= rcOpts.selectedWeek || window.RCIWV_SELECTED_WEEK || 'current';

  // ── Sorting ────────────────────────────────────────────────────────────────
  const sortSelect   = document.getElementById('rc-sort-select');
  const reverseCheck = document.getElementById('rc-reverse-sort');

  function sortTable() {
    const tbody = getTBody();
    if (!tbody) return;
    const rows   = Array.from(tbody.rows);
    const sortBy = sortSelect ? sortSelect.value : 'tw';

    if (sortBy === 'tw') {
      rows.sort((a, b) => {
        const vA = selectValue(a, COL.tw);
        const vB = selectValue(b, COL.tw);
        const iA = TW_ORDER.indexOf(vA);
        const iB = TW_ORDER.indexOf(vB);
        return (iA === -1 ? TW_ORDER.length : iA) - (iB === -1 ? TW_ORDER.length : iB);
      });
    } else if (sortBy === 'stn_rk_tw') {
      rows.sort((a, b) => {
        const nA = parseInt(a.cells[COL.stn_rk_tw]?.textContent.trim()) || 0;
        const nB = parseInt(b.cells[COL.stn_rk_tw]?.textContent.trim()) || 0;
        if (nA === 0 && nB !== 0) return 1;
        if (nB === 0 && nA !== 0) return -1;
        return nA - nB;
      });
    } else {
      const colIdx = {
        artist: COL.artist, title: COL.title, year: COL.year,
        spins_tw: COL.spins_tw, spins_delta: COL.spins_delta,
        market_shr: COL.market_shr, first_played: COL.first_played,
        atd: COL.atd, nat_rank: COL.nat_rank, nat_peak: COL.nat_peak,
        od_canada: COL.od_canada, od_market: COL.od_market
      }[sortBy];
      if (colIdx !== undefined) {
        rows.sort((a, b) => {
          const cA = a.cells[colIdx]?.textContent.trim() ?? '';
          const cB = b.cells[colIdx]?.textContent.trim() ?? '';
          if (!isNaN(cA) && !isNaN(cB) && cA !== '' && cB !== '') {
            return parseFloat(cA) - parseFloat(cB);
          }
          if (/^\d{4}-\d{2}-\d{2}$/.test(cA) && /^\d{4}-\d{2}-\d{2}$/.test(cB)) {
            return new Date(cA) - new Date(cB);
          }
          return cA.localeCompare(cB, undefined, { numeric: true, sensitivity: 'base' });
        });
      }
    }

    if (reverseCheck && reverseCheck.checked) rows.reverse();
    rows.forEach(r => tbody.appendChild(r));
  }

  function initialSort() {
    const tbody = getTBody();
    if (!tbody) return;
    const rows   = Array.from(tbody.rows);
    const hasTW  = rows.some(r => {
      const sel = r.cells[COL.tw]?.querySelector('select');
      return sel && sel.value !== '';
    });
    if (sortSelect) sortSelect.value = hasTW ? 'tw' : 'stn_rk_tw';
    sortTable();
  }

  if (sortSelect)   sortSelect.addEventListener('change', sortTable);
  if (reverseCheck) reverseCheck.addEventListener('change', sortTable);

  // ── Load saved TW/NW from DB ───────────────────────────────────────────────
  if (weekStart) {
    fetch('/modules/mod_ciwv_radiocharts/ajax/loadstate.php?week_start=' + encodeURIComponent(weekStart))
      .then(r => r.json())
      .then(savedState => {
        const stateMap = {};
        if (Array.isArray(savedState)) {
          savedState.forEach(entry => {
            const k = normalizeKey(entry.artist ?? '', entry.title ?? '');
            stateMap[k] = entry;
          });
        }
        const tbody = getTBody();
        if (!tbody) return;
        Array.from(tbody.rows).forEach(row => {
          const artist = row.cells[COL.artist]?.textContent.trim() || '';
          const title  = row.cells[COL.title]?.textContent.trim()  || '';
          const k      = normalizeKey(artist, title);
          if (stateMap[k]) {
            const twSel = row.querySelector('select[name="TW[]"]');
            const nwSel = row.querySelector('select[name="NW[]"]');
            if (twSel) twSel.value = stateMap[k].tw || '';
            if (nwSel) nwSel.value = stateMap[k].nw || '';
          }
        });
        // Sort after loading state (TW values now known)
        initialSort();
      })
      .catch(() => initialSort());
  } else {
    initialSort();
  }

  // ── Save Data ──────────────────────────────────────────────────────────────
  document.getElementById('rc-save-state')?.addEventListener('click', function () {
    const tbody = getTBody();
    if (!tbody) return;

    const stateRows = Array.from(tbody.rows).map(row => ({
      tw:              row.querySelector('select[name="TW[]"]')?.value ?? '',
      nw:              row.querySelector('select[name="NW[]"]')?.value ?? '',
      'Stn Rk TW':     row.cells[COL.stn_rk_tw]?.textContent.trim()  ?? '',
      'Stn Rk LW':     '',  // Stn Rk LW is not a visible column; populated by helper.php only
      'Stn Rk UP':     row.cells[COL.stn_rk_up]?.textContent.trim()  ?? '',
      artist:          row.cells[COL.artist]?.textContent.trim()      ?? '',
      title:           row.cells[COL.title]?.textContent.trim()       ?? '',
      cancon:          row.cells[COL.cancon]?.textContent.trim()      ?? '',
      year:            row.cells[COL.year]?.textContent.trim()        ?? '',
      'Spins TW':      row.cells[COL.spins_tw]?.textContent.trim()   ?? '',
      '+/-':           row.cells[COL.spins_delta]?.textContent.trim() ?? '',
      AMD:             row.cells[COL.amd]?.textContent.trim()         ?? '',
      MID:             row.cells[COL.mid]?.textContent.trim()         ?? '',
      PMD:             row.cells[COL.pmd]?.textContent.trim()         ?? '',
      EVE:             row.cells[COL.eve]?.textContent.trim()         ?? '',
      'Market Shr (%)':row.cells[COL.market_shr]?.textContent.trim() ?? '',
      'First Played':  row.cells[COL.first_played]?.textContent.trim() ?? '',
      ATD:             row.cells[COL.atd]?.textContent.trim()         ?? '',
      Rank:            row.cells[COL.nat_rank]?.textContent.trim()    ?? '',
      Peak:            row.cells[COL.nat_peak]?.textContent.trim()    ?? '',
      CANADA:          row.cells[COL.od_canada]?.textContent.trim()   ?? '',
      MARKET:          row.cells[COL.od_market]?.textContent.trim()   ?? '',
    }));

    const metaElem = document.getElementById('rc-meta-line');
    const metaLine = metaElem ? metaElem.textContent.trim() : '';

    fetch('/modules/mod_ciwv_radiocharts/ajax/savestate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        state:      stateRows,
        week_start: weekStart,
        meta_line:  metaLine
      })
    })
    .then(r => r.json())
    .then(d => alert('Saved for week: ' + (d.week_start || 'unknown')))
    .catch(() => alert('Save failed.'));
  });

  // ── Export CSV ─────────────────────────────────────────────────────────────
  document.getElementById('rc-export-csv')?.addEventListener('click', function () {
    const tbody = getTBody();
    if (!tbody) return;

    const allRows = Array.from(tbody.rows).map(row => ({
      row,
      tw: selectValue(row, COL.tw),
      nw: selectValue(row, COL.nw)
    }));

    // Group: A1/J/A2/P/B/C/D then ADDs-by-NW then others
    const groups   = {};
    const addsByNW = {};
    ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3'].forEach(c => { groups[c] = []; addsByNW[c] = []; });
    const leftovers = [];

    allRows.forEach(item => {
      if (item.tw === 'ADD' && addsByNW[item.nw]) {
        addsByNW[item.nw].push(item.row);
      } else if (groups[item.tw]) {
        groups[item.tw].push(item.row);
      } else if (item.tw !== '' || item.nw !== '') {
        leftovers.push(item.row);
      }
    });

    const finalRows = [];
    ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3'].forEach(k => {
      finalRows.push(...groups[k], ...addsByNW[k]);
    });
    finalRows.push(...leftovers);

    const h1 = ['','','Stn Rk','','','','','','Spins','','Day Parts','','','','Market','Historical','','Format','','OD-STREAMS-TW',''];
    const h2 = ['TW','NW','TW','UP','Artist','Title','CanCon','Year','TW','+/-','AMD','MID','PMD','EVE','Shr (%)','First Played','ATD','Rank','Peak','CANADA','MARKET'];

    function escCSV(v) {
      if (v == null) return '';
      v = String(v).replace(/"/g, '""');
      return (v.match(/("|,|\n)/)) ? '"' + v + '"' : v;
    }

    const lines = [h1.map(escCSV).join(','), h2.map(escCSV).join(',')];
    finalRows.forEach(row => {
      const cells = Array.from(row.cells).map((cell, idx) => {
        if (idx === COL.tw || idx === COL.nw) {
          const sel = cell.querySelector('select');
          return sel ? sel.value : cell.textContent.trim();
        }
        return cell.textContent.trim();
      });
      lines.push(cells.map(escCSV).join(','));
    });

    // Also auto-save on export
    const stateRows = Array.from(getTBody().rows).map(row => ({
      artist: row.cells[COL.artist]?.textContent.trim(),
      title:  row.cells[COL.title]?.textContent.trim(),
      tw:     row.querySelector('select[name="TW[]"]')?.value,
      nw:     row.querySelector('select[name="NW[]"]')?.value,
      'Spins TW': row.cells[COL.spins_tw]?.textContent.trim()
    }));

    const doDownload = () => {
      const BOM  = '\uFEFF';
      const blob = new Blob([BOM + lines.join('\r\n')], { type: 'text/csv' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href = url; a.download = 'radiocharts_export.csv';
      document.body.appendChild(a); a.click();
      setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
    };

    fetch('/modules/mod_ciwv_radiocharts/ajax/savestate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ state: stateRows, week_start: weekStart })
    }).finally(doDownload);
  });

  // ── Manual row add (only for saved weeks) ─────────────────────────────────
  const isCurrent = selectedWeek === 'current';
  const manualInputs = ['rc-manual-artist','rc-manual-title','rc-manual-cancon','rc-manual-year','rc-add-manual-row'];
  manualInputs.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.disabled = isCurrent;
  });

  document.getElementById('rc-add-manual-row')?.addEventListener('click', function () {
    const artist = document.getElementById('rc-manual-artist')?.value.trim() || '';
    const title  = document.getElementById('rc-manual-title')?.value.trim() || '';
    const cancon = document.getElementById('rc-manual-cancon')?.value || '';
    const year   = document.getElementById('rc-manual-year')?.value.trim() || '';
    if (!artist || !title) { alert('Artist and Title are required.'); return; }
    if (year && !/^\d{4}$/.test(year)) { alert('Year should be 4 digits.'); return; }

    const tbody = getTBody();
    if (!tbody) return;

    const twVals = ['','A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT'];
    const nwVals = ['','A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT',
                    'A1?','J?','A2?','P?','B?','C?','D?','GOLD?','PC2?','PC3?','Q?','OUT?'];

    function makeSelect(name, vals) {
      const sel = document.createElement('select');
      sel.name = name;
      vals.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        sel.appendChild(opt);
      });
      return sel;
    }

    const tr = document.createElement('tr');

    // TW dropdown
    const tdTW = document.createElement('td');
    tdTW.appendChild(makeSelect('TW[]', twVals));
    tr.appendChild(tdTW);

    // NW dropdown
    const tdNW = document.createElement('td');
    tdNW.appendChild(makeSelect('NW[]', nwVals));
    tr.appendChild(tdNW);

    // Stn Rk TW, Stn Rk UP (blank)
    tr.appendChild(document.createElement('td'));
    tr.appendChild(document.createElement('td'));

    // Artist, Title, CanCon, Year (user values via textContent)
    [artist, title, cancon, year].forEach(val => {
      const td = document.createElement('td');
      td.textContent = val;
      tr.appendChild(td);
    });

    // Remaining blank columns
    for (let i = 0; i < 13; i++) tr.appendChild(document.createElement('td'));

    tbody.appendChild(tr);

    document.getElementById('rc-manual-artist').value = '';
    document.getElementById('rc-manual-title').value  = '';
    document.getElementById('rc-manual-cancon').value = '';
    document.getElementById('rc-manual-year').value   = '';
  });

});
